<?php
/**
 * Admin functionality
 *
 * @package WC_AI_Translator
 */

class WCAT_Admin {

    /**
     * Plugin name
     */
    private $plugin_name;

    /**
     * Plugin version
     */
    private $version;

    /**
     * Constructor
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'wc-ai-translator') === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            WCAT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wc-ai-translator') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            WCAT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'wcatAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcat_admin_nonce'),
                'strings' => array(
                    'confirm_translate' => __('Are you sure you want to translate the selected items?', 'wc-ai-translator'),
                    'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'wc-ai-translator'),
                    'translating' => __('Translating...', 'wc-ai-translator'),
                    'loading' => __('Loading...', 'wc-ai-translator'),
                    'error' => __('An error occurred', 'wc-ai-translator'),
                    'success' => __('Operation completed successfully', 'wc-ai-translator'),
                ),
            )
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('WC AI Translator', 'wc-ai-translator'),
            __('AI Translator', 'wc-ai-translator'),
            'manage_options',
            'wc-ai-translator',
            array($this, 'display_dashboard_page'),
            'dashicons-translation',
            56
        );

        // Dashboard submenu
        add_submenu_page(
            'wc-ai-translator',
            __('Dashboard', 'wc-ai-translator'),
            __('Dashboard', 'wc-ai-translator'),
            'manage_options',
            'wc-ai-translator',
            array($this, 'display_dashboard_page')
        );

        // Batch Translate submenu
        add_submenu_page(
            'wc-ai-translator',
            __('Batch Translate', 'wc-ai-translator'),
            __('Batch Translate', 'wc-ai-translator'),
            'manage_options',
            'wc-ai-translator-batch',
            array($this, 'display_batch_page')
        );

        // Queue submenu
        add_submenu_page(
            'wc-ai-translator',
            __('Translation Queue', 'wc-ai-translator'),
            __('Queue', 'wc-ai-translator'),
            'manage_options',
            'wc-ai-translator-queue',
            array($this, 'display_queue_page')
        );

        // Logs submenu
        add_submenu_page(
            'wc-ai-translator',
            __('Translation Logs', 'wc-ai-translator'),
            __('Logs', 'wc-ai-translator'),
            'manage_options',
            'wc-ai-translator-logs',
            array($this, 'display_logs_page')
        );

        // Settings submenu
        add_submenu_page(
            'wc-ai-translator',
            __('Settings', 'wc-ai-translator'),
            __('Settings', 'wc-ai-translator'),
            'manage_options',
            'wc-ai-translator-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        include WCAT_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
    }

    /**
     * Display batch translation page
     */
    public function display_batch_page() {
        include WCAT_PLUGIN_DIR . 'includes/admin/views/batch.php';
    }

    /**
     * Display queue page
     */
    public function display_queue_page() {
        include WCAT_PLUGIN_DIR . 'includes/admin/views/queue.php';
    }

    /**
     * Display logs page
     */
    public function display_logs_page() {
        include WCAT_PLUGIN_DIR . 'includes/admin/views/logs.php';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        include WCAT_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }
}
