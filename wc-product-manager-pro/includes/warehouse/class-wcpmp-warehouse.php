<?php
/**
 * Warehouse Management System
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Warehouse {

    private $tracker;

    public function __construct() {
        $this->tracker = new WCPMP_Inventory_Tracker();
    }

    /**
     * Get warehouse dashboard data
     */
    public function get_dashboard_data() {
        return array(
            'inventory' => $this->tracker->get_inventory_summary(),
            'low_stock' => $this->tracker->get_low_stock_products(),
            'best_sellers' => $this->tracker->get_best_sellers(),
            'recent_orders' => $this->get_recent_orders(10)
        );
    }

    /**
     * Get recent orders
     */
    public function get_recent_orders($limit = 20) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT o.*, s.name as store_name, s.type as store_type
            FROM {$wpdb->prefix}wcpmp_orders o
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            ORDER BY o.order_date DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Get orders for product
     */
    public function get_product_orders($product_id, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT o.*, oi.quantity, oi.price as item_price, s.name as store_name
            FROM {$wpdb->prefix}wcpmp_orders o
            JOIN {$wpdb->prefix}wcpmp_order_items oi ON o.id = oi.order_id
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            WHERE oi.product_id = %d
            ORDER BY o.order_date DESC
            LIMIT %d
        ", $product_id, $limit));
    }

    /**
     * Fetch orders from all stores
     */
    public function fetch_orders_from_stores() {
        global $wpdb;

        $stores = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wcpmp_stores WHERE is_active = 1"
        );

        $prom_ua = new WCPMP_Prom_UA();
        $woo_api = new WCPMP_WooCommerce_API();

        $total_imported = 0;

        foreach ($stores as $store) {
            // Get last sync time
            $last_sync = $store->last_sync ?: date('Y-m-d', strtotime('-7 days'));

            if ($store->type === 'prom_ua') {
                $imported = $prom_ua->sync_orders($store->id, $last_sync);
            } else {
                $imported = $woo_api->sync_orders($store->id, $last_sync);
            }

            if (!is_wp_error($imported)) {
                $total_imported += $imported;
            }
        }

        // Update inventory based on new orders
        $this->update_inventory_from_orders();

        return $total_imported;
    }

    /**
     * Update local inventory based on orders
     */
    public function update_inventory_from_orders() {
        global $wpdb;

        // Get all completed orders that haven't been processed
        $orders = $wpdb->get_results("
            SELECT oi.product_id, SUM(oi.quantity) as sold
            FROM {$wpdb->prefix}wcpmp_order_items oi
            JOIN {$wpdb->prefix}wcpmp_orders o ON oi.order_id = o.id
            WHERE o.status IN ('completed', 'delivered')
            GROUP BY oi.product_id
        ");

        foreach ($orders as $order) {
            if ($order->product_id > 0) {
                // This is informational only - actual stock is managed manually
                // Could implement auto-deduction here if needed
            }
        }
    }

    /**
     * Get sales statistics
     */
    public function get_sales_stats($period = '30days') {
        global $wpdb;

        $date_from = date('Y-m-d', strtotime("-$period"));

        $stats = array();

        // Total orders
        $stats['total_orders'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_orders
            WHERE order_date >= %s AND status IN ('processing', 'completed')
        ", $date_from));

        // Total revenue
        $stats['total_revenue'] = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}wcpmp_orders
            WHERE order_date >= %s AND status IN ('processing', 'completed')
        ", $date_from));

        // Orders by store
        $stats['by_store'] = $wpdb->get_results($wpdb->prepare("
            SELECT s.name, COUNT(o.id) as orders, SUM(o.total) as revenue
            FROM {$wpdb->prefix}wcpmp_orders o
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            WHERE o.order_date >= %s AND o.status IN ('processing', 'completed')
            GROUP BY o.store_id
        ", $date_from));

        // Orders by status
        $stats['by_status'] = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count
            FROM {$wpdb->prefix}wcpmp_orders
            WHERE order_date >= %s
            GROUP BY status
        ", $date_from));

        // Daily sales
        $stats['daily'] = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(order_date) as date, COUNT(*) as orders, SUM(total) as revenue
            FROM {$wpdb->prefix}wcpmp_orders
            WHERE order_date >= %s AND status IN ('processing', 'completed')
            GROUP BY DATE(order_date)
            ORDER BY date DESC
        ", $date_from));

        return $stats;
    }

    /**
     * AJAX handler for getting orders
     */
    public function ajax_get_orders() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        $store_id = intval($_POST['store_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        global $wpdb;

        $where = array('1=1');
        $values = array();

        if ($store_id) {
            $where[] = 'o.store_id = %d';
            $values[] = $store_id;
        }

        if ($status) {
            $where[] = 'o.status = %s';
            $values[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;

        $sql = "
            SELECT o.*, s.name as store_name
            FROM {$wpdb->prefix}wcpmp_orders o
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            WHERE $where_sql
            ORDER BY o.order_date DESC
            LIMIT %d OFFSET %d
        ";

        $values[] = $per_page;
        $values[] = $offset;

        $orders = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_orders o WHERE $where_sql";
        $total = $wpdb->get_var($wpdb->prepare($count_sql, array_slice($values, 0, -2)));

        wp_send_json_success(array(
            'orders' => $orders,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ));
    }

    /**
     * AJAX handler for getting inventory
     */
    public function ajax_get_inventory() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_id = intval($_POST['product_id'] ?? 0);

        if ($product_id) {
            wp_send_json_success($this->tracker->get_product_stock($product_id));
        } else {
            wp_send_json_success($this->tracker->get_inventory_summary());
        }
    }
}
