<?php
/**
 * Core plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Core {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
    }

    private function load_dependencies() {
        // Database
        require_once WAA_PLUGIN_DIR . 'includes/database/class-waa-vector-db.php';

        // API providers
        require_once WAA_PLUGIN_DIR . 'includes/api/class-waa-api-base.php';
        require_once WAA_PLUGIN_DIR . 'includes/api/class-waa-openai.php';
        require_once WAA_PLUGIN_DIR . 'includes/api/class-waa-claude.php';
        require_once WAA_PLUGIN_DIR . 'includes/api/class-waa-kimi.php';
        require_once WAA_PLUGIN_DIR . 'includes/api/class-waa-rest-api.php';

        // Indexer
        require_once WAA_PLUGIN_DIR . 'includes/indexer/class-waa-indexer.php';

        // Assistant
        require_once WAA_PLUGIN_DIR . 'includes/assistant/class-waa-assistant.php';

        // Shortcodes
        require_once WAA_PLUGIN_DIR . 'includes/shortcodes/class-waa-shortcodes.php';

        // Admin
        if (is_admin()) {
            require_once WAA_PLUGIN_DIR . 'includes/admin/class-waa-admin.php';
            require_once WAA_PLUGIN_DIR . 'includes/admin/class-waa-settings.php';
        }
    }

    private function init_components() {
        // Initialize vector database
        WAA_Vector_DB::get_instance();

        // Initialize REST API
        WAA_REST_API::get_instance();

        // Initialize indexer
        WAA_Indexer::get_instance();

        // Initialize assistant
        WAA_Assistant::get_instance();

        // Initialize shortcodes
        WAA_Shortcodes::get_instance();

        // Initialize admin
        if (is_admin()) {
            WAA_Admin::get_instance();
            WAA_Settings::get_instance();
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_public_assets() {
        wp_enqueue_style(
            'waa-public',
            WAA_PLUGIN_URL . 'assets/css/public.css',
            array(),
            WAA_VERSION
        );

        wp_enqueue_script(
            'waa-public',
            WAA_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            WAA_VERSION,
            true
        );

        wp_localize_script('waa-public', 'waaConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('waa/v1/'),
            'nonce' => wp_create_nonce('waa_nonce'),
            'i18n' => array(
                'placeholder' => __('Задайте вопрос о товарах...', 'woo-ai-assistant'),
                'send' => __('Отправить', 'woo-ai-assistant'),
                'thinking' => __('Думаю...', 'woo-ai-assistant'),
                'error' => __('Произошла ошибка. Попробуйте снова.', 'woo-ai-assistant'),
                'welcome' => __('Здравствуйте! Я AI-консультант. Чем могу помочь с выбором товара?', 'woo-ai-assistant'),
            )
        ));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'waa-') === false && $hook !== 'toplevel_page_waa-dashboard') {
            return;
        }

        wp_enqueue_style(
            'waa-admin',
            WAA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WAA_VERSION
        );

        wp_enqueue_script(
            'waa-admin',
            WAA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WAA_VERSION,
            true
        );

        wp_localize_script('waa-admin', 'waaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('waa/v1/'),
            'nonce' => wp_create_nonce('waa_admin_nonce'),
            'i18n' => array(
                'indexing' => __('Индексация...', 'woo-ai-assistant'),
                'indexed' => __('Проиндексировано', 'woo-ai-assistant'),
                'error' => __('Ошибка', 'woo-ai-assistant'),
                'confirm_reindex' => __('Переиндексировать все товары и статьи?', 'woo-ai-assistant'),
            )
        ));
    }

    /**
     * Get AI provider instance based on settings
     */
    public static function get_ai_provider() {
        $provider = get_option('waa_ai_provider', 'openai');

        switch ($provider) {
            case 'claude':
                return WAA_Claude::get_instance();
            case 'kimi':
                return WAA_Kimi::get_instance();
            case 'openai':
            default:
                return WAA_OpenAI::get_instance();
        }
    }
}
