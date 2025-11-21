<?php
/**
 * Plugin Name: AI Image Generator
 * Plugin URI: https://github.com/yourusername/ai-image-generator
 * Description: Генерация изображений для статей через Gemini AI и замена фона товаров с настраиваемым водяным знаком
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-image-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Определение констант плагина
define('AIMG_VERSION', '1.0.0');
define('AIMG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIMG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIMG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Главный класс плагина AI Image Generator
 */
class AI_Image_Generator {

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
        require_once AIMG_PLUGIN_DIR . 'includes/class-gemini-api.php';
        require_once AIMG_PLUGIN_DIR . 'includes/class-image-generator.php';
        require_once AIMG_PLUGIN_DIR . 'includes/class-background-remover.php';
        require_once AIMG_PLUGIN_DIR . 'includes/class-watermark.php';
        require_once AIMG_PLUGIN_DIR . 'includes/class-admin.php';
        require_once AIMG_PLUGIN_DIR . 'includes/class-metaboxes.php';
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
            AIMG_Admin::get_instance();
            AIMG_Metaboxes::get_instance();
        }
    }

    /**
     * Активация плагина
     */
    public function activate() {
        // Проверка минимальных требований
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(AIMG_PLUGIN_BASENAME);
            wp_die(__('AI Image Generator требует PHP 7.4 или выше.', 'ai-image-generator'));
        }

        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(AIMG_PLUGIN_BASENAME);
            wp_die(__('AI Image Generator требует WordPress 5.0 или выше.', 'ai-image-generator'));
        }

        // Установка версии плагина
        update_option('aimg_version', AIMG_VERSION);

        // Установка настроек по умолчанию
        $default_settings = array(
            // Gemini API
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-pro',

            // Image Generation API (можно выбрать)
            'image_api_provider' => 'stability', // stability, dalle, midjourney
            'image_api_key' => '',

            // Background Removal API
            'bg_removal_provider' => 'removebg', // removebg, clipdrop
            'bg_removal_api_key' => '',

            // Watermark
            'watermark_enabled' => false,
            'watermark_text' => get_bloginfo('name'),
            'watermark_image' => '',
            'watermark_position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
            'watermark_opacity' => 50,
            'watermark_size' => 'medium', // small, medium, large

            // Post Images
            'auto_generate_post_images' => false,
            'post_image_style' => 'realistic', // realistic, illustration, abstract, minimal
            'post_image_size' => array('width' => 1200, 'height' => 630),

            // Product Images
            'product_bg_presets' => array(
                'white' => array('type' => 'solid', 'color' => '#FFFFFF'),
                'gradient_blue' => array('type' => 'gradient', 'colors' => array('#667eea', '#764ba2')),
                'gradient_sunset' => array('type' => 'gradient', 'colors' => array('#f12711', '#f5af19')),
                '3d_studio' => array('type' => '3d', 'style' => 'studio'),
            ),
        );

        add_option('aimg_settings', $default_settings);

        // Создание папки для кэша изображений
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/ai-image-generator-cache';

        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
            // Защита папки
            file_put_contents($cache_dir . '/.htaccess', 'deny from all');
            file_put_contents($cache_dir . '/index.php', '<?php // Silence is golden.');
        }
    }

    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Очистка временных файлов можно добавить при необходимости
    }

    /**
     * Загрузка переводов
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-image-generator',
            false,
            dirname(AIMG_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Получение настроек
     */
    public static function get_settings() {
        return get_option('aimg_settings', array());
    }

    /**
     * Получение конкретной настройки
     */
    public static function get_setting($key, $default = null) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Обновление настройки
     */
    public static function update_setting($key, $value) {
        $settings = self::get_settings();
        $settings[$key] = $value;
        update_option('aimg_settings', $settings);
    }
}

/**
 * Инициализация плагина
 */
function aimg_init() {
    return AI_Image_Generator::get_instance();
}

// Запуск плагина
aimg_init();
