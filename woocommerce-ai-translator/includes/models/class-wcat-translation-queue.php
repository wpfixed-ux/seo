<?php
/**
 * Translation Queue Model
 *
 * @package WC_AI_Translator
 */

class WCAT_Translation_Queue {

    /**
     * Table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcat_translation_queue';
    }

    /**
     * Add item to queue
     *
     * @param array $data Queue item data
     * @return int|false Queue ID or false on failure
     */
    public function add_to_queue($data) {
        global $wpdb;

        $defaults = array(
            'content_id' => 0,
            'content_type' => '',
            'source_lang' => '',
            'target_lang' => '',
            'status' => 'pending',
            'priority' => 5,
            'attempts' => 0,
            'error_message' => '',
        );

        $data = wp_parse_args($data, $defaults);

        // Check if item already exists in queue
        $existing = $this->get_queue_item_by_content($data['content_id'], $data['content_type'], $data['target_lang']);

        if ($existing && in_array($existing['status'], array('pending', 'processing'))) {
            return $existing['id'];
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'content_id' => $data['content_id'],
                'content_type' => $data['content_type'],
                'source_lang' => $data['source_lang'],
                'target_lang' => $data['target_lang'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'attempts' => $data['attempts'],
                'error_message' => $data['error_message'],
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get queue item by ID
     *
     * @param int $queue_id Queue ID
     * @return array|null Queue item or null
     */
    public function get_queue_item($queue_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $queue_id),
            ARRAY_A
        );
    }

    /**
     * Get queue item by content
     *
     * @param int $content_id Content ID
     * @param string $content_type Content type
     * @param string $target_lang Target language
     * @return array|null Queue item or null
     */
    public function get_queue_item_by_content($content_id, $content_type, $target_lang) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE content_id = %d AND content_type = %s AND target_lang = %s
                ORDER BY id DESC LIMIT 1",
                $content_id,
                $content_type,
                $target_lang
            ),
            ARRAY_A
        );
    }

    /**
     * Get pending queue items
     *
     * @param int $limit Number of items to retrieve
     * @return array Array of queue items
     */
    public function get_pending_items($limit = 10) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE status = 'pending'
                ORDER BY priority DESC, created_at ASC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get all queue items
     *
     * @param array $args Query arguments
     * @return array Array of queue items
     */
    public function get_queue_items($args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');

        if ($args['status']) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT * FROM {$this->table_name}
                WHERE {$where_clause}
                ORDER BY {$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $args['limit'], $args['offset']),
            ARRAY_A
        );
    }

    /**
     * Update queue item status
     *
     * @param int $queue_id Queue ID
     * @param string $status New status
     * @param string $error_message Optional error message
     * @return bool Success
     */
    public function update_status($queue_id, $status, $error_message = '') {
        global $wpdb;

        $data = array(
            'status' => $status,
        );

        $format = array('%s');

        if (!empty($error_message)) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }

        if ($status === 'processing') {
            $data['attempts'] = 'attempts + 1';
            // Use raw SQL for incrementing
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name}
                    SET status = %s, attempts = attempts + 1, error_message = %s
                    WHERE id = %d",
                    $status,
                    $error_message,
                    $queue_id
                )
            );
            return $result !== false;
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $queue_id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete queue item
     *
     * @param int $queue_id Queue ID
     * @return bool Success
     */
    public function delete_item($queue_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $queue_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Clear completed items
     *
     * @return int Number of deleted rows
     */
    public function clear_completed() {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$this->table_name} WHERE status IN ('completed', 'failed')"
        );
    }

    /**
     * Get queue statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'");
        $processing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'processing'");
        $completed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'");

        return array(
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
        );
    }

    /**
     * Batch add to queue
     *
     * @param array $items Array of items to add
     * @return array Array of queue IDs
     */
    public function batch_add_to_queue($items) {
        $queue_ids = array();

        foreach ($items as $item) {
            $queue_id = $this->add_to_queue($item);
            if ($queue_id) {
                $queue_ids[] = $queue_id;
            }
        }

        return $queue_ids;
    }

    /**
     * Reset stuck items
     *
     * @param int $minutes Items stuck for this many minutes
     * @return int Number of reset items
     */
    public function reset_stuck_items($minutes = 30) {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                SET status = 'pending', error_message = 'Reset from stuck processing state'
                WHERE status = 'processing' AND updated_at < %s",
                $date
            )
        );
    }
}
