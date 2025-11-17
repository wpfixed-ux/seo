<?php
/**
 * Queue Manager
 *
 * @package WC_AI_Translator
 */

class WCAT_Queue_Manager {

    /**
     * Queue instance
     */
    private $queue;

    /**
     * Constructor
     */
    public function __construct() {
        $this->queue = new WCAT_Translation_Queue();
    }

    /**
     * Schedule queue processing
     */
    public function schedule_processing() {
        if (!wp_next_scheduled('wcat_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'wcat_process_queue');
        }
    }

    /**
     * Unschedule queue processing
     */
    public function unschedule_processing() {
        wp_clear_scheduled_hook('wcat_process_queue');
    }

    /**
     * Reset stuck items in queue
     *
     * @param int $minutes Items stuck for this many minutes
     * @return int Number of reset items
     */
    public function reset_stuck_items($minutes = 30) {
        return $this->queue->reset_stuck_items($minutes);
    }

    /**
     * Prioritize queue item
     *
     * @param int $queue_id Queue ID
     * @param int $priority Priority (1-10, higher = more important)
     * @return bool Success
     */
    public function set_priority($queue_id, $priority) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcat_translation_queue';

        $result = $wpdb->update(
            $table_name,
            array('priority' => $priority),
            array('id' => $queue_id),
            array('%d'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Pause queue processing
     *
     * @return bool Success
     */
    public function pause_queue() {
        update_option('wcat_queue_paused', true);
        return true;
    }

    /**
     * Resume queue processing
     *
     * @return bool Success
     */
    public function resume_queue() {
        delete_option('wcat_queue_paused');
        return true;
    }

    /**
     * Check if queue is paused
     *
     * @return bool Is paused
     */
    public function is_queue_paused() {
        return (bool) get_option('wcat_queue_paused', false);
    }

    /**
     * Get queue progress
     *
     * @return array Progress information
     */
    public function get_progress() {
        $stats = $this->queue->get_statistics();

        $total = $stats['total'];
        $completed = $stats['completed'] + $stats['failed'];
        $progress_percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        return array(
            'total' => $total,
            'pending' => $stats['pending'],
            'processing' => $stats['processing'],
            'completed' => $stats['completed'],
            'failed' => $stats['failed'],
            'progress_percentage' => $progress_percentage,
            'is_paused' => $this->is_queue_paused(),
        );
    }

    /**
     * Clear queue
     *
     * @param string $status Optional status to clear (all, pending, completed, failed)
     * @return int Number of items cleared
     */
    public function clear_queue($status = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcat_translation_queue';

        if ($status === 'all') {
            return $wpdb->query("TRUNCATE TABLE {$table_name}");
        }

        if (in_array($status, array('pending', 'processing', 'completed', 'failed'))) {
            return $wpdb->query(
                $wpdb->prepare("DELETE FROM {$table_name} WHERE status = %s", $status)
            );
        }

        return 0;
    }

    /**
     * Export queue to CSV
     *
     * @return string CSV content
     */
    public function export_queue_to_csv() {
        $items = $this->queue->get_queue_items(array('limit' => 999999, 'offset' => 0));

        $csv = "ID,Content ID,Content Type,Source Lang,Target Lang,Status,Priority,Attempts,Error Message,Created At,Updated At\n";

        foreach ($items as $item) {
            $csv .= sprintf(
                "%d,%d,%s,%s,%s,%s,%d,%d,\"%s\",%s,%s\n",
                $item['id'],
                $item['content_id'],
                $item['content_type'],
                $item['source_lang'],
                $item['target_lang'],
                $item['status'],
                $item['priority'],
                $item['attempts'],
                str_replace('"', '""', $item['error_message']),
                $item['created_at'],
                $item['updated_at']
            );
        }

        return $csv;
    }
}
