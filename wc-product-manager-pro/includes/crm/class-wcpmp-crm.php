<?php
/**
 * Mini CRM - Customer Relationship Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_CRM {

    private $segments;
    private $campaigns;

    public function __construct() {
        $this->segments = new WCPMP_Segments();
        $this->campaigns = new WCPMP_Email_Campaigns();
    }

    /**
     * Get CRM dashboard data
     */
    public function get_dashboard() {
        global $wpdb;

        return array(
            'total_customers' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_customers"
            ),
            'subscribed' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_customers WHERE subscribed = 1"
            ),
            'total_revenue' => $wpdb->get_var(
                "SELECT COALESCE(SUM(total_spent), 0) FROM {$wpdb->prefix}wcpmp_customers"
            ),
            'avg_order_value' => $wpdb->get_var(
                "SELECT COALESCE(AVG(average_order), 0) FROM {$wpdb->prefix}wcpmp_customers WHERE total_orders > 0"
            ),
            'segments' => $this->segments->get_all(),
            'recent_campaigns' => $this->campaigns->get_recent(5)
        );
    }

    /**
     * Get customers list
     */
    public function get_customers($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'segment_id' => 0,
            'search' => '',
            'orderby' => 'last_order_date',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['search']) {
            $where[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        $sql = "
            SELECT * FROM {$wpdb->prefix}wcpmp_customers
            WHERE $where_sql
            ORDER BY $orderby
            LIMIT %d OFFSET %d
        ";

        $values[] = $args['per_page'];
        $values[] = $offset;

        $customers = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Get segment filters if needed
        if ($args['segment_id']) {
            $segment = $this->segments->get($args['segment_id']);
            if ($segment) {
                $customers = $this->segments->filter_customers($customers, $segment->conditions);
            }
        }

        // Get total
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_customers WHERE $where_sql",
            array_slice($values, 0, -2)
        ));

        return array(
            'customers' => $customers,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Get single customer
     */
    public function get_customer($customer_id) {
        global $wpdb;

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_customers WHERE id = %d",
            $customer_id
        ));

        if ($customer) {
            // Get orders
            $customer->orders = $wpdb->get_results($wpdb->prepare("
                SELECT o.*, s.name as store_name
                FROM {$wpdb->prefix}wcpmp_orders o
                JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
                WHERE o.customer_email = %s
                ORDER BY o.order_date DESC
                LIMIT 50
            ", $customer->email));

            // Parse segments
            $customer->segments = $customer->segments ? json_decode($customer->segments, true) : array();
        }

        return $customer;
    }

    /**
     * Update customer
     */
    public function update_customer($customer_id, $data) {
        global $wpdb;

        $update = array();

        if (isset($data['first_name'])) {
            $update['first_name'] = sanitize_text_field($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $update['last_name'] = sanitize_text_field($data['last_name']);
        }
        if (isset($data['phone'])) {
            $update['phone'] = sanitize_text_field($data['phone']);
        }
        if (isset($data['language'])) {
            $update['language'] = sanitize_text_field($data['language']);
        }
        if (isset($data['subscribed'])) {
            $update['subscribed'] = $data['subscribed'] ? 1 : 0;
        }
        if (isset($data['segments'])) {
            $update['segments'] = json_encode($data['segments']);
        }
        if (isset($data['preferences'])) {
            $update['preferences'] = json_encode($data['preferences']);
        }

        return $wpdb->update(
            $wpdb->prefix . 'wcpmp_customers',
            $update,
            array('id' => $customer_id)
        );
    }

    /**
     * Add customer to segment
     */
    public function add_to_segment($customer_id, $segment_id) {
        global $wpdb;

        $customer = $this->get_customer($customer_id);
        if (!$customer) {
            return false;
        }

        $segments = $customer->segments ?: array();
        if (!in_array($segment_id, $segments)) {
            $segments[] = $segment_id;
        }

        return $wpdb->update(
            $wpdb->prefix . 'wcpmp_customers',
            array('segments' => json_encode($segments)),
            array('id' => $customer_id)
        );
    }

    /**
     * Remove customer from segment
     */
    public function remove_from_segment($customer_id, $segment_id) {
        global $wpdb;

        $customer = $this->get_customer($customer_id);
        if (!$customer) {
            return false;
        }

        $segments = $customer->segments ?: array();
        $segments = array_diff($segments, array($segment_id));

        return $wpdb->update(
            $wpdb->prefix . 'wcpmp_customers',
            array('segments' => json_encode(array_values($segments))),
            array('id' => $customer_id)
        );
    }

    /**
     * AJAX handler for sending campaign
     */
    public function ajax_send_campaign() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_crm')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $campaign_id = intval($_POST['campaign_id']);

        $result = $this->campaigns->send($campaign_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'sent' => $result
        ));
    }

    /**
     * Process scheduled campaigns (cron)
     */
    public function process_scheduled_campaigns() {
        $this->campaigns->process_scheduled();
    }

    /**
     * Get customer lifetime value
     */
    public function get_customer_ltv($customer_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT total_spent FROM {$wpdb->prefix}wcpmp_customers WHERE id = %d",
            $customer_id
        ));
    }

    /**
     * Get top customers
     */
    public function get_top_customers($limit = 10) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}wcpmp_customers
            WHERE total_spent > 0
            ORDER BY total_spent DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Update all customer segments
     */
    public function update_all_segments() {
        $segments = $this->segments->get_all();

        foreach ($segments as $segment) {
            if ($segment->is_dynamic) {
                $this->segments->update_customers($segment->id);
            }
        }
    }
}
