<?php
/**
 * Customer analyzer module
 *
 * @package AIMarketingAssistant
 */

class AIMA_Customer_Analyzer {

    /**
     * Update customer analytics
     */
    public function update_customer_analytics() {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        // Get all customers
        $customers = $wpdb->get_results("SELECT id, email FROM $customers_table");

        foreach ($customers as $customer) {
            // Calculate analytics for each customer
            $analytics = $this->calculate_customer_analytics($customer->id);

            // Update customer record
            $wpdb->update(
                $customers_table,
                array(
                    'total_orders' => $analytics['total_orders'],
                    'total_spent' => $analytics['total_spent'],
                    'last_order_date' => $analytics['last_order_date'],
                    'first_order_date' => $analytics['first_order_date']
                ),
                array('id' => $customer->id),
                array('%d', '%f', '%s', '%s'),
                array('%d')
            );
        }

        return count($customers);
    }

    /**
     * Calculate customer analytics
     */
    public function calculate_customer_analytics($customer_id) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        $analytics = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(DISTINCT order_id) as total_orders,
                SUM(total) as total_spent,
                MAX(order_date) as last_order_date,
                MIN(order_date) as first_order_date
            FROM $purchases_table
            WHERE customer_id = %d",
            $customer_id
        ), ARRAY_A);

        return $analytics;
    }

    /**
     * Merge duplicate customers by email
     */
    public function merge_duplicate_customers() {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        // Find duplicate emails
        $duplicates = $wpdb->get_results(
            "SELECT email, GROUP_CONCAT(id) as ids, COUNT(*) as count
            FROM $customers_table
            GROUP BY email
            HAVING count > 1"
        );

        $merged_count = 0;

        foreach ($duplicates as $duplicate) {
            $ids = explode(',', $duplicate->ids);
            $primary_id = $ids[0]; // Keep first customer as primary

            // Merge purchases to primary customer
            for ($i = 1; $i < count($ids); $i++) {
                $secondary_id = $ids[$i];

                // Update purchases
                $wpdb->update(
                    $purchases_table,
                    array('customer_id' => $primary_id),
                    array('customer_id' => $secondary_id),
                    array('%d'),
                    array('%d')
                );

                // Delete secondary customer
                $wpdb->delete(
                    $customers_table,
                    array('id' => $secondary_id),
                    array('%d')
                );
            }

            // Recalculate analytics for merged customer
            $analytics = $this->calculate_customer_analytics($primary_id);
            $wpdb->update(
                $customers_table,
                array(
                    'total_orders' => $analytics['total_orders'],
                    'total_spent' => $analytics['total_spent'],
                    'last_order_date' => $analytics['last_order_date'],
                    'first_order_date' => $analytics['first_order_date']
                ),
                array('id' => $primary_id),
                array('%d', '%f', '%s', '%s'),
                array('%d')
            );

            $merged_count++;
        }

        return $merged_count;
    }

    /**
     * Get customer purchase patterns
     */
    public function get_customer_purchase_patterns($customer_id) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        // Get most purchased categories
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT category, COUNT(*) as purchase_count, SUM(total) as total_spent
            FROM $purchases_table
            WHERE customer_id = %d AND category IS NOT NULL
            GROUP BY category
            ORDER BY purchase_count DESC
            LIMIT 5",
            $customer_id
        ));

        // Get most purchased products
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, product_name, COUNT(*) as purchase_count
            FROM $purchases_table
            WHERE customer_id = %d
            GROUP BY product_id
            ORDER BY purchase_count DESC
            LIMIT 10",
            $customer_id
        ));

        // Calculate average order value
        $avg_order = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(order_total) FROM (
                SELECT order_id, SUM(total) as order_total
                FROM $purchases_table
                WHERE customer_id = %d
                GROUP BY order_id
            ) as orders",
            $customer_id
        ));

        // Get purchase frequency (days between orders)
        $frequency = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(days_diff) FROM (
                SELECT DATEDIFF(
                    order_date,
                    LAG(order_date) OVER (ORDER BY order_date)
                ) as days_diff
                FROM $purchases_table
                WHERE customer_id = %d
                ORDER BY order_date
            ) as diffs
            WHERE days_diff IS NOT NULL",
            $customer_id
        ));

        return array(
            'top_categories' => $categories,
            'top_products' => $products,
            'avg_order_value' => $avg_order,
            'purchase_frequency_days' => $frequency
        );
    }

    /**
     * Identify churn risk customers
     */
    public function identify_churn_risk($days_inactive = 60) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days_inactive} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customers_table
            WHERE last_order_date < %s
            AND total_orders > 1
            ORDER BY last_order_date ASC
            LIMIT 100",
            $date_threshold
        ));
    }
}
