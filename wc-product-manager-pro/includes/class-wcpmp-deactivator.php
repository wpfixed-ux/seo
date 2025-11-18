<?php
/**
 * Plugin deactivator
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Deactivator {

    public static function deactivate() {
        // Clear scheduled crons
        wp_clear_scheduled_hook('wcpmp_sync_inventory_cron');
        wp_clear_scheduled_hook('wcpmp_fetch_orders_cron');
        wp_clear_scheduled_hook('wcpmp_process_campaigns_cron');

        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wcpmp_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wcpmp_%'");

        flush_rewrite_rules();
    }
}
