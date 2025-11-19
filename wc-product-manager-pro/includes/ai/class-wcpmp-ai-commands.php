<?php
/**
 * AI Command Processor for price and stock management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_AI_Commands {

    private $openai;

    public function __construct() {
        $this->openai = new WCPMP_OpenAI();
    }

    /**
     * Process natural language command
     */
    public function process_command($command, $language = 'uk') {
        $api_key = get_option('wcpmp_openai_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'wc-product-manager-pro'));
        }

        // Parse command with AI
        $parsed = $this->parse_command($command, $language);

        if (is_wp_error($parsed)) {
            return $parsed;
        }

        // Generate preview
        $preview = $this->generate_preview($parsed);

        if (is_wp_error($preview)) {
            return $preview;
        }

        return array(
            'command' => $parsed,
            'preview' => $preview,
            'confirmation_required' => true
        );
    }

    /**
     * Parse natural language command using AI
     */
    private function parse_command($command, $language) {
        $lang_name = $language === 'uk' ? 'Ukrainian' : 'Russian';

        $prompt = "Parse this e-commerce command in $lang_name and extract structured data.

Command: \"$command\"

Extract:
- action: 'update_price' | 'update_stock' | 'set_price' | 'set_stock'
- operation: 'increase' | 'decrease' | 'set' | 'multiply'
- value: number (percentage or absolute value)
- value_type: 'percent' | 'absolute'
- target: object describing which products
  - type: 'all' | 'category' | 'price_range' | 'stock_range' | 'sku_list'
  - category_name: string (if type is category)
  - min_price/max_price: numbers (if type is price_range)
  - min_stock/max_stock: numbers (if type is stock_range)
  - skus: array (if type is sku_list)

Return ONLY valid JSON, no explanations:
{
  \"action\": \"\",
  \"operation\": \"\",
  \"value\": 0,
  \"value_type\": \"\",
  \"target\": {},
  \"understood\": true,
  \"summary\": \"Brief description of what will happen\"
}

If you cannot understand the command, return:
{\"understood\": false, \"error\": \"explanation\"}";

        $response = $this->call_openai($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        $parsed = json_decode($response, true);

        if (!$parsed || !isset($parsed['understood'])) {
            return new WP_Error('parse_error', __('Could not parse command', 'wc-product-manager-pro'));
        }

        if (!$parsed['understood']) {
            return new WP_Error('not_understood', $parsed['error'] ?? __('Command not understood', 'wc-product-manager-pro'));
        }

        return $parsed;
    }

    /**
     * Generate preview of changes
     */
    private function generate_preview($parsed) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_products';

        // Build query based on target
        $where = array('1=1');
        $values = array();

        $target = $parsed['target'];

        switch ($target['type'] ?? 'all') {
            case 'category':
                if (!empty($target['category_name'])) {
                    $cat_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}wcpmp_categories WHERE name_uk LIKE %s OR name_ru LIKE %s",
                        '%' . $wpdb->esc_like($target['category_name']) . '%',
                        '%' . $wpdb->esc_like($target['category_name']) . '%'
                    ));
                    if ($cat_id) {
                        $where[] = 'category_id = %d';
                        $values[] = $cat_id;
                    }
                }
                break;

            case 'price_range':
                if (isset($target['min_price'])) {
                    $where[] = 'price >= %f';
                    $values[] = $target['min_price'];
                }
                if (isset($target['max_price'])) {
                    $where[] = 'price <= %f';
                    $values[] = $target['max_price'];
                }
                break;

            case 'stock_range':
                if (isset($target['min_stock'])) {
                    $where[] = 'stock_quantity >= %d';
                    $values[] = $target['min_stock'];
                }
                if (isset($target['max_stock'])) {
                    $where[] = 'stock_quantity <= %d';
                    $values[] = $target['max_stock'];
                }
                break;

            case 'sku_list':
                if (!empty($target['skus'])) {
                    $placeholders = implode(',', array_fill(0, count($target['skus']), '%s'));
                    $where[] = "sku IN ($placeholders)";
                    $values = array_merge($values, $target['skus']);
                }
                break;
        }

        $where_sql = implode(' AND ', $where);

        // Get affected products
        $sql = "SELECT id, sku, name_uk, price, sale_price, stock_quantity FROM $table WHERE $where_sql LIMIT 100";

        if (!empty($values)) {
            $products = $wpdb->get_results($wpdb->prepare($sql, $values));
        } else {
            $products = $wpdb->get_results($sql);
        }

        if (empty($products)) {
            return new WP_Error('no_products', __('No products match the criteria', 'wc-product-manager-pro'));
        }

        // Calculate new values
        $preview_items = array();
        $action = $parsed['action'];
        $operation = $parsed['operation'];
        $value = floatval($parsed['value']);
        $value_type = $parsed['value_type'];

        foreach ($products as $product) {
            $item = array(
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name_uk
            );

            if (strpos($action, 'price') !== false) {
                $old_value = floatval($product->price);
                $new_value = $this->calculate_new_value($old_value, $operation, $value, $value_type);

                $item['field'] = 'price';
                $item['old_value'] = $old_value;
                $item['new_value'] = round($new_value, 2);
                $item['change'] = round($new_value - $old_value, 2);
            } else {
                $old_value = intval($product->stock_quantity);
                $new_value = $this->calculate_new_value($old_value, $operation, $value, $value_type);

                $item['field'] = 'stock_quantity';
                $item['old_value'] = $old_value;
                $item['new_value'] = max(0, intval($new_value));
                $item['change'] = intval($new_value) - $old_value;
            }

            $preview_items[] = $item;
        }

        // Calculate totals
        $total_products = count($preview_items);
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total_affected = !empty($values)
            ? $wpdb->get_var($wpdb->prepare($total_sql, $values))
            : $wpdb->get_var($total_sql);

        return array(
            'items' => $preview_items,
            'total_affected' => $total_affected,
            'showing' => $total_products,
            'summary' => $parsed['summary']
        );
    }

    /**
     * Calculate new value based on operation
     */
    private function calculate_new_value($old_value, $operation, $value, $value_type) {
        switch ($operation) {
            case 'increase':
                if ($value_type === 'percent') {
                    return $old_value * (1 + $value / 100);
                }
                return $old_value + $value;

            case 'decrease':
                if ($value_type === 'percent') {
                    return $old_value * (1 - $value / 100);
                }
                return $old_value - $value;

            case 'set':
                return $value;

            case 'multiply':
                return $old_value * $value;

            default:
                return $old_value;
        }
    }

    /**
     * Apply confirmed changes
     */
    public function apply_changes($parsed, $sync_to_stores = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_products';

        // Rebuild where clause
        $where = array('1=1');
        $values = array();
        $target = $parsed['target'];

        switch ($target['type'] ?? 'all') {
            case 'category':
                if (!empty($target['category_name'])) {
                    $cat_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}wcpmp_categories WHERE name_uk LIKE %s OR name_ru LIKE %s",
                        '%' . $wpdb->esc_like($target['category_name']) . '%',
                        '%' . $wpdb->esc_like($target['category_name']) . '%'
                    ));
                    if ($cat_id) {
                        $where[] = 'category_id = %d';
                        $values[] = $cat_id;
                    }
                }
                break;

            case 'price_range':
                if (isset($target['min_price'])) {
                    $where[] = 'price >= %f';
                    $values[] = $target['min_price'];
                }
                if (isset($target['max_price'])) {
                    $where[] = 'price <= %f';
                    $values[] = $target['max_price'];
                }
                break;

            case 'stock_range':
                if (isset($target['min_stock'])) {
                    $where[] = 'stock_quantity >= %d';
                    $values[] = $target['min_stock'];
                }
                if (isset($target['max_stock'])) {
                    $where[] = 'stock_quantity <= %d';
                    $values[] = $target['max_stock'];
                }
                break;

            case 'sku_list':
                if (!empty($target['skus'])) {
                    $placeholders = implode(',', array_fill(0, count($target['skus']), '%s'));
                    $where[] = "sku IN ($placeholders)";
                    $values = array_merge($values, $target['skus']);
                }
                break;
        }

        $where_sql = implode(' AND ', $where);

        // Get products to update
        $sql = "SELECT id, price, stock_quantity FROM $table WHERE $where_sql";
        $products = !empty($values)
            ? $wpdb->get_results($wpdb->prepare($sql, $values))
            : $wpdb->get_results($sql);

        $updated = 0;
        $action = $parsed['action'];
        $operation = $parsed['operation'];
        $value = floatval($parsed['value']);
        $value_type = $parsed['value_type'];

        $store_manager = new WCPMP_Store_Manager();

        foreach ($products as $product) {
            if (strpos($action, 'price') !== false) {
                $old_value = floatval($product->price);
                $new_value = round($this->calculate_new_value($old_value, $operation, $value, $value_type), 2);

                $wpdb->update($table, array('price' => $new_value), array('id' => $product->id));

                // Sync to stores
                if ($sync_to_stores) {
                    $store_manager->update_prices($product->id, $this->get_all_store_prices($product->id, $new_value));
                }
            } else {
                $old_value = intval($product->stock_quantity);
                $new_value = max(0, intval($this->calculate_new_value($old_value, $operation, $value, $value_type)));

                $wpdb->update($table, array(
                    'stock_quantity' => $new_value,
                    'stock_status' => $new_value > 0 ? 'instock' : 'outofstock'
                ), array('id' => $product->id));

                // Sync to stores
                if ($sync_to_stores) {
                    $store_manager->update_stock($product->id, $this->get_all_store_stock($product->id, $new_value));
                }
            }

            $updated++;
        }

        return array(
            'updated' => $updated,
            'synced_to_stores' => $sync_to_stores
        );
    }

    /**
     * Get all stores for product price update
     */
    private function get_all_store_prices($product_id, $new_price) {
        global $wpdb;

        $stores = $wpdb->get_col($wpdb->prepare(
            "SELECT store_id FROM {$wpdb->prefix}wcpmp_product_stores WHERE product_id = %d",
            $product_id
        ));

        $prices = array();
        foreach ($stores as $store_id) {
            $prices[$store_id] = array('price' => $new_price);
        }

        return $prices;
    }

    /**
     * Get all stores for product stock update
     */
    private function get_all_store_stock($product_id, $new_stock) {
        global $wpdb;

        $stores = $wpdb->get_col($wpdb->prepare(
            "SELECT store_id FROM {$wpdb->prefix}wcpmp_product_stores WHERE product_id = %d",
            $product_id
        ));

        $stock = array();
        foreach ($stores as $store_id) {
            $stock[$store_id] = $new_stock;
        }

        return $stock;
    }

    /**
     * Call OpenAI API
     */
    private function call_openai($prompt) {
        $api_key = get_option('wcpmp_openai_api_key');
        $model = get_option('wcpmp_openai_model', 'gpt-4o');

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.1,
                'max_tokens' => 1000,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('api_error', $body['error']['message'] ?? 'API Error');
        }

        return $body['choices'][0]['message']['content'];
    }

    /**
     * AJAX handler for processing command
     */
    public function ajax_process_command() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $command = sanitize_textarea_field($_POST['command']);
        $language = sanitize_text_field($_POST['language'] ?? 'uk');

        if (empty($command)) {
            wp_send_json_error(__('Command is required', 'wc-product-manager-pro'));
        }

        $result = $this->process_command($command, $language);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for applying changes
     */
    public function ajax_apply_changes() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $command_data = json_decode(stripslashes($_POST['command_data']), true);
        $sync_to_stores = !empty($_POST['sync_to_stores']);

        if (empty($command_data)) {
            wp_send_json_error(__('Invalid command data', 'wc-product-manager-pro'));
        }

        $result = $this->apply_changes($command_data, $sync_to_stores);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Updated %d products', 'wc-product-manager-pro'),
                $result['updated']
            ),
            'result' => $result
        ));
    }

    /**
     * Get example commands
     */
    public function get_example_commands($language = 'uk') {
        if ($language === 'uk') {
            return array(
                'Знизити ціни на всі товари на 10%',
                'Підвищити ціни на категорію Електроніка на 15%',
                'Встановити наявність 0 для товарів з ціною нижче 50 грн',
                'Зменшити залишки на 5 одиниць для товарів з низьким запасом (менше 10)',
                'Підняти ціни на 20% для товарів з артикулами SKU001, SKU002, SKU003',
                'Зменшити ціну на 100 грн для всіх товарів дорожче 1000 грн'
            );
        }

        return array(
            'Снизить цены на все товары на 10%',
            'Повысить цены на категорию Электроника на 15%',
            'Установить наличие 0 для товаров с ценой ниже 50 грн',
            'Уменьшить остатки на 5 единиц для товаров с низким запасом (меньше 10)',
            'Поднять цены на 20% для товаров с артикулами SKU001, SKU002, SKU003',
            'Уменьшить цену на 100 грн для всех товаров дороже 1000 грн'
        );
    }
}
