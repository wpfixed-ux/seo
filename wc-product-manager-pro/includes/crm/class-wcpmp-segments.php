<?php
/**
 * Customer Segments Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Segments {

    /**
     * Get all segments
     */
    public function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wcpmp_segments ORDER BY name"
        );
    }

    /**
     * Get single segment
     */
    public function get($segment_id) {
        global $wpdb;

        $segment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_segments WHERE id = %d",
            $segment_id
        ));

        if ($segment) {
            $segment->conditions = json_decode($segment->conditions, true);
        }

        return $segment;
    }

    /**
     * Create segment
     */
    public function create($data) {
        global $wpdb;

        $result = $wpdb->insert($wpdb->prefix . 'wcpmp_segments', array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'conditions' => json_encode($data['conditions']),
            'is_dynamic' => isset($data['is_dynamic']) ? 1 : 0
        ));

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create segment', 'wc-product-manager-pro'));
        }

        $segment_id = $wpdb->insert_id;

        // Update customer count
        $this->update_customers($segment_id);

        return $segment_id;
    }

    /**
     * Update segment
     */
    public function update($segment_id, $data) {
        global $wpdb;

        $update = array();

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['conditions'])) {
            $update['conditions'] = json_encode($data['conditions']);
        }
        if (isset($data['is_dynamic'])) {
            $update['is_dynamic'] = $data['is_dynamic'] ? 1 : 0;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'wcpmp_segments',
            $update,
            array('id' => $segment_id)
        );

        // Update customer count
        $this->update_customers($segment_id);

        return $result;
    }

    /**
     * Delete segment
     */
    public function delete($segment_id) {
        global $wpdb;

        // Remove segment from customers
        $customers = $wpdb->get_results(
            "SELECT id, segments FROM {$wpdb->prefix}wcpmp_customers WHERE segments IS NOT NULL"
        );

        foreach ($customers as $customer) {
            $segments = json_decode($customer->segments, true);
            if (is_array($segments) && in_array($segment_id, $segments)) {
                $segments = array_diff($segments, array($segment_id));
                $wpdb->update(
                    $wpdb->prefix . 'wcpmp_customers',
                    array('segments' => json_encode(array_values($segments))),
                    array('id' => $customer->id)
                );
            }
        }

        return $wpdb->delete($wpdb->prefix . 'wcpmp_segments', array('id' => $segment_id));
    }

    /**
     * Get customers in segment
     */
    public function get_customers($segment_id) {
        global $wpdb;

        $segment = $this->get($segment_id);
        if (!$segment) {
            return array();
        }

        $customers = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wcpmp_customers WHERE subscribed = 1"
        );

        return $this->filter_customers($customers, $segment->conditions);
    }

    /**
     * Filter customers by conditions
     */
    public function filter_customers($customers, $conditions) {
        if (!is_array($conditions) || empty($conditions)) {
            return $customers;
        }

        return array_filter($customers, function($customer) use ($conditions) {
            foreach ($conditions as $condition) {
                if (!$this->check_condition($customer, $condition)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Check single condition
     */
    private function check_condition($customer, $condition) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $customer_value = isset($customer->$field) ? $customer->$field : null;

        switch ($operator) {
            case 'equals':
                return $customer_value == $value;

            case 'not_equals':
                return $customer_value != $value;

            case 'greater_than':
                return $customer_value > $value;

            case 'less_than':
                return $customer_value < $value;

            case 'greater_equal':
                return $customer_value >= $value;

            case 'less_equal':
                return $customer_value <= $value;

            case 'contains':
                return stripos($customer_value, $value) !== false;

            case 'not_contains':
                return stripos($customer_value, $value) === false;

            case 'starts_with':
                return strpos($customer_value, $value) === 0;

            case 'ends_with':
                return substr($customer_value, -strlen($value)) === $value;

            case 'is_empty':
                return empty($customer_value);

            case 'is_not_empty':
                return !empty($customer_value);

            case 'days_ago':
                if (empty($customer_value)) return false;
                $days = (strtotime('today') - strtotime($customer_value)) / 86400;
                return $days >= $value;

            case 'within_days':
                if (empty($customer_value)) return false;
                $days = (strtotime('today') - strtotime($customer_value)) / 86400;
                return $days <= $value;

            default:
                return true;
        }
    }

    /**
     * Update customers in segment (for dynamic segments)
     */
    public function update_customers($segment_id) {
        global $wpdb;

        $segment = $this->get($segment_id);
        if (!$segment || !$segment->is_dynamic) {
            return;
        }

        // Get matching customers
        $matching = $this->get_customers($segment_id);
        $count = count($matching);

        // Update count
        $wpdb->update(
            $wpdb->prefix . 'wcpmp_segments',
            array('customer_count' => $count),
            array('id' => $segment_id)
        );

        // Update customers' segments
        $matching_ids = array_map(function($c) { return $c->id; }, $matching);

        // Add segment to matching customers
        foreach ($matching as $customer) {
            $segments = $customer->segments ? json_decode($customer->segments, true) : array();
            if (!in_array($segment_id, $segments)) {
                $segments[] = $segment_id;
                $wpdb->update(
                    $wpdb->prefix . 'wcpmp_customers',
                    array('segments' => json_encode($segments)),
                    array('id' => $customer->id)
                );
            }
        }

        // Remove segment from non-matching customers
        $all_customers = $wpdb->get_results(
            "SELECT id, segments FROM {$wpdb->prefix}wcpmp_customers"
        );

        foreach ($all_customers as $customer) {
            if (in_array($customer->id, $matching_ids)) continue;

            $segments = $customer->segments ? json_decode($customer->segments, true) : array();
            if (in_array($segment_id, $segments)) {
                $segments = array_diff($segments, array($segment_id));
                $wpdb->update(
                    $wpdb->prefix . 'wcpmp_customers',
                    array('segments' => json_encode(array_values($segments))),
                    array('id' => $customer->id)
                );
            }
        }

        return $count;
    }

    /**
     * Get predefined segment templates
     */
    public function get_templates() {
        return array(
            'vip' => array(
                'name' => __('VIP Customers', 'wc-product-manager-pro'),
                'description' => __('Customers with high lifetime value', 'wc-product-manager-pro'),
                'conditions' => array(
                    array('field' => 'total_spent', 'operator' => 'greater_than', 'value' => 10000)
                )
            ),
            'new' => array(
                'name' => __('New Customers', 'wc-product-manager-pro'),
                'description' => __('Customers with only one order', 'wc-product-manager-pro'),
                'conditions' => array(
                    array('field' => 'total_orders', 'operator' => 'equals', 'value' => 1)
                )
            ),
            'inactive' => array(
                'name' => __('Inactive Customers', 'wc-product-manager-pro'),
                'description' => __('No orders in last 90 days', 'wc-product-manager-pro'),
                'conditions' => array(
                    array('field' => 'last_order_date', 'operator' => 'days_ago', 'value' => 90)
                )
            ),
            'frequent' => array(
                'name' => __('Frequent Buyers', 'wc-product-manager-pro'),
                'description' => __('More than 5 orders', 'wc-product-manager-pro'),
                'conditions' => array(
                    array('field' => 'total_orders', 'operator' => 'greater_than', 'value' => 5)
                )
            )
        );
    }
}
