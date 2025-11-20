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

        // Get AI client for provider/model info
        $ai_client = new AIMA_AI_Client();
        $providers = $ai_client->get_providers();
        $current_provider = get_option('aima_ai_provider', 'anthropic');
        $current_models = $ai_client->get_models($current_provider);

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

    /**
     * Test API connection via AJAX
     */
    public function test_api_connection() {
        check_ajax_referer('aima-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'ai-marketing-assistant')));
        }

        // Temporarily update settings for test
        $provider = sanitize_text_field($_POST['provider']);
        $model = sanitize_text_field($_POST['model']);
        $api_key = sanitize_text_field($_POST['api_key']);

        $old_provider = get_option('aima_ai_provider');
        $old_model = get_option('aima_ai_model');
        $old_key = get_option('aima_ai_api_key');

        update_option('aima_ai_provider', $provider);
        update_option('aima_ai_model', $model);
        update_option('aima_ai_api_key', $api_key);

        // Test connection
        $ai_client = new AIMA_AI_Client();
        $result = $ai_client->test_connection();

        // Restore old settings
        update_option('aima_ai_provider', $old_provider);
        update_option('aima_ai_model', $old_model);
        update_option('aima_ai_api_key', $old_key);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get models for provider via AJAX
     */
    public function get_provider_models() {
        check_ajax_referer('aima-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'ai-marketing-assistant')));
        }

        $provider = sanitize_text_field($_POST['provider']);

        $ai_client = new AIMA_AI_Client();
        $models = $ai_client->get_models($provider);

        wp_send_json_success(array('models' => $models));
    }
}
