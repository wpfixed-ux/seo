<?php
/**
 * Database operations class
 *
 * @package AIMarketingAssistant
 */

class AIMA_Database {

    /**
     * Get customer by email
     */
    public static function get_customer_by_email($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_customers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ));
    }

    /**
     * Insert or update customer
     */
    public static function upsert_customer($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_customers';

        $existing = self::get_customer_by_email($data['email']);

        if ($existing) {
            // Update existing customer
            $wpdb->update(
                $table,
                $data,
                array('email' => $data['email']),
                array('%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s'),
                array('%s')
            );
            return $existing->id;
        } else {
            // Insert new customer
            $wpdb->insert(
                $table,
                $data,
                array('%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s')
            );
            return $wpdb->insert_id;
        }
    }

    /**
     * Add purchase to history
     */
    public static function add_purchase($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_purchase_history';

        return $wpdb->insert(
            $table,
            $data,
            array('%d', '%d', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s')
        );
    }

    /**
     * Get customer purchase history
     */
    public static function get_customer_purchases($customer_id, $limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_purchase_history';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE customer_id = %d ORDER BY order_date DESC LIMIT %d",
            $customer_id,
            $limit
        ));
    }

    /**
     * Get customers by segment
     */
    public static function get_segment_customers($segment_id) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';
        $members_table = $wpdb->prefix . 'aima_segment_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM $customers_table c
            INNER JOIN $members_table m ON c.id = m.customer_id
            WHERE m.segment_id = %d",
            $segment_id
        ));
    }

    /**
     * Add customer to segment
     */
    public static function add_customer_to_segment($customer_id, $segment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_segment_members';

        return $wpdb->insert(
            $table,
            array(
                'customer_id' => $customer_id,
                'segment_id' => $segment_id
            ),
            array('%d', '%d')
        );
    }

    /**
     * Get all segments
     */
    public static function get_segments($status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_segments';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY name ASC",
                $status
            ));
        }

        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    /**
     * Create or update segment
     */
    public static function upsert_segment($data, $segment_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_segments';

        if ($segment_id) {
            return $wpdb->update(
                $table,
                $data,
                array('id' => $segment_id),
                array('%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                $data,
                array('%s', '%s', '%s', '%d', '%s')
            );
            return $wpdb->insert_id;
        }
    }

    /**
     * Get offer by ID
     */
    public static function get_offer($offer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_offers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $offer_id
        ));
    }

    /**
     * Get all offers
     */
    public static function get_offers($status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_offers';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC",
                $status
            ));
        }

        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    /**
     * Create or update offer
     */
    public static function upsert_offer($data, $offer_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_offers';

        if ($offer_id) {
            return $wpdb->update(
                $table,
                $data,
                array('id' => $offer_id),
                null,
                array('%d')
            );
        } else {
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Get analytics data
     */
    public static function get_analytics_summary($days = 30) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $summary = array();

        // Total customers
        $summary['total_customers'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $customers_table"
        );

        // New customers
        $summary['new_customers'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $customers_table WHERE created_at >= %s",
            $date_from
        ));

        // Total orders
        $summary['total_orders'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT order_id) FROM $purchases_table WHERE order_date >= %s",
            $date_from
        ));

        // Total revenue
        $summary['total_revenue'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total) FROM $purchases_table WHERE order_date >= %s",
            $date_from
        ));

        return $summary;
    }

    /**
     * Get top products
     */
    public static function get_top_products($limit = 10, $days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_purchase_history';

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, product_name, category,
                    SUM(quantity) as total_quantity,
                    SUM(total) as total_revenue,
                    COUNT(DISTINCT customer_id) as unique_customers
             FROM $table
             WHERE order_date >= %s
             GROUP BY product_id
             ORDER BY total_revenue DESC
             LIMIT %d",
            $date_from,
            $limit
        ));
    }
}
