<?php
/**
 * Inventory Tracker - tracks stock movements
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Inventory_Tracker {

    /**
     * Get inventory summary
     */
    public function get_inventory_summary() {
        global $wpdb;

        $summary = array();

        // Total products
        $summary['total_products'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products"
        );

        // In stock
        $summary['in_stock'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products WHERE stock_quantity > 0"
        );

        // Out of stock
        $summary['out_of_stock'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products WHERE stock_quantity <= 0"
        );

        // Low stock (less than 10)
        $summary['low_stock'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products WHERE stock_quantity > 0 AND stock_quantity < 10"
        );

        // Total stock value
        $summary['total_value'] = $wpdb->get_var(
            "SELECT SUM(stock_quantity * price) FROM {$wpdb->prefix}wcpmp_products WHERE stock_quantity > 0"
        );

        return $summary;
    }

    /**
     * Get product stock across all stores
     */
    public function get_product_stock($product_id) {
        global $wpdb;

        // Main stock
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT stock_quantity, stock_status FROM {$wpdb->prefix}wcpmp_products WHERE id = %d",
            $product_id
        ));

        // Stock in each store
        $store_stock = $wpdb->get_results($wpdb->prepare("
            SELECT ps.stock_quantity, s.name as store_name, s.id as store_id
            FROM {$wpdb->prefix}wcpmp_product_stores ps
            JOIN {$wpdb->prefix}wcpmp_stores s ON ps.store_id = s.id
            WHERE ps.product_id = %d
        ", $product_id));

        return array(
            'main' => $product,
            'stores' => $store_stock
        );
    }

    /**
     * Calculate sold quantity from orders
     */
    public function get_sold_quantity($product_id, $period = '30days') {
        global $wpdb;

        $date_from = $this->get_period_date($period);

        return $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM {$wpdb->prefix}wcpmp_order_items oi
            JOIN {$wpdb->prefix}wcpmp_orders o ON oi.order_id = o.id
            WHERE oi.product_id = %d
            AND o.order_date >= %s
            AND o.status IN ('processing', 'completed')
        ", $product_id, $date_from));
    }

    /**
     * Get remaining stock after pending orders
     */
    public function get_available_stock($product_id) {
        global $wpdb;

        // Current stock
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT stock_quantity FROM {$wpdb->prefix}wcpmp_products WHERE id = %d",
            $product_id
        ));

        // Reserved in pending orders
        $reserved = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM {$wpdb->prefix}wcpmp_order_items oi
            JOIN {$wpdb->prefix}wcpmp_orders o ON oi.order_id = o.id
            WHERE oi.product_id = %d
            AND o.status IN ('pending', 'processing')
        ", $product_id));

        return max(0, $current - $reserved);
    }

    /**
     * Get stock history for product
     */
    public function get_stock_movements($product_id, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                o.external_order_id,
                o.order_date,
                oi.quantity,
                s.name as store_name,
                'sale' as movement_type
            FROM {$wpdb->prefix}wcpmp_order_items oi
            JOIN {$wpdb->prefix}wcpmp_orders o ON oi.order_id = o.id
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            WHERE oi.product_id = %d
            ORDER BY o.order_date DESC
            LIMIT %d
        ", $product_id, $limit));
    }

    /**
     * Get low stock products
     */
    public function get_low_stock_products($threshold = 10) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, c.name_uk as category_name
            FROM {$wpdb->prefix}wcpmp_products p
            LEFT JOIN {$wpdb->prefix}wcpmp_categories c ON p.category_id = c.id
            WHERE p.stock_quantity > 0 AND p.stock_quantity < %d
            ORDER BY p.stock_quantity ASC
        ", $threshold));
    }

    /**
     * Get best sellers
     */
    public function get_best_sellers($period = '30days', $limit = 10) {
        global $wpdb;

        $date_from = $this->get_period_date($period);

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                p.id,
                p.name_uk,
                p.sku,
                p.price,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total) as total_revenue
            FROM {$wpdb->prefix}wcpmp_order_items oi
            JOIN {$wpdb->prefix}wcpmp_products p ON oi.product_id = p.id
            JOIN {$wpdb->prefix}wcpmp_orders o ON oi.order_id = o.id
            WHERE o.order_date >= %s
            AND o.status IN ('processing', 'completed')
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT %d
        ", $date_from, $limit));
    }

    /**
     * Get period start date
     */
    private function get_period_date($period) {
        switch ($period) {
            case '7days':
                return date('Y-m-d', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d', strtotime('-30 days'));
            case '90days':
                return date('Y-m-d', strtotime('-90 days'));
            case 'year':
                return date('Y-m-d', strtotime('-1 year'));
            default:
                return date('Y-m-d', strtotime('-30 days'));
        }
    }
}
