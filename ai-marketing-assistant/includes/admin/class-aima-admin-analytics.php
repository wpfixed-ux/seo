<?php
/**
 * Analytics admin page
 *
 * @package AIMarketingAssistant
 */

class AIMA_Admin_Analytics {

    /**
     * Render analytics page
     */
    public function render_page() {
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        $analytics = AIMA_Database::get_analytics_summary($days);
        $top_products = AIMA_Database::get_top_products(10, $days);

        include AIMA_ADMIN_DIR . 'views/analytics.php';
    }

    /**
     * Get customer lifetime value data
     */
    public function get_customer_ltv() {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        return $wpdb->get_results(
            "SELECT
                CASE
                    WHEN total_spent < 1000 THEN '< 1000'
                    WHEN total_spent < 5000 THEN '1000-5000'
                    WHEN total_spent < 10000 THEN '5000-10000'
                    ELSE '> 10000'
                END as ltv_range,
                COUNT(*) as customer_count
            FROM $customers_table
            GROUP BY ltv_range
            ORDER BY MIN(total_spent)"
        );
    }

    /**
     * Get customer retention rate
     */
    public function get_retention_rate() {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        $date_30_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $date_60_days_ago = date('Y-m-d H:i:s', strtotime('-60 days'));

        $repeat_customers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $customers_table
            WHERE total_orders > 1
            AND last_order_date >= %s",
            $date_30_days_ago
        ));

        $total_customers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $customers_table
            WHERE first_order_date <= %s",
            $date_60_days_ago
        ));

        if ($total_customers > 0) {
            return round(($repeat_customers / $total_customers) * 100, 2);
        }

        return 0;
    }
}
