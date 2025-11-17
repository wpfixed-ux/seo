<?php
/**
 * Fired during plugin deactivation
 *
 * @package WC_AI_Translator
 */

class WCAT_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('wcat_process_queue');
        wp_clear_scheduled_hook('wcat_cleanup_logs');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
