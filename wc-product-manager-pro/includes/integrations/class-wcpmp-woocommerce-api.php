<?php
/**
 * WooCommerce REST API Integration for external stores
 * Documentation: https://woocommerce.github.io/woocommerce-rest-api-docs/
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_WooCommerce_API {

    /**
     * Get store API credentials
     */
    private function get_store_credentials($store_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_stores';
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND type = 'woocommerce'",
            $store_id
        ));

        if (!$store) {
            return new WP_Error('store_not_found', __('WooCommerce store not found', 'wc-product-manager-pro'));
        }

        return array(
            'url' => rtrim($store->api_url, '/'),
            'consumer_key' => $store->api_key,
            'consumer_secret' => $store->api_secret,
            'settings' => json_decode($store->settings, true)
        );
    }

    /**
     * Make API request to WooCommerce store
     */
    private function api_request($store_id, $endpoint, $method = 'GET', $data = null) {
        $credentials = $this->get_store_credentials($store_id);

        if (is_wp_error($credentials)) {
            return $credentials;
        }

        $url = $credentials['url'] . '/wp-json/wc/v3' . $endpoint;

        // Add OAuth parameters
        $params = array(
            'consumer_key' => $credentials['consumer_key'],
            'consumer_secret' => $credentials['consumer_secret'],
        );

        if ($method === 'GET' && $data) {
            $params = array_merge($params, $data);
        }

        $url = add_query_arg($params, $url);

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'method' => $method,
        );

        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log_api_call($store_id, $endpoint, $method, $data, $response->get_error_message(), 0);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        $this->log_api_call($store_id, $endpoint, $method, $data, $body, $status_code);

        if ($status_code >= 400) {
            $error_message = isset($body['message']) ? $body['message'] : 'API Error';
            return new WP_Error('api_error', $error_message);
        }

        return $body;
    }

    /**
     * Get products from WooCommerce store
     */
    public function get_products($store_id, $params = array()) {
        $defaults = array(
            'per_page' => 100,
            'page' => 1,
        );
        $params = array_merge($defaults, $params);

        return $this->api_request($store_id, '/products', 'GET', $params);
    }

    /**
     * Get single product
     */
    public function get_product($store_id, $product_id) {
        return $this->api_request($store_id, '/products/' . $product_id);
    }

    /**
     * Create product in WooCommerce store
     */
    public function create_product($store_id, $product_data) {
        return $this->api_request($store_id, '/products', 'POST', $product_data);
    }

    /**
     * Update product in WooCommerce store
     */
    public function update_product($store_id, $product_id, $product_data) {
        return $this->api_request($store_id, '/products/' . $product_id, 'PUT', $product_data);
    }

    /**
     * Delete product
     */
    public function delete_product($store_id, $product_id, $force = false) {
        return $this->api_request($store_id, '/products/' . $product_id, 'DELETE', array('force' => $force));
    }

    /**
     * Batch update products (stock, prices)
     */
    public function batch_update($store_id, $products) {
        return $this->api_request($store_id, '/products/batch', 'POST', array(
            'update' => $products
        ));
    }

    /**
     * Get orders from WooCommerce store
     */
    public function get_orders($store_id, $params = array()) {
        $defaults = array(
            'per_page' => 100,
            'page' => 1,
            'status' => 'processing,completed',
        );
        $params = array_merge($defaults, $params);

        return $this->api_request($store_id, '/orders', 'GET', $params);
    }

    /**
     * Get single order
     */
    public function get_order($store_id, $order_id) {
        return $this->api_request($store_id, '/orders/' . $order_id);
    }

    /**
     * Update order status
     */
    public function update_order($store_id, $order_id, $data) {
        return $this->api_request($store_id, '/orders/' . $order_id, 'PUT', $data);
    }

    /**
     * Get product categories
     */
    public function get_categories($store_id, $params = array()) {
        return $this->api_request($store_id, '/products/categories', 'GET', $params);
    }

    /**
     * Prepare product data for WooCommerce API
     */
    public function prepare_product_data($product, $store_id, $language = 'uk') {
        global $wpdb;

        $name = $language === 'uk' ? $product->name_uk : ($product->name_ru ?: $product->name_uk);
        $description = $language === 'uk' ? $product->description_uk : ($product->description_ru ?: $product->description_uk);
        $seo_description = $language === 'uk' ? $product->seo_description_uk : ($product->seo_description_ru ?: $product->seo_description_uk);

        $data = array(
            'name' => $name,
            'type' => 'simple',
            'status' => 'publish',
            'description' => $description,
            'short_description' => $seo_description ?: '',
            'sku' => $product->sku,
            'regular_price' => strval($product->price),
            'manage_stock' => true,
            'stock_quantity' => intval($product->stock_quantity),
            'stock_status' => $product->stock_quantity > 0 ? 'instock' : 'outofstock',
        );

        // Add sale price
        if ($product->sale_price && $product->sale_price < $product->price) {
            $data['sale_price'] = strval($product->sale_price);
        }

        // Add images
        if ($product->images) {
            $images = json_decode($product->images, true);
            if (!empty($images)) {
                $data['images'] = array();
                foreach ($images as $image_id) {
                    $url = wp_get_attachment_url($image_id);
                    if ($url) {
                        $data['images'][] = array('src' => $url);
                    }
                }
            }
        }

        // Add attributes
        if ($product->attributes) {
            $attributes = json_decode($product->attributes, true);
            if (!empty($attributes)) {
                $data['attributes'] = array();
                $position = 0;
                foreach ($attributes as $key => $value) {
                    $data['attributes'][] = array(
                        'name' => $key,
                        'position' => $position++,
                        'visible' => true,
                        'options' => array($value)
                    );
                }
            }
        }

        // Add category mapping
        if ($product->category_id) {
            $credentials = $this->get_store_credentials($store_id);
            $settings = is_array($credentials) ? ($credentials['settings'] ?? array()) : array();
            $category_mapping = $settings['category_mapping'] ?? array();

            if (isset($category_mapping[$product->category_id])) {
                $data['categories'] = array(
                    array('id' => intval($category_mapping[$product->category_id]))
                );
            }
        }

        return $data;
    }

    /**
     * AJAX handler for publishing product
     */
    public function ajax_publish_product() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_id = intval($_POST['product_id']);
        $store_ids = array_map('intval', $_POST['store_ids'] ?? array());
        $language = sanitize_text_field($_POST['language'] ?? 'uk');

        if (empty($store_ids)) {
            wp_send_json_error(__('No stores selected', 'wc-product-manager-pro'));
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'wcpmp_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table WHERE id = %d", $product_id));

        if (!$product) {
            wp_send_json_error(__('Product not found', 'wc-product-manager-pro'));
        }

        $results = array();
        $errors = array();

        foreach ($store_ids as $store_id) {
            $product_data = $this->prepare_product_data($product, $store_id, $language);

            // Check if already published
            $mapping_table = $wpdb->prefix . 'wcpmp_product_stores';
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $mapping_table WHERE product_id = %d AND store_id = %d",
                $product_id, $store_id
            ));

            if ($existing && $existing->external_id) {
                // Update existing
                $result = $this->update_product($store_id, $existing->external_id, $product_data);
            } else {
                // Create new
                $result = $this->create_product($store_id, $product_data);
            }

            if (is_wp_error($result)) {
                $errors[] = array(
                    'store_id' => $store_id,
                    'error' => $result->get_error_message()
                );
                continue;
            }

            $external_id = $result['id'] ?? null;

            // Update mapping
            if ($existing) {
                $wpdb->update($mapping_table, array(
                    'external_id' => $external_id,
                    'is_published' => 1,
                    'last_synced' => current_time('mysql')
                ), array('id' => $existing->id));
            } else {
                $wpdb->insert($mapping_table, array(
                    'product_id' => $product_id,
                    'store_id' => $store_id,
                    'external_id' => $external_id,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'stock_quantity' => $product->stock_quantity,
                    'is_published' => 1,
                    'last_synced' => current_time('mysql')
                ));
            }

            $results[] = array(
                'store_id' => $store_id,
                'external_id' => $external_id
            );
        }

        wp_send_json_success(array(
            'published' => $results,
            'errors' => $errors
        ));
    }

    /**
     * Sync orders from WooCommerce stores
     */
    public function sync_orders($store_id, $since = null) {
        $params = array('per_page' => 100);

        if ($since) {
            $params['after'] = $since;
        }

        $result = $this->get_orders($store_id, $params);

        if (is_wp_error($result)) {
            return $result;
        }

        global $wpdb;
        $orders_table = $wpdb->prefix . 'wcpmp_orders';
        $items_table = $wpdb->prefix . 'wcpmp_order_items';
        $customers_table = $wpdb->prefix . 'wcpmp_customers';

        $imported = 0;

        foreach ($result as $order) {
            // Check if order already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $orders_table WHERE store_id = %d AND external_order_id = %s",
                $store_id, $order['id']
            ));

            if ($existing) {
                // Update status
                $wpdb->update($orders_table, array(
                    'status' => $order['status']
                ), array('id' => $existing));
                continue;
            }

            // Insert order
            $wpdb->insert($orders_table, array(
                'store_id' => $store_id,
                'external_order_id' => $order['id'],
                'status' => $order['status'],
                'total' => $order['total'],
                'currency' => $order['currency'],
                'customer_name' => trim($order['billing']['first_name'] . ' ' . $order['billing']['last_name']),
                'customer_email' => $order['billing']['email'] ?? '',
                'customer_phone' => $order['billing']['phone'] ?? '',
                'shipping_address' => json_encode($order['shipping']),
                'order_date' => $order['date_created'],
                'items' => json_encode($order['line_items']),
                'notes' => $order['customer_note'] ?? ''
            ));

            $order_id = $wpdb->insert_id;

            // Insert order items
            foreach ($order['line_items'] as $item) {
                $wpdb->insert($items_table, array(
                    'order_id' => $order_id,
                    'product_id' => $this->find_product_by_sku($item['sku'] ?? ''),
                    'sku' => $item['sku'] ?? '',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total']
                ));
            }

            // Update customer
            if (!empty($order['billing']['email'])) {
                $this->update_customer_from_order($order);
            }

            $imported++;
        }

        return $imported;
    }

    /**
     * Find product by SKU
     */
    private function find_product_by_sku($sku) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wcpmp_products WHERE sku = %s",
            $sku
        )) ?: 0;
    }

    /**
     * Update customer from order data
     */
    private function update_customer_from_order($order) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_customers';

        $email = $order['billing']['email'];

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ));

        if ($existing) {
            $wpdb->update($table, array(
                'total_orders' => $existing->total_orders + 1,
                'total_spent' => $existing->total_spent + $order['total'],
                'average_order' => ($existing->total_spent + $order['total']) / ($existing->total_orders + 1),
                'last_order_date' => $order['date_created'],
                'phone' => $order['billing']['phone'] ?? $existing->phone
            ), array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array(
                'email' => $email,
                'first_name' => $order['billing']['first_name'],
                'last_name' => $order['billing']['last_name'],
                'phone' => $order['billing']['phone'] ?? '',
                'total_orders' => 1,
                'total_spent' => $order['total'],
                'average_order' => $order['total'],
                'last_order_date' => $order['date_created']
            ));
        }
    }

    /**
     * Log API call
     */
    private function log_api_call($store_id, $endpoint, $method, $request, $response, $status_code) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_api_logs';

        $wpdb->insert($table, array(
            'service' => 'woocommerce_' . $store_id,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => json_encode($request),
            'response_data' => is_string($response) ? $response : json_encode($response),
            'status_code' => $status_code,
            'created_at' => current_time('mysql')
        ));
    }
}
