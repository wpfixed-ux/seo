<?php
/**
 * Fired during plugin deactivation
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes
 */

class SAP_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Cleans up scheduled tasks and temporary data.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('sap_daily_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'sap_daily_tasks');
        }

        $timestamp = wp_next_scheduled('sap_process_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'sap_process_queue');
        }

        // Clear transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sap_%' OR option_name LIKE '_transient_timeout_sap_%'"
        );

        // Flush rewrite rules
        flush_rewrite_rules();

        // Note: We don't delete database tables or options on deactivation
        // That should only happen on uninstall
    }
}
