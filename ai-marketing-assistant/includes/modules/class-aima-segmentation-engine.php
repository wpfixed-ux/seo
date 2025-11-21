<?php
/**
 * Segmentation engine module
 *
 * @package AIMarketingAssistant
 */

class AIMA_Segmentation_Engine {

    /**
     * Update all segments
     */
    public function update_all_segments() {
        $segments = AIMA_Database::get_segments('active');

        foreach ($segments as $segment) {
            $this->update_segment($segment->id);
        }

        return count($segments);
    }

    /**
     * Update specific segment
     */
    public function update_segment($segment_id) {
        global $wpdb;

        $segments_table = $wpdb->prefix . 'aima_segments';
        $members_table = $wpdb->prefix . 'aima_segment_members';

        // Get segment
        $segment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $segments_table WHERE id = %d",
            $segment_id
        ));

        if (!$segment) {
            return false;
        }

        // Clear existing members
        $wpdb->delete(
            $members_table,
            array('segment_id' => $segment_id),
            array('%d')
        );

        // Get customers matching segment conditions
        $conditions = json_decode($segment->conditions, true);
        $customers = $this->get_customers_by_conditions($conditions);

        // Add customers to segment
        $count = 0;
        foreach ($customers as $customer) {
            $wpdb->insert(
                $members_table,
                array(
                    'segment_id' => $segment_id,
                    'customer_id' => $customer->id
                ),
                array('%d', '%d')
            );
            $count++;
        }

        // Update segment customer count
        $wpdb->update(
            $segments_table,
            array('customer_count' => $count),
            array('id' => $segment_id),
            array('%d'),
            array('%d')
        );

        return $count;
    }

    /**
     * Get customers by conditions
     */
    private function get_customers_by_conditions($conditions) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        $where_clauses = array();
        $join_clauses = array();

        if (!is_array($conditions)) {
            return array();
        }

        foreach ($conditions as $condition) {
            if (!isset($condition['field']) || !isset($condition['operator']) || !isset($condition['value'])) {
                continue;
            }

            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            switch ($field) {
                case 'total_spent':
                    $where_clauses[] = $wpdb->prepare(
                        "c.total_spent {$operator} %f",
                        floatval($value)
                    );
                    break;

                case 'total_orders':
                    $where_clauses[] = $wpdb->prepare(
                        "c.total_orders {$operator} %d",
                        intval($value)
                    );
                    break;

                case 'last_order_date':
                    if (strpos($value, '-') === 0) {
                        // Relative date (e.g., "-30 days")
                        $date = date('Y-m-d H:i:s', strtotime($value));
                        $where_clauses[] = $wpdb->prepare(
                            "c.last_order_date {$operator} %s",
                            $date
                        );
                    } else {
                        $where_clauses[] = $wpdb->prepare(
                            "c.last_order_date {$operator} %s",
                            $value
                        );
                    }
                    break;

                case 'created_at':
                    if (strpos($value, '-') === 0) {
                        $date = date('Y-m-d H:i:s', strtotime($value));
                        $where_clauses[] = $wpdb->prepare(
                            "c.created_at {$operator} %s",
                            $date
                        );
                    } else {
                        $where_clauses[] = $wpdb->prepare(
                            "c.created_at {$operator} %s",
                            $value
                        );
                    }
                    break;

                case 'purchased_category':
                    $join_clauses[] = "INNER JOIN $purchases_table p ON c.id = p.customer_id";
                    $where_clauses[] = $wpdb->prepare(
                        "p.category = %s",
                        $value
                    );
                    break;

                case 'not_purchased_category':
                    $subquery = $wpdb->prepare(
                        "SELECT DISTINCT customer_id FROM $purchases_table WHERE category = %s",
                        $value
                    );
                    $where_clauses[] = "c.id NOT IN ($subquery)";
                    break;

                case 'purchased_product':
                    $join_clauses[] = "INNER JOIN $purchases_table p ON c.id = p.customer_id";
                    $where_clauses[] = $wpdb->prepare(
                        "p.product_id = %d",
                        intval($value)
                    );
                    break;

                case 'not_purchased_product':
                    $subquery = $wpdb->prepare(
                        "SELECT DISTINCT customer_id FROM $purchases_table WHERE product_id = %d",
                        intval($value)
                    );
                    $where_clauses[] = "c.id NOT IN ($subquery)";
                    break;
            }
        }

        if (empty($where_clauses)) {
            return array();
        }

        // Build query
        $joins = implode(' ', array_unique($join_clauses));
        $where = 'WHERE ' . implode(' AND ', $where_clauses);

        $query = "SELECT DISTINCT c.* FROM $customers_table c $joins $where";

        return $wpdb->get_results($query);
    }

    /**
     * Create automatic segments based on purchase behavior
     */
    public function create_automatic_segments() {
        $segments = array(
            array(
                'name' => __('VIP Customers', 'ai-marketing-assistant'),
                'description' => __('High-value customers with spending over 50000', 'ai-marketing-assistant'),
                'conditions' => json_encode(array(
                    array('field' => 'total_spent', 'operator' => '>', 'value' => 50000)
                )),
                'status' => 'active'
            ),
            array(
                'name' => __('Active Buyers', 'ai-marketing-assistant'),
                'description' => __('Customers with orders in last 30 days', 'ai-marketing-assistant'),
                'conditions' => json_encode(array(
                    array('field' => 'last_order_date', 'operator' => '>', 'value' => '-30 days')
                )),
                'status' => 'active'
            ),
            array(
                'name' => __('At Risk', 'ai-marketing-assistant'),
                'description' => __('Customers inactive for 60+ days', 'ai-marketing-assistant'),
                'conditions' => json_encode(array(
                    array('field' => 'last_order_date', 'operator' => '<', 'value' => '-60 days'),
                    array('field' => 'total_orders', 'operator' => '>', 'value' => 1)
                )),
                'status' => 'active'
            ),
            array(
                'name' => __('One-Time Buyers', 'ai-marketing-assistant'),
                'description' => __('Customers with only one purchase', 'ai-marketing-assistant'),
                'conditions' => json_encode(array(
                    array('field' => 'total_orders', 'operator' => '=', 'value' => 1)
                )),
                'status' => 'active'
            ),
            array(
                'name' => __('Frequent Shoppers', 'ai-marketing-assistant'),
                'description' => __('Customers with 5+ orders', 'ai-marketing-assistant'),
                'conditions' => json_encode(array(
                    array('field' => 'total_orders', 'operator' => '>=', 'value' => 5)
                )),
                'status' => 'active'
            )
        );

        $created_count = 0;

        foreach ($segments as $segment_data) {
            $segment_id = AIMA_Database::upsert_segment($segment_data);
            if ($segment_id) {
                $this->update_segment($segment_id);
                $created_count++;
            }
        }

        return $created_count;
    }

    /**
     * Get segment overlap analysis
     */
    public function get_segment_overlap($segment_id1, $segment_id2) {
        global $wpdb;
        $members_table = $wpdb->prefix . 'aima_segment_members';

        $overlap = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT m1.customer_id)
            FROM $members_table m1
            INNER JOIN $members_table m2 ON m1.customer_id = m2.customer_id
            WHERE m1.segment_id = %d AND m2.segment_id = %d",
            $segment_id1,
            $segment_id2
        ));

        return $overlap;
    }
}
