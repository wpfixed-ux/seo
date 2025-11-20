<?php
/**
 * Settings admin page
 *
 * @package AIMarketingAssistant
 */

class AIMA_Admin_Settings {

    /**
     * Render settings page
     */
    public function render_page() {
        if (isset($_POST['save_settings'])) {
            $this->save_settings();
        }

        include AIMA_ADMIN_DIR . 'views/settings.php';
    }

    /**
     * Save settings
     */
    private function save_settings() {
        check_admin_referer('aima_save_settings');

        $settings = array(
            'aima_ai_provider' => sanitize_text_field($_POST['aima_ai_provider']),
            'aima_ai_model' => sanitize_text_field($_POST['aima_ai_model']),
            'aima_ai_api_key' => sanitize_text_field($_POST['aima_ai_api_key']),
            'aima_telegram_bot_token' => sanitize_text_field($_POST['aima_telegram_bot_token']),
            'aima_telegram_channel_id' => sanitize_text_field($_POST['aima_telegram_channel_id']),
            'aima_email_from_name' => sanitize_text_field($_POST['aima_email_from_name']),
            'aima_email_from_email' => sanitize_email($_POST['aima_email_from_email']),
            'aima_campaign_frequency' => sanitize_text_field($_POST['aima_campaign_frequency']),
            'aima_min_purchase_count' => intval($_POST['aima_min_purchase_count']),
            'aima_analytics_days' => intval($_POST['aima_analytics_days']),
        );

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        add_settings_error(
            'aima_messages',
            'aima_message',
            __('Settings saved successfully', 'ai-marketing-assistant'),
            'updated'
        );
    }
}
