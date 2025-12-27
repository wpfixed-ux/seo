<?php
/**
 * Scheduler Class
 * Handles batch processing and scheduled tasks
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Scheduler {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('ail_batch_processing_cron', [$this, 'process_batch']);
        add_action('ail_log_cleanup_cron', [$this, 'cleanup_logs']);
    }

    /**
     * Schedule batch processing
     *
     * @param string $frequency Frequency (daily, weekly, monthly)
     * @param string $time      Time to run (HH:MM)
     * @return bool             Success status
     */
    public function schedule_batch_processing($frequency = 'daily', $time = '02:00') {
        // Clear existing schedule
        $timestamp = wp_next_scheduled('ail_batch_processing_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ail_batch_processing_cron');
        }

        // Calculate next run time
        list($hour, $minute) = explode(':', $time);
        $next_run = strtotime("today {$hour}:{$minute}:00");

        if ($next_run < time()) {
            $next_run = strtotime("tomorrow {$hour}:{$minute}:00");
        }

        // Schedule new event
        $scheduled = wp_schedule_event($next_run, $frequency, 'ail_batch_processing_cron');

        if ($scheduled) {
            AIL_Logger::log_operation(
                'schedule_created',
                ['frequency' => $frequency, 'time' => $time, 'next_run' => date('Y-m-d H:i:s', $next_run)]
            );
        }

        return $scheduled !== false;
    }

    /**
     * Unschedule batch processing
     *
     * @return bool Success status
     */
    public function unschedule_batch_processing() {
        $timestamp = wp_next_scheduled('ail_batch_processing_cron');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ail_batch_processing_cron');
            AIL_Logger::log_operation('schedule_removed', ['timestamp' => $timestamp]);
            return true;
        }

        return false;
    }

    /**
     * Process batch of posts
     *
     * @param int $batch_size Number of posts to process
     * @return array          Processing results
     */
    public function process_batch($batch_size = null) {
        $settings = get_option('ail_settings', []);

        if (!isset($settings['scheduler_enabled']) || !$settings['scheduler_enabled']) {
            return ['error' => 'Scheduler is disabled'];
        }

        if ($batch_size === null) {
            $batch_size = isset($settings['batch_size']) ? intval($settings['batch_size']) : 50;
        }

        $batch_delay = isset($settings['batch_delay']) ? intval($settings['batch_delay']) : 2;
        $process_new_only = isset($settings['process_new_only']) ? $settings['process_new_only'] : false;

        $start_time = microtime(true);
        $results = [
            'processed' => 0,
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];

        // Get posts to process
        $posts = $this->get_posts_to_process($batch_size, $process_new_only);

        if (empty($posts)) {
            AIL_Logger::log_operation('batch_processing', ['message' => 'No posts to process']);
            return $results;
        }

        $link_builder = new AIL_Link_Builder();

        foreach ($posts as $post_id) {
            $results['processed']++;

            $result = $link_builder->build_links($post_id);

            if (is_wp_error($result)) {
                $results['errors']++;
            } elseif ($result['links_created'] > 0) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }

            // Delay between posts to avoid API rate limits
            if ($batch_delay > 0 && $results['processed'] < count($posts)) {
                sleep($batch_delay);
            }
        }

        $elapsed_time = microtime(true) - $start_time;

        AIL_Logger::log_operation(
            'batch_processing',
            [
                'batch_size' => $batch_size,
                'processed' => $results['processed'],
                'success' => $results['success'],
                'errors' => $results['errors'],
                'skipped' => $results['skipped'],
                'elapsed_time' => round($elapsed_time, 2),
            ]
        );

        return $results;
    }

    /**
     * Get posts to process
     *
     * @param int  $limit         Number of posts
     * @param bool $new_only      Process only new posts
     * @return array              Array of post IDs
     */
    private function get_posts_to_process($limit, $new_only = false) {
        global $wpdb;

        $args = [
            'post_type' => ['post', 'page', 'product'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
        ];

        if ($new_only) {
            // Get posts modified in last 7 days
            $args['date_query'] = [
                [
                    'column' => 'post_modified',
                    'after' => '7 days ago',
                ],
            ];
        }

        // Exclude posts that were recently processed
        $links_table = $wpdb->prefix . 'ai_interlinking_links';
        $recently_processed = $wpdb->get_col(
            "SELECT DISTINCT source_post_id FROM {$links_table}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            LIMIT 100"
        );

        if (!empty($recently_processed)) {
            $args['post__not_in'] = $recently_processed;
        }

        $query = new WP_Query($args);

        return $query->posts;
    }

    /**
     * Process single post immediately
     *
     * @param int $post_id Post ID
     * @return array|WP_Error Result
     */
    public function process_single_post($post_id) {
        $link_builder = new AIL_Link_Builder();
        $result = $link_builder->build_links($post_id);

        if (is_wp_error($result)) {
            AIL_Logger::log_operation(
                'single_post_processing',
                ['post_id' => $post_id, 'error' => $result->get_error_message()],
                0,
                'error'
            );
        } else {
            AIL_Logger::log_operation(
                'single_post_processing',
                ['post_id' => $post_id, 'links_created' => $result['links_created']]
            );
        }

        return $result;
    }

    /**
     * Process all posts (use with caution on large sites)
     *
     * @return array Processing results
     */
    public function process_all_posts() {
        $settings = get_option('ail_settings', []);
        $batch_size = isset($settings['batch_size']) ? intval($settings['batch_size']) : 50;
        $batch_delay = isset($settings['batch_delay']) ? intval($settings['batch_delay']) : 2;

        $total_results = [
            'processed' => 0,
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'batches' => 0,
        ];

        $start_time = time();

        // Process in batches
        do {
            $batch_results = $this->process_batch($batch_size);
            $total_results['batches']++;
            $total_results['processed'] += $batch_results['processed'];
            $total_results['success'] += $batch_results['success'];
            $total_results['errors'] += $batch_results['errors'];
            $total_results['skipped'] += $batch_results['skipped'];

            // Break if no posts were processed
            if ($batch_results['processed'] === 0) {
                break;
            }

            // Delay between batches
            sleep($batch_delay);

            // Safety: stop after 5 minutes
            if (time() - $start_time > 300) {
                break;
            }

        } while ($batch_results['processed'] > 0);

        AIL_Logger::log_operation('process_all_posts', $total_results);

        return $total_results;
    }

    /**
     * Cleanup old logs (cron job)
     */
    public function cleanup_logs() {
        $settings = get_option('ail_settings', []);
        $retention_days = isset($settings['log_retention_days']) ? intval($settings['log_retention_days']) : 30;

        $deleted = AIL_Logger::auto_cleanup($retention_days);

        AIL_Logger::log_operation(
            'log_cleanup',
            ['retention_days' => $retention_days, 'deleted_rows' => $deleted]
        );
    }

    /**
     * Get next scheduled run time
     *
     * @return int|false Timestamp or false
     */
    public function get_next_scheduled_run() {
        return wp_next_scheduled('ail_batch_processing_cron');
    }

    /**
     * Get schedule status
     *
     * @return array Status information
     */
    public function get_schedule_status() {
        $next_run = $this->get_next_scheduled_run();
        $settings = get_option('ail_settings', []);

        return [
            'enabled' => isset($settings['scheduler_enabled']) ? $settings['scheduler_enabled'] : false,
            'frequency' => isset($settings['scheduler_frequency']) ? $settings['scheduler_frequency'] : 'daily',
            'time' => isset($settings['scheduler_time']) ? $settings['scheduler_time'] : '02:00',
            'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
            'next_run_timestamp' => $next_run,
        ];
    }

    /**
     * Reschedule based on settings
     */
    public function reschedule() {
        $settings = get_option('ail_settings', []);

        if (!isset($settings['scheduler_enabled']) || !$settings['scheduler_enabled']) {
            $this->unschedule_batch_processing();
            return false;
        }

        $frequency = isset($settings['scheduler_frequency']) ? $settings['scheduler_frequency'] : 'daily';
        $time = isset($settings['scheduler_time']) ? $settings['scheduler_time'] : '02:00';

        return $this->schedule_batch_processing($frequency, $time);
    }
}
