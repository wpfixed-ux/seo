<?php
/**
 * Класс для управления админ-панелью
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс WPOM_Admin
 */
class WPOM_Admin {

    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;

    /**
     * Получить экземпляр класса (Singleton)
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Конструктор
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_wpom_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_wpom_clean_transients', array($this, 'ajax_clean_transients'));
        add_action('wp_ajax_wpom_clean_wc_sessions', array($this, 'ajax_clean_wc_sessions'));
        add_action('wp_ajax_wpom_clean_by_pattern', array($this, 'ajax_clean_by_pattern'));
        add_action('wp_ajax_wpom_preview_pattern', array($this, 'ajax_preview_pattern'));
        add_action('wp_ajax_wpom_disable_autoload', array($this, 'ajax_disable_autoload'));
        add_action('wp_ajax_wpom_analyze_plugin', array($this, 'ajax_analyze_plugin'));
        add_action('wp_ajax_wpom_clean_plugin', array($this, 'ajax_clean_plugin'));
    }

    /**
     * Добавление меню в админ-панель
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WP Options Manager', 'wp-options-manager'),
            __('Options Manager', 'wp-options-manager'),
            'manage_options',
            'wp-options-manager',
            array($this, 'render_dashboard_page'),
            'dashicons-database',
            80
        );

        add_submenu_page(
            'wp-options-manager',
            __('Dashboard', 'wp-options-manager'),
            __('Dashboard', 'wp-options-manager'),
            'manage_options',
            'wp-options-manager',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'wp-options-manager',
            __('Diagnostic', 'wp-options-manager'),
            __('Diagnostic', 'wp-options-manager'),
            'manage_options',
            'wpom-diagnostic',
            array($this, 'render_diagnostic_page')
        );

        add_submenu_page(
            'wp-options-manager',
            __('Cleaner', 'wp-options-manager'),
            __('Cleaner', 'wp-options-manager'),
            'manage_options',
            'wpom-cleaner',
            array($this, 'render_cleaner_page')
        );
    }

    /**
     * Подключение CSS и JS
     */
    public function enqueue_admin_assets($hook) {
        // Загружаем только на страницах плагина
        if (strpos($hook, 'wp-options-manager') === false && strpos($hook, 'wpom-') === false) {
            return;
        }

        wp_enqueue_style(
            'wpom-admin-css',
            WPOM_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            WPOM_VERSION
        );

        wp_enqueue_script(
            'wpom-admin-js',
            WPOM_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            WPOM_VERSION,
            true
        );

        wp_localize_script('wpom-admin-js', 'wpomAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpom_ajax_nonce'),
            'strings' => array(
                'confirm_clean' => __('Are you sure you want to clean this data? This action cannot be undone without restoring from backup.', 'wp-options-manager'),
                'confirm_force_clean' => __('WARNING: This will delete ALL WooCommerce sessions including active carts! Are you absolutely sure?', 'wp-options-manager'),
                'loading' => __('Loading...', 'wp-options-manager'),
                'success' => __('Operation completed successfully!', 'wp-options-manager'),
                'error' => __('Error occurred during operation.', 'wp-options-manager'),
            ),
        ));
    }

    /**
     * Рендер страницы Dashboard
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include WPOM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Рендер страницы Diagnostic
     */
    public function render_diagnostic_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include WPOM_PLUGIN_DIR . 'admin/views/diagnostic.php';
    }

    /**
     * Рендер страницы Cleaner
     */
    public function render_cleaner_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include WPOM_PLUGIN_DIR . 'admin/views/cleaner.php';
    }

    /**
     * AJAX: Получение статистики
     */
    public function ajax_get_stats() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $diagnostic = WPOM_Diagnostic::get_instance();

        $data = array(
            'general_stats' => $diagnostic->get_general_stats(),
            'data_by_prefix' => $diagnostic->get_data_by_prefix(),
            'top_large_options' => $diagnostic->get_top_large_options(20),
            'transients_analysis' => $diagnostic->analyze_transients(),
            'wc_sessions' => $diagnostic->analyze_woocommerce_sessions(),
        );

        wp_send_json_success($data);
    }

    /**
     * AJAX: Очистка transients
     */
    public function ajax_clean_transients() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'expired';

        $cleaner = WPOM_Cleaner::get_instance();

        if ($type === 'expired') {
            $result = $cleaner->clean_expired_transients(true);
        } elseif ($type === 'orphaned') {
            $result = $cleaner->clean_orphaned_transients(true);
        } else {
            wp_send_json_error(array('message' => 'Invalid type'));
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Очистка WooCommerce сессий
     */
    public function ajax_clean_wc_sessions() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $force = isset($_POST['force']) && $_POST['force'] === 'true';

        $cleaner = WPOM_Cleaner::get_instance();
        $result = $cleaner->clean_woocommerce_sessions($force, true);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Очистка по паттерну
     */
    public function ajax_clean_by_pattern() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $pattern = isset($_POST['pattern']) ? sanitize_text_field($_POST['pattern']) : '';

        if (empty($pattern)) {
            wp_send_json_error(array('message' => 'Pattern is required'));
        }

        $cleaner = WPOM_Cleaner::get_instance();
        $result = $cleaner->clean_by_pattern($pattern, true);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Предпросмотр очистки по паттерну
     */
    public function ajax_preview_pattern() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $pattern = isset($_POST['pattern']) ? sanitize_text_field($_POST['pattern']) : '';

        if (empty($pattern)) {
            wp_send_json_error(array('message' => 'Pattern is required'));
        }

        $cleaner = WPOM_Cleaner::get_instance();
        $result = $cleaner->preview_pattern_cleanup($pattern);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Отключение autoload для больших опций
     */
    public function ajax_disable_autoload() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $threshold = isset($_POST['threshold']) ? intval($_POST['threshold']) : 100;

        $cleaner = WPOM_Cleaner::get_instance();
        $result = $cleaner->disable_autoload_for_large_options($threshold);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Анализ опций плагина
     */
    public function ajax_analyze_plugin() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';

        if (empty($plugin_slug)) {
            wp_send_json_error(array('message' => 'Plugin slug is required'));
        }

        $cleaner = WPOM_Cleaner::get_instance();
        $result = $cleaner->analyze_plugin_options($plugin_slug);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Очистка опций плагина
     */
    public function ajax_clean_plugin() {
        check_ajax_referer('wpom_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';

        if (empty($plugin_slug)) {
            wp_send_json_error(array('message' => 'Plugin slug is required'));
        }

        $cleaner = WPOM_Cleaner::get_instance();
        $result = $cleaner->clean_plugin_options($plugin_slug, true);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
