<?php
class SAP_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, SAP_ASSETS_URL . 'css/admin.css', array(), $this->version);
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, SAP_ASSETS_URL . 'js/admin/admin.js', array('jquery'), $this->version, true);
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'SEO Analytics Pro',
            'SEO Analytics',
            'manage_options',
            'seo-analytics-pro',
            array($this, 'display_plugin_admin_page'),
            'dashicons-chart-line',
            30
        );
    }

    public function display_plugin_admin_page() {
        echo '<div class="wrap"><h1>SEO Analytics Pro</h1><p>Welcome to SEO Analytics Pro!</p></div>';
    }

    public function register_settings() {
        register_setting('sap_settings', 'sap_settings');
    }

    public function ajax_analyze_competitor() {
        wp_send_json_success(array('message' => 'Placeholder'));
    }

    public function ajax_analyze_keyword() {
        wp_send_json_success(array('message' => 'Placeholder'));
    }

    public function ajax_generate_content_spec() {
        wp_send_json_success(array('message' => 'Placeholder'));
    }

    public function ajax_import_keywords() {
        wp_send_json_success(array('message' => 'Placeholder'));
    }
}
