<?php
/**
 * Orders management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Orders {

    /**
     * Get order by ID
     */
    public function get_order($order_id) {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, s.name as store_name, s.type as store_type
            FROM {$wpdb->prefix}wcpmp_orders o
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            WHERE o.id = %d",
            $order_id
        ));

        if ($order) {
            $order->items = $wpdb->get_results($wpdb->prepare(
                "SELECT oi.*, p.name_uk as product_name
                FROM {$wpdb->prefix}wcpmp_order_items oi
                LEFT JOIN {$wpdb->prefix}wcpmp_products p ON oi.product_id = p.id
                WHERE oi.order_id = %d",
                $order_id
            ));
        }

        return $order;
    }

    /**
     * Get orders list
     */
    public function get_orders($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'store_id' => 0,
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['store_id']) {
            $where[] = 'o.store_id = %d';
            $values[] = $args['store_id'];
        }

        if ($args['status']) {
            $where[] = 'o.status = %s';
            $values[] = $args['status'];
        }

        if ($args['date_from']) {
            $where[] = 'o.order_date >= %s';
            $values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'o.order_date <= %s';
            $values[] = $args['date_to'];
        }

        if ($args['search']) {
            $where[] = '(o.customer_name LIKE %s OR o.customer_email LIKE %s OR o.external_order_id LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "
            SELECT o.*, s.name as store_name
            FROM {$wpdb->prefix}wcpmp_orders o
            JOIN {$wpdb->prefix}wcpmp_stores s ON o.store_id = s.id
            WHERE $where_sql
            ORDER BY o.order_date DESC
            LIMIT %d OFFSET %d
        ";

        $values[] = $args['per_page'];
        $values[] = $offset;

        $orders = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Get total
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_orders o WHERE $where_sql";
        $total = $wpdb->get_var($wpdb->prepare($count_sql, array_slice($values, 0, -2)));

        return array(
            'orders' => $orders,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Update order status locally
     */
    public function update_status($order_id, $status) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'wcpmp_orders',
            array('status' => $status),
            array('id' => $order_id)
        );
    }

    /**
     * Get order statistics
     */
    public function get_statistics($period = 'month') {
        global $wpdb;

        switch ($period) {
            case 'week':
                $date_from = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $date_from = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'quarter':
                $date_from = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'year':
                $date_from = date('Y-m-d', strtotime('-1 year'));
                break;
            default:
                $date_from = date('Y-m-d', strtotime('-30 days'));
        }

        return array(
            'total' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_orders WHERE order_date >= %s",
                $date_from
            )),
            'revenue' => $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}wcpmp_orders
                WHERE order_date >= %s AND status IN ('completed', 'processing')",
                $date_from
            )),
            'average' => $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(AVG(total), 0) FROM {$wpdb->prefix}wcpmp_orders
                WHERE order_date >= %s AND status IN ('completed', 'processing')",
                $date_from
            ))
        );
    }
}
