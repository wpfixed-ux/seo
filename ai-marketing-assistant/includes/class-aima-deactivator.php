<?php
/**
 * Fired during plugin deactivation
 *
 * @package AIMarketingAssistant
 */

class AIMA_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        flush_rewrite_rules();
    }

    /**
     * Clear all scheduled cron events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook('aima_daily_analytics_update');
        wp_clear_scheduled_hook('aima_hourly_campaign_check');
        wp_clear_scheduled_hook('aima_update_segments');
    }
}
