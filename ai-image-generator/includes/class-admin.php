<?php
/**
 * Класс для админ-панели
 *
 * @package AI_Image_Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIMG_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_aimg_generate_post_image', array($this, 'ajax_generate_post_image'));
        add_action('wp_ajax_aimg_replace_product_background', array($this, 'ajax_replace_product_background'));
        add_action('wp_ajax_aimg_test_gemini_api', array($this, 'ajax_test_gemini_api'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('AI Image Generator', 'ai-image-generator'),
            __('AI Images', 'ai-image-generator'),
            'manage_options',
            'ai-image-generator',
            array($this, 'render_settings_page'),
            'dashicons-format-image',
            80
        );
    }

    public function register_settings() {
        register_setting('aimg_settings_group', 'aimg_settings');
    }

    public function enqueue_admin_assets($hook) {
        // Загружаем на всех страницах редактирования постов и товаров
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_style('aimg-admin-css', AIMG_PLUGIN_URL . 'admin/css/admin.css', array(), AIMG_VERSION);
            wp_enqueue_script('aimg-admin-js', AIMG_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), AIMG_VERSION, true);

            wp_localize_script('aimg-admin-js', 'aimgAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aimg_ajax_nonce'),
                'strings' => array(
                    'generating' => __('Генерация изображения...', 'ai-image-generator'),
                    'success' => __('Изображение успешно сгенерировано!', 'ai-image-generator'),
                    'error' => __('Ошибка генерации', 'ai-image-generator'),
                ),
            ));
        }

        // Загружаем на странице настроек
        if ($hook === 'toplevel_page_ai-image-generator') {
            wp_enqueue_media();
            wp_enqueue_style('aimg-admin-css', AIMG_PLUGIN_URL . 'admin/css/admin.css', array(), AIMG_VERSION);
            wp_enqueue_script('aimg-settings-js', AIMG_PLUGIN_URL . 'admin/js/settings.js', array('jquery'), AIMG_VERSION, true);

            wp_localize_script('aimg-settings-js', 'aimgAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aimg_ajax_nonce'),
            ));
        }
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include AIMG_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * AJAX: Генерация изображения для поста
     */
    public function ajax_generate_post_image() {
        check_ajax_referer('aimg_ajax_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'ai-image-generator')));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('ID поста не указан', 'ai-image-generator')));
        }

        $generator = AIMG_Image_Generator::get_instance();
        $attachment_id = $generator->generate_post_image($post_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        $image_url = wp_get_attachment_image_url($attachment_id, 'medium');

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'message' => __('Изображение успешно сгенерировано!', 'ai-image-generator'),
        ));
    }

    /**
     * AJAX: Замена фона товара
     */
    public function ajax_replace_product_background() {
        check_ajax_referer('aimg_ajax_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'ai-image-generator')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $background_type = isset($_POST['background_type']) ? sanitize_text_field($_POST['background_type']) : 'white';

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('ID изображения не указан', 'ai-image-generator')));
        }

        $bg_remover = AIMG_Background_Remover::get_instance();
        $new_attachment_id = $bg_remover->replace_background($attachment_id, $background_type);

        if (is_wp_error($new_attachment_id)) {
            wp_send_json_error(array('message' => $new_attachment_id->get_error_message()));
        }

        $image_url = wp_get_attachment_image_url($new_attachment_id, 'medium');

        wp_send_json_success(array(
            'attachment_id' => $new_attachment_id,
            'image_url' => $image_url,
            'message' => __('Фон успешно заменен!', 'ai-image-generator'),
        ));
    }

    /**
     * AJAX: Тестирование Gemini API
     */
    public function ajax_test_gemini_api() {
        check_ajax_referer('aimg_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'ai-image-generator')));
        }

        $gemini = AIMG_Gemini_API::get_instance();
        $result = $gemini->test_api_key();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('API ключ работает корректно!', 'ai-image-generator')));
    }
}
