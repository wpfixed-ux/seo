<?php
/**
 * Data cleanup and archiving system
 *
 * @package AIMarketingAssistant
 */

class AIMA_Data_Cleanup {

    /**
     * Run cleanup process
     */
    public function run_cleanup() {
        $retention_days = get_option('aima_data_retention_days', 365);

        $results = array(
            'purchase_history' => $this->cleanup_old_purchases($retention_days),
            'campaign_logs' => $this->cleanup_old_campaigns(180),
            'email_queue' => $this->cleanup_sent_emails(30),
            'order_hashes' => $this->cleanup_old_hashes($retention_days),
            'analytics_temp' => $this->cleanup_temp_data(90)
        );

        // Log cleanup report
        $this->log_cleanup_report($results);

        return $results;
    }

    /**
     * Cleanup old purchase history
     */
    private function cleanup_old_purchases($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_purchase_history';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Archive before deletion (optional)
        $to_archive = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE created_at < %s",
            $date_threshold
        ));

        if (!empty($to_archive)) {
            $this->archive_data('purchase_history', $to_archive);
        }

        // Delete old records
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $date_threshold
        ));

        return array(
            'deleted' => $deleted,
            'archived' => count($to_archive)
        );
    }

    /**
     * Cleanup old campaign logs
     */
    private function cleanup_old_campaigns($days) {
        global $wpdb;
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Archive
        $to_archive = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $recipients_table WHERE sent_at < %s",
            $date_threshold
        ));

        if (!empty($to_archive)) {
            $this->archive_data('campaign_recipients', $to_archive);
        }

        // Delete
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $recipients_table WHERE sent_at < %s",
            $date_threshold
        ));

        return array(
            'deleted' => $deleted,
            'archived' => count($to_archive)
        );
    }

    /**
     * Cleanup sent emails from queue
     */
    private function cleanup_sent_emails($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_email_queue';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE status = 'sent' AND sent_at < %s",
            $date_threshold
        ));

        // Also cleanup old failed attempts
        $deleted_failed = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE status = 'failed' AND created_at < %s",
            $date_threshold
        ));

        return array(
            'deleted' => $deleted + $deleted_failed,
            'archived' => 0
        );
    }

    /**
     * Cleanup old order hashes
     */
    private function cleanup_old_hashes($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_order_hashes';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE order_date < %s",
            $date_threshold
        ));

        return array(
            'deleted' => $deleted,
            'archived' => 0
        );
    }

    /**
     * Cleanup temporary analytics data
     */
    private function cleanup_temp_data($days) {
        global $wpdb;
        $personalized_table = $wpdb->prefix . 'aima_personalized_offers';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete old personalized offers
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $personalized_table WHERE generated_at < %s AND status != 'sent'",
            $date_threshold
        ));

        return array(
            'deleted' => $deleted,
            'archived' => 0
        );
    }

    /**
     * Archive data to JSON files
     */
    private function archive_data($type, $data) {
        $upload_dir = wp_upload_dir();
        $archive_dir = $upload_dir['basedir'] . '/aima-archives';

        if (!file_exists($archive_dir)) {
            wp_mkdir_p($archive_dir);
        }

        $filename = $archive_dir . '/' . $type . '_' . date('Y-m-d') . '.json';

        // Append to file if exists
        if (file_exists($filename)) {
            $existing = json_decode(file_get_contents($filename), true);
            $data = array_merge($existing, $data);
        }

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

        return $filename;
    }

    /**
     * Log cleanup report
     */
    private function log_cleanup_report($results) {
        $total_deleted = 0;
        $total_archived = 0;

        foreach ($results as $type => $result) {
            $total_deleted += $result['deleted'];
            $total_archived += $result['archived'];
        }

        error_log(sprintf(
            'AIMA Cleanup Report: Deleted %d records, Archived %d records',
            $total_deleted,
            $total_archived
        ));

        // Store summary in options
        update_option('aima_last_cleanup', array(
            'date' => current_time('mysql'),
            'results' => $results,
            'total_deleted' => $total_deleted,
            'total_archived' => $total_archived
        ));
    }

    /**
     * Get cleanup statistics
     */
    public function get_cleanup_stats() {
        return get_option('aima_last_cleanup', array(
            'date' => 'Never',
            'results' => array(),
            'total_deleted' => 0,
            'total_archived' => 0
        ));
    }

    /**
     * Get archive files list
     */
    public function get_archives() {
        $upload_dir = wp_upload_dir();
        $archive_dir = $upload_dir['basedir'] . '/aima-archives';

        if (!file_exists($archive_dir)) {
            return array();
        }

        $files = glob($archive_dir . '/*.json');
        $archives = array();

        foreach ($files as $file) {
            $archives[] = array(
                'name' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file)
            );
        }

        return $archives;
    }
}
