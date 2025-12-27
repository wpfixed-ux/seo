<?php
/**
 * Logger Class
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Logger {

    /**
     * Log operation
     *
     * @param string $operation_type Type of operation
     * @param mixed  $details        Operation details
     * @param int    $tokens         Tokens used
     * @param string $status         Operation status
     * @param float  $cost           Operation cost
     * @return int|false             Log ID or false on failure
     */
    public static function log_operation($operation_type, $details = '', $tokens = 0, $status = 'success', $cost = 0.0) {
        global $wpdb;

        $settings = get_option('ail_settings', []);
        if (!isset($settings['enable_logging']) || !$settings['enable_logging']) {
            return false;
        }

        $table = $wpdb->prefix . 'ai_interlinking_logs';

        // Convert details to JSON if it's an array
        if (is_array($details) || is_object($details)) {
            $details = wp_json_encode($details);
        }

        $result = $wpdb->insert(
            $table,
            [
                'operation_type' => sanitize_text_field($operation_type),
                'details' => $details,
                'tokens_used' => absint($tokens),
                'cost' => floatval($cost),
                'status' => sanitize_text_field($status),
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%f', '%s', '%d', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Log API request
     *
     * @param array $request  Request data
     * @param array $response Response data
     * @param int   $tokens   Tokens used
     * @param float $cost     Request cost
     * @return int|false      Log ID or false on failure
     */
    public static function log_api_request($request, $response, $tokens = 0, $cost = 0.0) {
        $settings = get_option('ail_settings', []);
        if (!isset($settings['log_api_requests']) || !$settings['log_api_requests']) {
            return false;
        }

        $details = [
            'request' => $request,
            'response' => $response,
            'timestamp' => time(),
        ];

        $status = isset($response['error']) ? 'error' : 'success';

        return self::log_operation('api_request', $details, $tokens, $status, $cost);
    }

    /**
     * Log link creation
     *
     * @param int    $source_id Source post ID
     * @param int    $target_id Target post ID
     * @param string $anchor    Anchor text
     * @param string $strategy  Linking strategy
     * @return int|false        Log ID or false on failure
     */
    public static function log_link_creation($source_id, $target_id, $anchor, $strategy = '') {
        $details = [
            'source_post_id' => $source_id,
            'target_post_id' => $target_id,
            'anchor_text' => $anchor,
            'strategy' => $strategy,
        ];

        return self::log_operation('link_created', $details);
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array      Array of log entries
     */
    public static function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'operation_type' => '',
            'status' => '',
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC',
            'orderby' => 'created_at',
        ];

        $args = wp_parse_args($args, $defaults);

        $table = $wpdb->prefix . 'ai_interlinking_logs';
        $where = ['1=1'];
        $values = [];

        if (!empty($args['operation_type'])) {
            $where[] = 'operation_type = %s';
            $values[] = $args['operation_type'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf(
            'ORDER BY %s %s',
            esc_sql($args['orderby']),
            esc_sql($args['order'])
        );

        $query = "SELECT * FROM {$table} WHERE {$where_clause} {$order_clause} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get statistics
     *
     * @param int $days Number of days to analyze
     * @return array    Statistics data
     */
    public static function get_statistics($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_interlinking_logs';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Total operations
        $total_operations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $date_from
        ));

        // Total API calls
        $api_calls = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE operation_type = 'api_request' AND created_at >= %s",
            $date_from
        ));

        // Total tokens used
        $tokens_used = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(tokens_used) FROM {$table} WHERE created_at >= %s",
            $date_from
        ));

        // Total cost
        $total_cost = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cost) FROM {$table} WHERE created_at >= %s",
            $date_from
        ));

        // Links created
        $links_table = $wpdb->prefix . 'ai_interlinking_links';
        $links_created = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$links_table} WHERE created_at >= %s",
            $date_from
        ));

        // Error count
        $errors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'error' AND created_at >= %s",
            $date_from
        ));

        return [
            'total_operations' => intval($total_operations),
            'api_calls' => intval($api_calls),
            'tokens_used' => intval($tokens_used ?: 0),
            'total_cost' => floatval($total_cost ?: 0),
            'links_created' => intval($links_created),
            'errors' => intval($errors),
            'period_days' => $days,
        ];
    }

    /**
     * Auto cleanup old logs
     *
     * @param int $days Days to keep logs
     * @return int      Number of deleted rows
     */
    public static function auto_cleanup($days = null) {
        global $wpdb;

        $settings = get_option('ail_settings', []);
        if ($days === null) {
            $days = isset($settings['log_retention_days']) ? intval($settings['log_retention_days']) : 30;
        }

        $table = $wpdb->prefix . 'ai_interlinking_logs';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $date_limit
        ));

        if ($deleted) {
            self::log_operation(
                'log_cleanup',
                ['deleted_rows' => $deleted, 'days' => $days],
                0,
                'success'
            );
        }

        return $deleted;
    }

    /**
     * Get operation type summary
     *
     * @param int $days Number of days to analyze
     * @return array    Summary by operation type
     */
    public static function get_operation_summary($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_interlinking_logs';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                operation_type,
                COUNT(*) as count,
                SUM(tokens_used) as total_tokens,
                SUM(cost) as total_cost,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
            FROM {$table}
            WHERE created_at >= %s
            GROUP BY operation_type
            ORDER BY count DESC",
            $date_from
        ), ARRAY_A);

        return $results;
    }

    /**
     * Clear all logs
     *
     * @return bool Success status
     */
    public static function clear_all_logs() {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_interlinking_logs';
        $result = $wpdb->query("TRUNCATE TABLE {$table}");

        return $result !== false;
    }
}
