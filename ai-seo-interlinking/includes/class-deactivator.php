<?php
/**
 * Plugin Deactivator Class
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        self::unschedule_cron_jobs();
        self::clear_cache();

        flush_rewrite_rules();
    }

    /**
     * Unschedule cron jobs
     */
    private static function unschedule_cron_jobs() {
        $timestamp = wp_next_scheduled('ail_batch_processing_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ail_batch_processing_cron');
        }

        $timestamp = wp_next_scheduled('ail_log_cleanup_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ail_log_cleanup_cron');
        }
    }

    /**
     * Clear plugin cache
     */
    private static function clear_cache() {
        global $wpdb;

        // Delete all transients related to the plugin
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_ail_%'
            OR option_name LIKE '_transient_timeout_ail_%'"
        );
    }

    /**
     * Complete uninstall (called from uninstall.php)
     */
    public static function uninstall() {
        global $wpdb;

        // Drop tables
        $table_prefix = $wpdb->prefix;
        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}ai_interlinking_keywords");
        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}ai_interlinking_links");
        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}ai_interlinking_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}ai_interlinking_settings");

        // Delete options
        delete_option('ail_settings');
        delete_option('ail_activation_time');
        delete_option('ail_version');

        // Clear all transients
        self::clear_cache();
    }
}
