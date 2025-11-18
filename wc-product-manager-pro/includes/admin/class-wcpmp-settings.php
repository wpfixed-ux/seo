<?php
/**
 * Settings management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Settings {

    /**
     * Get all settings
     */
    public static function get_all() {
        return array(
            'openai_api_key' => get_option('wcpmp_openai_api_key', ''),
            'openai_model' => get_option('wcpmp_openai_model', 'gpt-4o'),
            'gemini_api_key' => get_option('wcpmp_gemini_api_key', ''),
            'telegram_bot_token' => get_option('wcpmp_telegram_bot_token', ''),
            'default_language' => get_option('wcpmp_default_language', 'uk'),
            'sync_interval' => get_option('wcpmp_sync_interval', 'hourly'),
            'global_sync_enabled' => get_option('wcpmp_global_sync_enabled', 1),
            'orders_fetch_interval' => get_option('wcpmp_orders_fetch_interval', 'hourly'),
            'email_from_name' => get_option('wcpmp_email_from_name', get_bloginfo('name')),
            'email_from_address' => get_option('wcpmp_email_from_address', get_option('admin_email')),
            'seo_description_length' => get_option('wcpmp_seo_description_length', 160),
            'ai_temperature' => get_option('wcpmp_ai_temperature', 0.7),
        );
    }

    /**
     * Update setting
     */
    public static function update($key, $value) {
        return update_option('wcpmp_' . $key, $value);
    }

    /**
     * Get setting
     */
    public static function get($key, $default = '') {
        return get_option('wcpmp_' . $key, $default);
    }

    /**
     * Validate API key
     */
    public static function validate_openai_key($key) {
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Validate Gemini API key
     */
    public static function validate_gemini_key($key) {
        $response = wp_remote_get(
            'https://generativelanguage.googleapis.com/v1beta/models?key=' . $key,
            array('timeout' => 10)
        );

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get available OpenAI models
     */
    public static function get_openai_models() {
        return array(
            'gpt-4o' => 'GPT-4o (Recommended)',
            'gpt-4o-mini' => 'GPT-4o Mini (Faster)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Budget)'
        );
    }

    /**
     * Get sync intervals
     */
    public static function get_sync_intervals() {
        return array(
            'every_fifteen_minutes' => __('Every 15 Minutes', 'wc-product-manager-pro'),
            'hourly' => __('Hourly', 'wc-product-manager-pro'),
            'twicedaily' => __('Twice Daily', 'wc-product-manager-pro'),
            'daily' => __('Daily', 'wc-product-manager-pro')
        );
    }

    /**
     * Get languages
     */
    public static function get_languages() {
        return array(
            'uk' => __('Ukrainian', 'wc-product-manager-pro'),
            'ru' => __('Russian', 'wc-product-manager-pro')
        );
    }
}
