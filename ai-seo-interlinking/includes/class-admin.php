<?php
/**
 * Admin Class
 * Handles admin interface and settings
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Admin {

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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);

        // AJAX handlers
        add_action('wp_ajax_ail_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ail_generate_keywords', [$this, 'ajax_generate_keywords']);
        add_action('wp_ajax_ail_process_batch', [$this, 'ajax_process_batch']);
        add_action('wp_ajax_ail_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_ail_get_statistics', [$this, 'ajax_get_statistics']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI SEO Interlinking', 'ai-seo-interlinking'),
            __('AI Interlinking', 'ai-seo-interlinking'),
            'manage_options',
            'ai-seo-interlinking',
            [$this, 'render_settings_page'],
            'dashicons-admin-links',
            30
        );

        add_submenu_page(
            'ai-seo-interlinking',
            __('Settings', 'ai-seo-interlinking'),
            __('Settings', 'ai-seo-interlinking'),
            'manage_options',
            'ai-seo-interlinking',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'ai-seo-interlinking',
            __('Reports', 'ai-seo-interlinking'),
            __('Reports', 'ai-seo-interlinking'),
            'manage_options',
            'ai-seo-interlinking-reports',
            [$this, 'render_reports_page']
        );

        add_submenu_page(
            'ai-seo-interlinking',
            __('Logs', 'ai-seo-interlinking'),
            __('Logs', 'ai-seo-interlinking'),
            'manage_options',
            'ai-seo-interlinking-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ai-seo-interlinking') === false) {
            return;
        }

        wp_enqueue_style(
            'ail-admin-css',
            AIL_PLUGIN_URL . 'admin/css/admin.css',
            [],
            AIL_VERSION
        );

        wp_enqueue_script(
            'ail-admin-js',
            AIL_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            AIL_VERSION,
            true
        );

        wp_localize_script('ail-admin-js', 'ailAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ail_admin_nonce'),
            'strings' => [
                'testing' => __('Testing connection...', 'ai-seo-interlinking'),
                'success' => __('Success!', 'ai-seo-interlinking'),
                'error' => __('Error', 'ai-seo-interlinking'),
                'processing' => __('Processing...', 'ai-seo-interlinking'),
                'confirm' => __('Are you sure?', 'ai-seo-interlinking'),
            ],
        ]);
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ail_settings_group', 'ail_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        // Get existing settings first
        $existing = get_option('ail_settings', []);
        $sanitized = [];

        // API Settings - preserve existing key if input is empty
        if (isset($input['openai_api_key']) && !empty($input['openai_api_key'])) {
            $api_key = sanitize_text_field($input['openai_api_key']);
            // Only encrypt if it's not already encrypted and not empty
            if (!empty($api_key)) {
                $sanitized['openai_api_key'] = AIL_AI_Processor::encrypt_api_key($api_key);
            }
        } else {
            // Preserve existing API key if not updating
            if (isset($existing['openai_api_key'])) {
                $sanitized['openai_api_key'] = $existing['openai_api_key'];
            }
        }

        if (isset($input['openai_model'])) {
            $sanitized['openai_model'] = sanitize_text_field($input['openai_model']);
        }

        if (isset($input['max_tokens'])) {
            $sanitized['max_tokens'] = absint($input['max_tokens']);
        }

        // AI Prompt Template
        if (isset($input['ai_prompt_template'])) {
            $sanitized['ai_prompt_template'] = wp_kses_post($input['ai_prompt_template']);
        }

        // Linking Settings
        if (isset($input['linking_strategy'])) {
            $sanitized['linking_strategy'] = sanitize_text_field($input['linking_strategy']);
        }

        if (isset($input['max_outbound_links'])) {
            $sanitized['max_outbound_links'] = absint($input['max_outbound_links']);
        }

        if (isset($input['max_inbound_links'])) {
            $sanitized['max_inbound_links'] = absint($input['max_inbound_links']);
        }

        if (isset($input['min_content_length'])) {
            $sanitized['min_content_length'] = absint($input['min_content_length']);
        }

        // Scheduler Settings
        if (isset($input['scheduler_enabled'])) {
            $sanitized['scheduler_enabled'] = (bool) $input['scheduler_enabled'];
        }

        if (isset($input['scheduler_frequency'])) {
            $sanitized['scheduler_frequency'] = sanitize_text_field($input['scheduler_frequency']);
        }

        if (isset($input['scheduler_time'])) {
            $sanitized['scheduler_time'] = sanitize_text_field($input['scheduler_time']);
        }

        if (isset($input['batch_size'])) {
            $sanitized['batch_size'] = absint($input['batch_size']);
        }

        // Logging Settings
        if (isset($input['enable_logging'])) {
            $sanitized['enable_logging'] = (bool) $input['enable_logging'];
        }

        if (isset($input['log_retention_days'])) {
            $sanitized['log_retention_days'] = absint($input['log_retention_days']);
        }

        // Language Settings
        if (isset($input['active_languages']) && is_array($input['active_languages'])) {
            $sanitized['active_languages'] = array_map('sanitize_text_field', $input['active_languages']);
        }

        if (isset($input['default_language'])) {
            $sanitized['default_language'] = sanitize_text_field($input['default_language']);
        }

        if (isset($input['language_detection'])) {
            $sanitized['language_detection'] = sanitize_text_field($input['language_detection']);
        }

        // Merge with existing settings (existing already loaded at top of function)
        return array_merge($existing, $sanitized);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include AIL_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include AIL_PLUGIN_DIR . 'admin/views/reports-page.php';
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $logs = AIL_Logger::get_logs([
            'limit' => 100,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ]);

        include AIL_PLUGIN_DIR . 'admin/views/logs-page.php';
    }

    /**
     * AJAX: Test OpenAI connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('ail_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-interlinking')]);
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        // If no key provided in request, try to use the saved key
        if (empty($api_key)) {
            $settings = get_option('ail_settings', []);
            if (empty($settings['openai_api_key'])) {
                wp_send_json_error(['message' => __('API key is required. Please enter an API key or save one first.', 'ai-seo-interlinking')]);
            }
            // Use saved key - reload instance to get current settings
            $ai = AIL_AI_Processor::reload_instance();
            $result = $ai->test_connection();
        } else {
            // Test with provided key (temporarily)
            $settings = get_option('ail_settings', []);
            $old_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
            $settings['openai_api_key'] = AIL_AI_Processor::encrypt_api_key($api_key);
            update_option('ail_settings', $settings);

            // Reload instance to pick up new key
            $ai = AIL_AI_Processor::reload_instance();
            $result = $ai->test_connection();

            // Restore old key if test failed
            if (!$result['success']) {
                $settings['openai_api_key'] = $old_key;
                update_option('ail_settings', $settings);
                // Reload again to restore old key in instance
                AIL_AI_Processor::reload_instance();
            }
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Generate keywords
     */
    public function ajax_generate_keywords() {
        check_ajax_referer('ail_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-interlinking')]);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID', 'ai-seo-interlinking')]);
        }

        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(['message' => __('Post not found', 'ai-seo-interlinking')]);
        }

        $language = AIL_Multilang_Support::get_post_language($post_id);
        $category = '';

        if ($post->post_type === 'product') {
            $terms = wp_get_post_terms($post_id, 'product_cat');
            if (!empty($terms)) {
                $category = $terms[0]->name;
            }
        }

        $ai = AIL_AI_Processor::get_instance();
        $keywords = $ai->generate_keywords($post->post_content, $post->post_title, $category, $language);

        if (is_wp_error($keywords)) {
            wp_send_json_error(['message' => $keywords->get_error_message()]);
        }

        // Save keywords to database
        global $wpdb;
        $table = $wpdb->prefix . 'ai_interlinking_keywords';

        foreach ($keywords as $keyword_data) {
            $wpdb->insert(
                $table,
                [
                    'keyword' => $keyword_data['keyword'],
                    'language' => $language,
                    'post_id' => $post_id,
                    'keyword_type' => $keyword_data['type'],
                ],
                ['%s', '%s', '%d', '%s']
            );
        }

        wp_send_json_success([
            'keywords' => $keywords,
            'count' => count($keywords),
        ]);
    }

    /**
     * AJAX: Process batch
     */
    public function ajax_process_batch() {
        check_ajax_referer('ail_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-interlinking')]);
        }

        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 10;

        $scheduler = AIL_Scheduler::get_instance();
        $result = $scheduler->process_batch($batch_size);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('ail_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-interlinking')]);
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
        $sanitized = $this->sanitize_settings($settings);

        update_option('ail_settings', $sanitized);

        // Reschedule cron if scheduler settings changed
        if (isset($settings['scheduler_enabled']) || isset($settings['scheduler_frequency']) || isset($settings['scheduler_time'])) {
            $scheduler = AIL_Scheduler::get_instance();
            $scheduler->reschedule();
        }

        wp_send_json_success(['message' => __('Settings saved', 'ai-seo-interlinking')]);
    }

    /**
     * AJAX: Get statistics
     */
    public function ajax_get_statistics() {
        check_ajax_referer('ail_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-interlinking')]);
        }

        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;

        $stats = AIL_Logger::get_statistics($days);
        $lang_stats = AIL_Multilang_Support::get_language_statistics();

        wp_send_json_success([
            'general' => $stats,
            'languages' => $lang_stats,
        ]);
    }
}
