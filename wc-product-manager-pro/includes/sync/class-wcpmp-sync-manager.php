<?php
/**
 * Sync Manager - handles inventory synchronization
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Sync_Manager {

    private $store_manager;

    public function __construct() {
        $this->store_manager = new WCPMP_Store_Manager();
    }

    /**
     * Run scheduled sync
     */
    public function run_scheduled_sync() {
        if (!get_option('wcpmp_global_sync_enabled', 1)) {
            return;
        }

        global $wpdb;
        $stores = $this->store_manager->get_stores();

        foreach ($stores as $store) {
            $this->sync_store($store->id);
        }

        // Update last sync time
        update_option('wcpmp_last_sync', current_time('mysql'));
    }

    /**
     * Sync specific store
     */
    public function sync_store($store_id) {
        global $wpdb;

        $store = $this->store_manager->get_store($store_id);
        if (!$store || !$store->is_active) {
            return new WP_Error('store_inactive', __('Store is not active', 'wc-product-manager-pro'));
        }

        // Log sync start
        $log_table = $wpdb->prefix . 'wcpmp_sync_logs';
        $wpdb->insert($log_table, array(
            'store_id' => $store_id,
            'sync_type' => 'inventory',
            'status' => 'running',
            'started_at' => current_time('mysql')
        ));
        $log_id = $wpdb->insert_id;

        $processed = 0;
        $failed = 0;
        $errors = array();

        // Get products that need syncing
        $products = $this->get_products_to_sync($store_id);

        foreach ($products as $product) {
            // Check category sync settings
            if (!$this->should_sync_product($product)) {
                continue;
            }

            $result = $this->sync_product_to_store($product->id, $store_id);

            if (is_wp_error($result)) {
                $failed++;
                $errors[] = sprintf('Product %d: %s', $product->id, $result->get_error_message());
            } else {
                $processed++;
            }
        }

        // Update log
        $wpdb->update($log_table, array(
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'items_processed' => $processed,
            'items_failed' => $failed,
            'error_log' => !empty($errors) ? json_encode($errors) : null,
            'completed_at' => current_time('mysql')
        ), array('id' => $log_id));

        // Update store last sync
        $wpdb->update($wpdb->prefix . 'wcpmp_stores', array(
            'last_sync' => current_time('mysql')
        ), array('id' => $store_id));

        return array(
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors
        );
    }

    /**
     * Get products that need syncing for a store
     */
    private function get_products_to_sync($store_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, ps.external_id, ps.is_published
            FROM {$wpdb->prefix}wcpmp_products p
            LEFT JOIN {$wpdb->prefix}wcpmp_product_stores ps ON p.id = ps.product_id AND ps.store_id = %d
            WHERE p.sync_enabled = 1
            AND (ps.sync_enabled = 1 OR ps.id IS NULL)
            ORDER BY p.id
        ", $store_id));
    }

    /**
     * Check if product should be synced based on category settings
     */
    private function should_sync_product($product) {
        if (!$product->category_id) {
            return true;
        }

        global $wpdb;
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_categories WHERE id = %d",
            $product->category_id
        ));

        return $category && $category->sync_enabled;
    }

    /**
     * Sync single product to store
     */
    public function sync_product_to_store($product_id, $store_id) {
        global $wpdb;

        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_products WHERE id = %d",
            $product_id
        ));

        if (!$product) {
            return new WP_Error('product_not_found', __('Product not found', 'wc-product-manager-pro'));
        }

        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_product_stores WHERE product_id = %d AND store_id = %d",
            $product_id, $store_id
        ));

        if (!$mapping || !$mapping->external_id) {
            return new WP_Error('not_published', __('Product not published to this store', 'wc-product-manager-pro'));
        }

        $store = $this->store_manager->get_store($store_id);

        // Determine sync mode from category
        $sync_mode = $this->get_category_sync_mode($product->category_id);

        // Build update data
        $update = array(
            $store_id => $product->stock_quantity
        );

        // Sync stock
        $result = $this->store_manager->update_stock($product_id, $update);

        if (!empty($result['errors'])) {
            return new WP_Error('sync_error', reset($result['errors']));
        }

        return true;
    }

    /**
     * Get category sync mode (async/sync)
     */
    private function get_category_sync_mode($category_id) {
        if (!$category_id) {
            return 'async';
        }

        global $wpdb;
        $mode = $wpdb->get_var($wpdb->prepare(
            "SELECT sync_mode FROM {$wpdb->prefix}wcpmp_categories WHERE id = %d",
            $category_id
        ));

        return $mode ?: 'async';
    }

    /**
     * AJAX handler for manual sync
     */
    public function ajax_sync_inventory() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $store_id = intval($_POST['store_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);

        if ($product_id && $store_id) {
            // Sync single product
            $result = $this->sync_product_to_store($product_id, $store_id);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(__('Product synced successfully', 'wc-product-manager-pro'));
        } elseif ($store_id) {
            // Sync entire store
            $result = $this->sync_store($store_id);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Synced %d products, %d failed', 'wc-product-manager-pro'),
                    $result['processed'],
                    $result['failed']
                ),
                'details' => $result
            ));
        } else {
            // Sync all stores
            $stores = $this->store_manager->get_stores();
            $total_processed = 0;
            $total_failed = 0;

            foreach ($stores as $store) {
                $result = $this->sync_store($store->id);
                if (!is_wp_error($result)) {
                    $total_processed += $result['processed'];
                    $total_failed += $result['failed'];
                }
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Synced %d products across all stores, %d failed', 'wc-product-manager-pro'),
                    $total_processed,
                    $total_failed
                )
            ));
        }
    }

    /**
     * AJAX handler for bulk sync
     */
    public function ajax_bulk_sync() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_ids = array_map('intval', $_POST['product_ids'] ?? array());

        if (empty($product_ids)) {
            wp_send_json_error(__('No products selected', 'wc-product-manager-pro'));
        }

        $stores = $this->store_manager->get_stores();
        $synced = 0;
        $failed = 0;

        foreach ($product_ids as $product_id) {
            foreach ($stores as $store) {
                $result = $this->sync_product_to_store($product_id, $store->id);
                if (is_wp_error($result)) {
                    $failed++;
                } else {
                    $synced++;
                }
            }
        }

        wp_send_json_success(array(
            'synced' => $synced,
            'failed' => $failed
        ));
    }

    /**
     * Get sync status for dashboard
     */
    public function get_sync_status() {
        global $wpdb;

        $stats = array();

        // Last sync time
        $stats['last_sync'] = get_option('wcpmp_last_sync', __('Never', 'wc-product-manager-pro'));

        // Products needing sync
        $stats['pending_sync'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products p
            LEFT JOIN {$wpdb->prefix}wcpmp_product_stores ps ON p.id = ps.product_id
            WHERE p.sync_enabled = 1
            AND (ps.last_synced IS NULL OR ps.last_synced < p.updated_at)
        ");

        // Recent sync logs
        $stats['recent_logs'] = $wpdb->get_results("
            SELECT sl.*, s.name as store_name
            FROM {$wpdb->prefix}wcpmp_sync_logs sl
            JOIN {$wpdb->prefix}wcpmp_stores s ON sl.store_id = s.id
            ORDER BY sl.started_at DESC
            LIMIT 10
        ");

        return $stats;
    }
}
