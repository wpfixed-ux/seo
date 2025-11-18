<?php
/**
 * Prom.ua Marketplace API Integration
 * Documentation: https://my.prom.ua/api/docs
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Prom_UA {

    private $api_base = 'https://my.prom.ua/api/v1';

    /**
     * Get store API credentials
     */
    private function get_store_credentials($store_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_stores';
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND type = 'prom_ua'",
            $store_id
        ));

        if (!$store) {
            return new WP_Error('store_not_found', __('Prom.ua store not found', 'wc-product-manager-pro'));
        }

        return array(
            'api_key' => $store->api_key,
            'settings' => json_decode($store->settings, true)
        );
    }

    /**
     * Make API request to Prom.ua
     */
    private function api_request($store_id, $endpoint, $method = 'GET', $data = null) {
        $credentials = $this->get_store_credentials($store_id);

        if (is_wp_error($credentials)) {
            return $credentials;
        }

        $url = $this->api_base . $endpoint;

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $credentials['api_key'],
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
            $error_message = isset($body['error']) ? $body['error'] : 'API Error';
            return new WP_Error('api_error', $error_message);
        }

        return $body;
    }

    /**
     * Get products from Prom.ua store
     */
    public function get_products($store_id, $params = array()) {
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
        );
        $params = array_merge($defaults, $params);

        $query_string = http_build_query($params);
        return $this->api_request($store_id, '/products/list?' . $query_string);
    }

    /**
     * Get single product
     */
    public function get_product($store_id, $product_id) {
        return $this->api_request($store_id, '/products/' . $product_id);
    }

    /**
     * Create/publish product to Prom.ua
     */
    public function create_product($store_id, $product_data) {
        return $this->api_request($store_id, '/products/import', 'POST', array(
            'products' => array($product_data)
        ));
    }

    /**
     * Update product on Prom.ua
     */
    public function update_product($store_id, $product_id, $product_data) {
        $product_data['id'] = $product_id;
        return $this->api_request($store_id, '/products/edit', 'POST', array(
            'products' => array($product_data)
        ));
    }

    /**
     * Update product stock
     */
    public function update_stock($store_id, $products) {
        // Format: [['id' => 123, 'presence' => 'available', 'quantity_in_stock' => 10], ...]
        return $this->api_request($store_id, '/products/edit', 'POST', array(
            'products' => $products
        ));
    }

    /**
     * Update product prices
     */
    public function update_prices($store_id, $products) {
        // Format: [['id' => 123, 'price' => 1000, 'discount' => ['value' => 900]], ...]
        return $this->api_request($store_id, '/products/edit', 'POST', array(
            'products' => $products
        ));
    }

    /**
     * Get orders from Prom.ua
     */
    public function get_orders($store_id, $params = array()) {
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'status' => 'pending,accepted,delivered',
        );
        $params = array_merge($defaults, $params);

        $query_string = http_build_query($params);
        return $this->api_request($store_id, '/orders/list?' . $query_string);
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
    public function update_order_status($store_id, $order_id, $status, $comment = '') {
        $data = array(
            'status' => $status,
        );

        if ($comment) {
            $data['seller_comment'] = $comment;
        }

        return $this->api_request($store_id, '/orders/' . $order_id, 'PUT', $data);
    }

    /**
     * Get product categories from Prom.ua
     */
    public function get_categories($store_id) {
        return $this->api_request($store_id, '/groups/list');
    }

    /**
     * Prepare product data for Prom.ua API
     */
    public function prepare_product_data($product, $store_id, $language = 'uk') {
        global $wpdb;

        // Get store settings for category mapping
        $credentials = $this->get_store_credentials($store_id);
        $settings = is_array($credentials) ? $credentials['settings'] : array();

        $name = $language === 'uk' ? $product->name_uk : ($product->name_ru ?: $product->name_uk);
        $description = $language === 'uk' ? $product->description_uk : ($product->description_ru ?: $product->description_uk);

        $data = array(
            'external_id' => $product->sku,
            'name' => $name,
            'description' => $description,
            'price' => floatval($product->price),
            'currency' => 'UAH',
            'presence' => $product->stock_quantity > 0 ? 'available' : 'not_available',
            'quantity_in_stock' => intval($product->stock_quantity),
        );

        // Add sale price if exists
        if ($product->sale_price && $product->sale_price < $product->price) {
            $data['discount'] = array(
                'value' => floatval($product->sale_price),
                'type' => 'amount'
            );
        }

        // Add images
        if ($product->images) {
            $images = json_decode($product->images, true);
            if (!empty($images)) {
                $data['images'] = array();
                foreach ($images as $image_id) {
                    $url = wp_get_attachment_url($image_id);
                    if ($url) {
                        $data['images'][] = array('url' => $url);
                    }
                }
            }
        }

        // Add category mapping
        if ($product->category_id) {
            $category = $wpdb->get_row($wpdb->prepare(
                "SELECT prom_category_id FROM {$wpdb->prefix}wcpmp_categories WHERE id = %d",
                $product->category_id
            ));
            if ($category && $category->prom_category_id) {
                $data['group_id'] = $category->prom_category_id;
            }
        }

        // Add attributes if available
        if ($product->attributes) {
            $attributes = json_decode($product->attributes, true);
            if (!empty($attributes)) {
                $data['attributes'] = array();
                foreach ($attributes as $key => $value) {
                    $data['attributes'][] = array(
                        'name' => $key,
                        'value' => $value
                    );
                }
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

            // Update mapping
            $external_id = isset($result['ids'][0]) ? $result['ids'][0] : null;

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
     * Sync orders from Prom.ua stores
     */
    public function sync_orders($store_id, $since = null) {
        $params = array('limit' => 100);

        if ($since) {
            $params['date_from'] = $since;
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

        foreach ($result['orders'] ?? array() as $order) {
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
                'total' => $order['price'],
                'currency' => 'UAH',
                'customer_name' => $order['client_name'] ?? '',
                'customer_email' => $order['email'] ?? '',
                'customer_phone' => $order['phone'] ?? '',
                'shipping_address' => json_encode($order['delivery_address'] ?? array()),
                'order_date' => $order['date_created'],
                'items' => json_encode($order['products'] ?? array()),
                'notes' => $order['client_notes'] ?? ''
            ));

            $order_id = $wpdb->insert_id;

            // Insert order items
            foreach ($order['products'] ?? array() as $item) {
                $wpdb->insert($items_table, array(
                    'order_id' => $order_id,
                    'product_id' => $this->find_product_by_external_id($item['external_id'] ?? ''),
                    'sku' => $item['external_id'] ?? '',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity']
                ));
            }

            // Update/create customer
            if (!empty($order['email'])) {
                $this->update_customer_from_order($order);
            }

            $imported++;
        }

        return $imported;
    }

    /**
     * Find product by external ID
     */
    private function find_product_by_external_id($external_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}wcpmp_product_stores WHERE external_id = %s",
            $external_id
        )) ?: 0;
    }

    /**
     * Update customer from order data
     */
    private function update_customer_from_order($order) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_customers';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $order['email']
        ));

        if ($existing) {
            $wpdb->update($table, array(
                'total_orders' => $existing->total_orders + 1,
                'total_spent' => $existing->total_spent + $order['price'],
                'average_order' => ($existing->total_spent + $order['price']) / ($existing->total_orders + 1),
                'last_order_date' => $order['date_created'],
                'phone' => $order['phone'] ?? $existing->phone
            ), array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array(
                'email' => $order['email'],
                'first_name' => $order['client_first_name'] ?? '',
                'last_name' => $order['client_last_name'] ?? '',
                'phone' => $order['phone'] ?? '',
                'total_orders' => 1,
                'total_spent' => $order['price'],
                'average_order' => $order['price'],
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
            'service' => 'prom_ua_' . $store_id,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => json_encode($request),
            'response_data' => is_string($response) ? $response : json_encode($response),
            'status_code' => $status_code,
            'created_at' => current_time('mysql')
        ));
    }
}
