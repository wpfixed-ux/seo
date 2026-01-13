<?php
/**
 * Plugin Name: WP Options Table Manager
 * Plugin URI: https://github.com/yourusername/wp-options-manager
 * Description: Диагностика, мониторинг и безопасная очистка таблицы wp_options для оптимизации производительности WordPress/WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-options-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Определение констант плагина
define('WPOM_VERSION', '1.0.0');
define('WPOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPOM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Главный класс плагина WP Options Manager
 */
class WP_Options_Manager {

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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Загрузка зависимостей
     */
    private function load_dependencies() {
        // Загрузка основных классов
        require_once WPOM_PLUGIN_DIR . 'includes/class-diagnostic.php';
        require_once WPOM_PLUGIN_DIR . 'includes/class-cleaner.php';
        require_once WPOM_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPOM_PLUGIN_DIR . 'includes/class-database.php';
    }

    /**
     * Инициализация хуков
     */
    private function init_hooks() {
        // Хук активации плагина
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Хук деактивации плагина
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Загрузка переводов
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Инициализация админ-панели
        if (is_admin()) {
            WPOM_Admin::get_instance();
        }
    }

    /**
     * Активация плагина
     */
    public function activate() {
        // Проверка минимальных требований
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(WPOM_PLUGIN_BASENAME);
            wp_die(__('WP Options Manager требует PHP 7.4 или выше.', 'wp-options-manager'));
        }

        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(WPOM_PLUGIN_BASENAME);
            wp_die(__('WP Options Manager требует WordPress 5.0 или выше.', 'wp-options-manager'));
        }

        // Создание таблиц базы данных
        WPOM_Database::create_tables();

        // Установка версии плагина
        update_option('wpom_version', WPOM_VERSION);

        // Установка настроек по умолчанию
        $default_settings = array(
            'autoload_warning_threshold' => 800, // KB
            'large_option_threshold' => 100, // KB
            'enable_monitoring' => false,
            'enable_auto_cleanup' => false,
            'cleanup_schedule' => 'weekly',
        );

        add_option('wpom_settings', $default_settings);
    }

    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Удаление запланированных задач
        wp_clear_scheduled_hook('wpom_scheduled_cleanup');
    }

    /**
     * Загрузка переводов
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-options-manager',
            false,
            dirname(WPOM_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

/**
 * Инициализация плагина
 */
function wpom_init() {
    return WP_Options_Manager::get_instance();
}

// Запуск плагина
wpom_init();
