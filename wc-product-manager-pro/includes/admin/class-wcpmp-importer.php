<?php
/**
 * Product Importer - handles all import methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Importer {

    /**
     * Import products from local WooCommerce
     */
    public function import_from_woocommerce($args = array()) {
        $defaults = array(
            'limit' => -1,
            'status' => 'publish',
            'category' => '',
            'update_existing' => false
        );
        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'post_type' => 'product',
            'post_status' => $args['status'],
            'posts_per_page' => $args['limit'],
            'fields' => 'ids'
        );

        if ($args['category']) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $args['category']
                )
            );
        }

        $product_ids = get_posts($query_args);

        if (empty($product_ids)) {
            return array('imported' => 0, 'updated' => 0, 'skipped' => 0);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_products';

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($product_ids as $product_id) {
            $wc_product = wc_get_product($product_id);
            if (!$wc_product) {
                $skipped++;
                continue;
            }

            $sku = $wc_product->get_sku();
            if (empty($sku)) {
                $sku = 'WC-' . $product_id;
            }

            // Check if already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE sku = %s OR wc_product_id = %d",
                $sku, $product_id
            ));

            // Get product data
            $name = $wc_product->get_name();
            $description = $wc_product->get_description();
            $short_description = $wc_product->get_short_description();

            // Get images
            $images = array();
            $image_id = $wc_product->get_image_id();
            if ($image_id) {
                $images[] = $image_id;
            }
            $gallery_ids = $wc_product->get_gallery_image_ids();
            $images = array_merge($images, $gallery_ids);

            // Get attributes
            $attributes = array();
            foreach ($wc_product->get_attributes() as $attr_name => $attr) {
                if ($attr->is_taxonomy()) {
                    $terms = wc_get_product_terms($product_id, $attr->get_name(), array('fields' => 'names'));
                    $attributes[$attr->get_name()] = implode(', ', $terms);
                } else {
                    $attributes[$attr->get_name()] = implode(', ', $attr->get_options());
                }
            }

            // Get category
            $category_id = null;
            $categories = $wc_product->get_category_ids();
            if (!empty($categories)) {
                $category_id = $this->get_or_create_category($categories[0]);
            }

            $product_data = array(
                'wc_product_id' => $product_id,
                'sku' => $sku,
                'name_uk' => $name,
                'description_uk' => $description,
                'seo_description_uk' => $short_description,
                'price' => $wc_product->get_regular_price() ?: 0,
                'sale_price' => $wc_product->get_sale_price() ?: null,
                'stock_quantity' => $wc_product->get_stock_quantity() ?: 0,
                'stock_status' => $wc_product->get_stock_status(),
                'category_id' => $category_id,
                'images' => json_encode($images),
                'attributes' => json_encode($attributes)
            );

            if ($existing && $args['update_existing']) {
                $wpdb->update($table, $product_data, array('id' => $existing));
                $updated++;
            } elseif (!$existing) {
                $wpdb->insert($table, $product_data);
                $imported++;
            } else {
                $skipped++;
            }
        }

        return array(
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped
        );
    }

    /**
     * Import products from CSV file
     */
    public function import_from_csv($file_path, $args = array()) {
        $defaults = array(
            'delimiter' => ',',
            'has_header' => true,
            'update_existing' => false,
            'mapping' => array()
        );
        $args = wp_parse_args($args, $defaults);

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('CSV file not found', 'wc-product-manager-pro'));
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', __('Could not open file', 'wc-product-manager-pro'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_products';

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();
        $row_num = 0;

        // Default column mapping
        $default_mapping = array(
            'sku' => 0,
            'name_uk' => 1,
            'name_ru' => 2,
            'description_uk' => 3,
            'description_ru' => 4,
            'price' => 5,
            'sale_price' => 6,
            'stock_quantity' => 7,
            'category' => 8
        );
        $mapping = !empty($args['mapping']) ? $args['mapping'] : $default_mapping;

        // Skip header
        if ($args['has_header']) {
            fgetcsv($handle, 0, $args['delimiter']);
        }

        while (($data = fgetcsv($handle, 0, $args['delimiter'])) !== false) {
            $row_num++;

            $sku = isset($data[$mapping['sku']]) ? trim($data[$mapping['sku']]) : '';
            if (empty($sku)) {
                $errors[] = sprintf(__('Row %d: SKU is required', 'wc-product-manager-pro'), $row_num);
                $skipped++;
                continue;
            }

            // Check existing
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE sku = %s",
                $sku
            ));

            $product_data = array(
                'sku' => $sku,
                'name_uk' => isset($data[$mapping['name_uk']]) ? trim($data[$mapping['name_uk']]) : '',
                'name_ru' => isset($data[$mapping['name_ru']]) ? trim($data[$mapping['name_ru']]) : '',
                'description_uk' => isset($data[$mapping['description_uk']]) ? trim($data[$mapping['description_uk']]) : '',
                'description_ru' => isset($data[$mapping['description_ru']]) ? trim($data[$mapping['description_ru']]) : '',
                'price' => isset($data[$mapping['price']]) ? floatval($data[$mapping['price']]) : 0,
                'sale_price' => isset($data[$mapping['sale_price']]) && $data[$mapping['sale_price']] ? floatval($data[$mapping['sale_price']]) : null,
                'stock_quantity' => isset($data[$mapping['stock_quantity']]) ? intval($data[$mapping['stock_quantity']]) : 0,
                'stock_status' => (isset($data[$mapping['stock_quantity']]) && intval($data[$mapping['stock_quantity']]) > 0) ? 'instock' : 'outofstock'
            );

            // Handle category
            if (isset($mapping['category']) && isset($data[$mapping['category']]) && $data[$mapping['category']]) {
                $product_data['category_id'] = $this->get_or_create_category_by_name(trim($data[$mapping['category']]));
            }

            if ($existing && $args['update_existing']) {
                $wpdb->update($table, $product_data, array('id' => $existing));
                $updated++;
            } elseif (!$existing) {
                $wpdb->insert($table, $product_data);
                $imported++;
            } else {
                $skipped++;
            }
        }

        fclose($handle);

        return array(
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        );
    }

    /**
     * Import products from connected store API
     */
    public function import_from_store($store_id, $args = array()) {
        global $wpdb;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_Error('store_not_found', __('Store not found', 'wc-product-manager-pro'));
        }

        $defaults = array(
            'limit' => 100,
            'update_existing' => false
        );
        $args = wp_parse_args($args, $defaults);

        $products = array();

        // Fetch products based on store type
        if ($store->type === 'prom_ua') {
            $products = $this->fetch_prom_products($store_id, $args['limit']);
        } else {
            $products = $this->fetch_woo_products($store_id, $args['limit']);
        }

        if (is_wp_error($products)) {
            return $products;
        }

        $table = $wpdb->prefix . 'wcpmp_products';
        $mapping_table = $wpdb->prefix . 'wcpmp_product_stores';

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $sku = $product['sku'];
            if (empty($sku)) {
                $skipped++;
                continue;
            }

            // Check existing
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE sku = %s",
                $sku
            ));

            $product_data = array(
                'sku' => $sku,
                'name_uk' => $product['name'],
                'description_uk' => $product['description'] ?? '',
                'price' => floatval($product['price']),
                'sale_price' => isset($product['sale_price']) ? floatval($product['sale_price']) : null,
                'stock_quantity' => intval($product['stock'] ?? 0),
                'stock_status' => ($product['stock'] ?? 0) > 0 ? 'instock' : 'outofstock'
            );

            if ($existing && $args['update_existing']) {
                $wpdb->update($table, $product_data, array('id' => $existing));
                $product_id = $existing;
                $updated++;
            } elseif (!$existing) {
                $wpdb->insert($table, $product_data);
                $product_id = $wpdb->insert_id;
                $imported++;
            } else {
                $product_id = $existing;
                $skipped++;
            }

            // Create store mapping
            $existing_mapping = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $mapping_table WHERE product_id = %d AND store_id = %d",
                $product_id, $store_id
            ));

            if (!$existing_mapping) {
                $wpdb->insert($mapping_table, array(
                    'product_id' => $product_id,
                    'store_id' => $store_id,
                    'external_id' => $product['external_id'],
                    'price' => $product['price'],
                    'sale_price' => $product['sale_price'] ?? null,
                    'stock_quantity' => $product['stock'] ?? 0,
                    'is_published' => 1,
                    'last_synced' => current_time('mysql')
                ));
            }
        }

        return array(
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped
        );
    }

    /**
     * Fetch products from Prom.ua
     */
    private function fetch_prom_products($store_id, $limit) {
        $prom = new WCPMP_Prom_UA();
        $result = $prom->get_products($store_id, array('limit' => $limit));

        if (is_wp_error($result)) {
            return $result;
        }

        $products = array();
        foreach ($result['products'] ?? array() as $item) {
            $products[] = array(
                'external_id' => $item['id'],
                'sku' => $item['external_id'] ?? $item['sku'] ?? 'PROM-' . $item['id'],
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'price' => $item['price'],
                'sale_price' => isset($item['discount']['value']) ? $item['discount']['value'] : null,
                'stock' => $item['quantity_in_stock'] ?? 0
            );
        }

        return $products;
    }

    /**
     * Fetch products from WooCommerce store
     */
    private function fetch_woo_products($store_id, $limit) {
        $woo = new WCPMP_WooCommerce_API();
        $result = $woo->get_products($store_id, array('per_page' => $limit));

        if (is_wp_error($result)) {
            return $result;
        }

        $products = array();
        foreach ($result as $item) {
            $products[] = array(
                'external_id' => $item['id'],
                'sku' => $item['sku'] ?: 'WOO-' . $item['id'],
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'price' => floatval($item['regular_price']),
                'sale_price' => $item['sale_price'] ? floatval($item['sale_price']) : null,
                'stock' => $item['stock_quantity'] ?? 0
            );
        }

        return $products;
    }

    /**
     * Get or create category from WC term
     */
    private function get_or_create_category($wc_term_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_categories';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE wc_term_id = %d",
            $wc_term_id
        ));

        if ($existing) {
            return $existing;
        }

        $term = get_term($wc_term_id, 'product_cat');
        if (!$term || is_wp_error($term)) {
            return null;
        }

        $wpdb->insert($table, array(
            'wc_term_id' => $wc_term_id,
            'name_uk' => $term->name,
            'parent_id' => $term->parent ? $this->get_or_create_category($term->parent) : null
        ));

        return $wpdb->insert_id;
    }

    /**
     * Get or create category by name
     */
    private function get_or_create_category_by_name($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_categories';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE name_uk = %s",
            $name
        ));

        if ($existing) {
            return $existing;
        }

        $wpdb->insert($table, array(
            'name_uk' => $name
        ));

        return $wpdb->insert_id;
    }

    /**
     * Get sample CSV format
     */
    public function get_csv_template() {
        $headers = array(
            'sku',
            'name_uk',
            'name_ru',
            'description_uk',
            'description_ru',
            'price',
            'sale_price',
            'stock_quantity',
            'category'
        );

        $sample = array(
            'SKU001',
            'Назва товару',
            'Название товара',
            'Опис товару',
            'Описание товара',
            '100.00',
            '90.00',
            '50',
            'Категорія'
        );

        return array(
            'headers' => $headers,
            'sample' => $sample
        );
    }

    /**
     * AJAX handler for WooCommerce import
     */
    public function ajax_import_woocommerce() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $args = array(
            'limit' => intval($_POST['limit'] ?? -1),
            'category' => intval($_POST['category'] ?? 0),
            'update_existing' => !empty($_POST['update_existing'])
        );

        $result = $this->import_from_woocommerce($args);

        wp_send_json_success(array(
            'message' => sprintf(
                __('Imported: %d, Updated: %d, Skipped: %d', 'wc-product-manager-pro'),
                $result['imported'],
                $result['updated'],
                $result['skipped']
            ),
            'result' => $result
        ));
    }

    /**
     * AJAX handler for CSV import
     */
    public function ajax_import_csv() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded', 'wc-product-manager-pro'));
        }

        $file = $_FILES['csv_file'];
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        }

        $args = array(
            'delimiter' => sanitize_text_field($_POST['delimiter'] ?? ','),
            'has_header' => !empty($_POST['has_header']),
            'update_existing' => !empty($_POST['update_existing'])
        );

        $result = $this->import_from_csv($upload['file'], $args);

        // Delete temp file
        @unlink($upload['file']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Imported: %d, Updated: %d, Skipped: %d', 'wc-product-manager-pro'),
                $result['imported'],
                $result['updated'],
                $result['skipped']
            ),
            'result' => $result
        ));
    }

    /**
     * AJAX handler for store import
     */
    public function ajax_import_store() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $store_id = intval($_POST['store_id']);
        if (!$store_id) {
            wp_send_json_error(__('Store ID required', 'wc-product-manager-pro'));
        }

        $args = array(
            'limit' => intval($_POST['limit'] ?? 100),
            'update_existing' => !empty($_POST['update_existing'])
        );

        $result = $this->import_from_store($store_id, $args);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Imported: %d, Updated: %d, Skipped: %d', 'wc-product-manager-pro'),
                $result['imported'],
                $result['updated'],
                $result['skipped']
            ),
            'result' => $result
        ));
    }
}
