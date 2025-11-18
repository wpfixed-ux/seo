<?php
/**
 * Store Manager - unified interface for all stores
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Store_Manager {

    private $prom_ua;
    private $woo_api;

    public function __construct() {
        $this->prom_ua = new WCPMP_Prom_UA();
        $this->woo_api = new WCPMP_WooCommerce_API();
    }

    /**
     * Get all configured stores
     */
    public function get_stores($type = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_stores';

        $sql = "SELECT * FROM $table WHERE is_active = 1";
        if ($type) {
            $sql .= $wpdb->prepare(" AND type = %s", $type);
        }
        $sql .= " ORDER BY name ASC";

        return $wpdb->get_results($sql);
    }

    /**
     * Get store by ID
     */
    public function get_store($store_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_stores WHERE id = %d",
            $store_id
        ));
    }

    /**
     * Add new store
     */
    public function add_store($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_stores';

        $result = $wpdb->insert($table, array(
            'name' => sanitize_text_field($data['name']),
            'type' => sanitize_text_field($data['type']),
            'api_url' => esc_url_raw($data['api_url']),
            'api_key' => sanitize_text_field($data['api_key']),
            'api_secret' => sanitize_text_field($data['api_secret'] ?? ''),
            'settings' => json_encode($data['settings'] ?? array()),
            'is_active' => isset($data['is_active']) ? 1 : 0
        ));

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add store', 'wc-product-manager-pro'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Update store
     */
    public function update_store($store_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_stores';

        $update_data = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['api_url'])) {
            $update_data['api_url'] = esc_url_raw($data['api_url']);
        }
        if (isset($data['api_key'])) {
            $update_data['api_key'] = sanitize_text_field($data['api_key']);
        }
        if (isset($data['api_secret'])) {
            $update_data['api_secret'] = sanitize_text_field($data['api_secret']);
        }
        if (isset($data['settings'])) {
            $update_data['settings'] = json_encode($data['settings']);
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
        }

        return $wpdb->update($table, $update_data, array('id' => $store_id));
    }

    /**
     * Delete store
     */
    public function delete_store($store_id) {
        global $wpdb;

        // Delete product mappings
        $wpdb->delete($wpdb->prefix . 'wcpmp_product_stores', array('store_id' => $store_id));

        // Delete store
        return $wpdb->delete($wpdb->prefix . 'wcpmp_stores', array('id' => $store_id));
    }

    /**
     * Test store connection
     */
    public function test_connection($store_id) {
        $store = $this->get_store($store_id);

        if (!$store) {
            return new WP_Error('store_not_found', __('Store not found', 'wc-product-manager-pro'));
        }

        if ($store->type === 'prom_ua') {
            $result = $this->prom_ua->get_products($store_id, array('limit' => 1));
        } else {
            $result = $this->woo_api->get_products($store_id, array('per_page' => 1));
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Update prices across stores
     */
    public function update_prices($product_id, $prices) {
        global $wpdb;
        $mappings_table = $wpdb->prefix . 'wcpmp_product_stores';

        $results = array();
        $errors = array();

        foreach ($prices as $store_id => $price_data) {
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $mappings_table WHERE product_id = %d AND store_id = %d",
                $product_id, $store_id
            ));

            if (!$mapping || !$mapping->external_id) {
                continue;
            }

            $store = $this->get_store($store_id);

            if ($store->type === 'prom_ua') {
                $update_data = array(
                    'id' => $mapping->external_id,
                    'price' => $price_data['price']
                );
                if (isset($price_data['sale_price'])) {
                    $update_data['discount'] = array(
                        'value' => $price_data['sale_price'],
                        'type' => 'amount'
                    );
                }
                $result = $this->prom_ua->update_prices($store_id, array($update_data));
            } else {
                $update_data = array(
                    'id' => $mapping->external_id,
                    'regular_price' => strval($price_data['price'])
                );
                if (isset($price_data['sale_price'])) {
                    $update_data['sale_price'] = strval($price_data['sale_price']);
                }
                $result = $this->woo_api->batch_update($store_id, array($update_data));
            }

            if (is_wp_error($result)) {
                $errors[$store_id] = $result->get_error_message();
            } else {
                // Update local mapping
                $wpdb->update($mappings_table, array(
                    'price' => $price_data['price'],
                    'sale_price' => $price_data['sale_price'] ?? null,
                    'last_synced' => current_time('mysql')
                ), array('id' => $mapping->id));

                $results[$store_id] = true;
            }
        }

        return array('success' => $results, 'errors' => $errors);
    }

    /**
     * Update stock across stores
     */
    public function update_stock($product_id, $stock_data) {
        global $wpdb;
        $mappings_table = $wpdb->prefix . 'wcpmp_product_stores';

        $results = array();
        $errors = array();

        foreach ($stock_data as $store_id => $quantity) {
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $mappings_table WHERE product_id = %d AND store_id = %d",
                $product_id, $store_id
            ));

            if (!$mapping || !$mapping->external_id) {
                continue;
            }

            $store = $this->get_store($store_id);

            if ($store->type === 'prom_ua') {
                $update_data = array(
                    'id' => $mapping->external_id,
                    'quantity_in_stock' => $quantity,
                    'presence' => $quantity > 0 ? 'available' : 'not_available'
                );
                $result = $this->prom_ua->update_stock($store_id, array($update_data));
            } else {
                $update_data = array(
                    'id' => $mapping->external_id,
                    'stock_quantity' => $quantity,
                    'stock_status' => $quantity > 0 ? 'instock' : 'outofstock'
                );
                $result = $this->woo_api->batch_update($store_id, array($update_data));
            }

            if (is_wp_error($result)) {
                $errors[$store_id] = $result->get_error_message();
            } else {
                // Update local mapping
                $wpdb->update($mappings_table, array(
                    'stock_quantity' => $quantity,
                    'last_synced' => current_time('mysql')
                ), array('id' => $mapping->id));

                $results[$store_id] = true;
            }
        }

        return array('success' => $results, 'errors' => $errors);
    }

    /**
     * Get product availability across stores
     */
    public function get_product_availability($product_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT ps.*, s.name as store_name, s.type as store_type
            FROM {$wpdb->prefix}wcpmp_product_stores ps
            JOIN {$wpdb->prefix}wcpmp_stores s ON ps.store_id = s.id
            WHERE ps.product_id = %d
            ORDER BY s.name
        ", $product_id));
    }
}
