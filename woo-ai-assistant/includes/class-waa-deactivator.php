<?php
/**
 * Plugin deactivator
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Deactivator {

    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('waa_daily_reindex');
        wp_clear_scheduled_hook('waa_cleanup_old_chats');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
