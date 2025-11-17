<?php
/**
 * Translation Log Model
 *
 * @package WC_AI_Translator
 */

class WCAT_Translation_Log {

    /**
     * Table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcat_translation_logs';
    }

    /**
     * Add log entry
     *
     * @param array $data Log data
     * @return int|false Log ID or false on failure
     */
    public function add_log($data) {
        global $wpdb;

        $defaults = array(
            'queue_id' => null,
            'content_id' => 0,
            'content_type' => '',
            'source_lang' => '',
            'target_lang' => '',
            'action' => '',
            'status' => '',
            'message' => '',
            'tokens_used' => 0,
            'cost' => 0,
            'user_id' => get_current_user_id(),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'queue_id' => $data['queue_id'],
                'content_id' => $data['content_id'],
                'content_type' => $data['content_type'],
                'source_lang' => $data['source_lang'],
                'target_lang' => $data['target_lang'],
                'action' => $data['action'],
                'status' => $data['status'],
                'message' => $data['message'],
                'tokens_used' => $data['tokens_used'],
                'cost' => $data['cost'],
                'user_id' => $data['user_id'],
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'content_id' => null,
            'content_type' => null,
            'status' => null,
            'user_id' => null,
            'date_from' => null,
            'date_to' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');

        if ($args['content_id']) {
            $where[] = $wpdb->prepare('content_id = %d', $args['content_id']);
        }

        if ($args['content_type']) {
            $where[] = $wpdb->prepare('content_type = %s', $args['content_type']);
        }

        if ($args['status']) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }

        if ($args['user_id']) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }

        if ($args['date_from']) {
            $where[] = $wpdb->prepare('created_at >= %s', $args['date_from']);
        }

        if ($args['date_to']) {
            $where[] = $wpdb->prepare('created_at <= %s', $args['date_to']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT * FROM {$this->table_name}
                WHERE {$where_clause}
                ORDER BY {$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $args['limit'], $args['offset']),
            ARRAY_A
        );

        return $results;
    }

    /**
     * Get log count
     *
     * @param array $args Query arguments
     * @return int Count of logs
     */
    public function get_log_count($args = array()) {
        global $wpdb;

        $where = array('1=1');

        if (isset($args['content_id'])) {
            $where[] = $wpdb->prepare('content_id = %d', $args['content_id']);
        }

        if (isset($args['content_type'])) {
            $where[] = $wpdb->prepare('content_type = %s', $args['content_type']);
        }

        if (isset($args['status'])) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get statistics
     *
     * @param array $args Query arguments
     * @return array Statistics
     */
    public function get_statistics($args = array()) {
        global $wpdb;

        $defaults = array(
            'date_from' => null,
            'date_to' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');

        if ($args['date_from']) {
            $where[] = $wpdb->prepare('created_at >= %s', $args['date_from']);
        }

        if ($args['date_to']) {
            $where[] = $wpdb->prepare('created_at <= %s', $args['date_to']);
        }

        $where_clause = implode(' AND ', $where);

        // Get total translations
        $total_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        $total = (int) $wpdb->get_var($total_sql);

        // Get successful translations
        $success_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause} AND status = 'success'";
        $success = (int) $wpdb->get_var($success_sql);

        // Get failed translations
        $failed_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause} AND status = 'failed'";
        $failed = (int) $wpdb->get_var($failed_sql);

        // Get total tokens used
        $tokens_sql = "SELECT SUM(tokens_used) FROM {$this->table_name} WHERE {$where_clause}";
        $tokens = (int) $wpdb->get_var($tokens_sql);

        // Get total cost
        $cost_sql = "SELECT SUM(cost) FROM {$this->table_name} WHERE {$where_clause}";
        $cost = (float) $wpdb->get_var($cost_sql);

        // Get translations by content type
        $by_type_sql = "SELECT content_type, COUNT(*) as count
                       FROM {$this->table_name}
                       WHERE {$where_clause}
                       GROUP BY content_type";
        $by_type = $wpdb->get_results($by_type_sql, ARRAY_A);

        return array(
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'tokens_used' => $tokens,
            'total_cost' => $cost,
            'by_content_type' => $by_type,
        );
    }

    /**
     * Delete log
     *
     * @param int $log_id Log ID
     * @return bool Success
     */
    public function delete_log($log_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $log_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Clear all logs
     *
     * @return bool Success
     */
    public function clear_all_logs() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}") !== false;
    }

    /**
     * Cleanup old logs
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted rows
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date
            )
        );
    }

    /**
     * Export logs to CSV
     *
     * @param array $args Query arguments
     * @return string CSV content
     */
    public function export_to_csv($args = array()) {
        $logs = $this->get_logs(array_merge($args, array('limit' => 999999, 'offset' => 0)));

        $csv = "ID,Content ID,Content Type,Source Lang,Target Lang,Action,Status,Message,Tokens,Cost,User ID,Created At\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%d,%s,%s,%s,%s,%s,\"%s\",%d,%.4f,%d,%s\n",
                $log['id'],
                $log['content_id'],
                $log['content_type'],
                $log['source_lang'],
                $log['target_lang'],
                $log['action'],
                $log['status'],
                str_replace('"', '""', $log['message']),
                $log['tokens_used'],
                $log['cost'],
                $log['user_id'],
                $log['created_at']
            );
        }

        return $csv;
    }
}
