<?php
/**
 * Класс для генерации изображений через AI
 *
 * @package AI_Image_Generator
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс AIMG_Image_Generator
 */
class AIMG_Image_Generator {

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
        // Конструктор
    }

    /**
     * Генерация изображения для статьи
     *
     * @param int $post_id ID поста
     * @return int|WP_Error ID вложения или ошибка
     */
    public function generate_post_image($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('Пост не найден', 'ai-image-generator'));
        }

        // Получаем контент и заголовок
        $content = $post->post_content;
        $title = $post->post_title;

        // Используем Gemini для создания промпта
        $gemini = AIMG_Gemini_API::get_instance();
        $prompt_data = $gemini->generate_image_prompt($content, $title);

        if (is_wp_error($prompt_data)) {
            return $prompt_data;
        }

        $prompt = $prompt_data['prompt'];

        // Генерируем изображение
        $image_url = $this->generate_image($prompt);

        if (is_wp_error($image_url)) {
            return $image_url;
        }

        // Загружаем изображение в медиатеку
        $attachment_id = $this->upload_image_to_media_library($image_url, $post_id, $title);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Применяем водяной знак если нужно
        if (AI_Image_Generator::get_setting('watermark_enabled', false)) {
            $watermark = AIMG_Watermark::get_instance();
            $watermark->apply_watermark($attachment_id);
        }

        // Устанавливаем как Featured Image
        set_post_thumbnail($post_id, $attachment_id);

        // Сохраняем метаданные
        update_post_meta($post_id, '_aimg_generated', true);
        update_post_meta($post_id, '_aimg_prompt', $prompt);
        update_post_meta($post_id, '_aimg_generated_date', current_time('mysql'));

        return $attachment_id;
    }

    /**
     * Генерация изображения через выбранный API
     *
     * @param string $prompt Промпт
     * @return string|WP_Error URL изображения или ошибка
     */
    private function generate_image($prompt) {
        $provider = AI_Image_Generator::get_setting('image_api_provider', 'stability');

        switch ($provider) {
            case 'stability':
                return $this->generate_with_stability($prompt);

            case 'dalle':
                return $this->generate_with_dalle($prompt);

            default:
                return new WP_Error('unsupported_provider', __('Неподдерживаемый провайдер', 'ai-image-generator'));
        }
    }

    /**
     * Генерация через Stability AI (Stable Diffusion)
     *
     * @param string $prompt Промпт
     * @return string|WP_Error
     */
    private function generate_with_stability($prompt) {
        $api_key = AI_Image_Generator::get_setting('image_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API ключ не настроен', 'ai-image-generator'));
        }

        $url = 'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image';

        $size = AI_Image_Generator::get_setting('post_image_size', array('width' => 1200, 'height' => 630));

        $body = array(
            'text_prompts' => array(
                array(
                    'text' => $prompt,
                    'weight' => 1
                )
            ),
            'cfg_scale' => 7,
            'height' => $size['height'],
            'width' => $size['width'],
            'samples' => 1,
            'steps' => 30,
        );

        $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 60,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', __('Ошибка API', 'ai-image-generator'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['artifacts'][0]['base64'])) {
            return new WP_Error('invalid_response', __('Некорректный ответ API', 'ai-image-generator'));
        }

        // Сохраняем base64 изображение во временный файл
        $base64_image = $data['artifacts'][0]['base64'];
        $image_data = base64_decode($base64_image);

        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/ai-image-generator-cache/temp_' . uniqid() . '.png';

        file_put_contents($temp_file, $image_data);

        return $temp_file;
    }

    /**
     * Генерация через DALL-E
     *
     * @param string $prompt Промпт
     * @return string|WP_Error
     */
    private function generate_with_dalle($prompt) {
        $api_key = AI_Image_Generator::get_setting('image_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API ключ не настроен', 'ai-image-generator'));
        }

        $url = 'https://api.openai.com/v1/images/generations';

        $body = array(
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1792x1024',
            'quality' => 'standard',
        );

        $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 60,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', __('Ошибка API', 'ai-image-generator'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data'][0]['url'])) {
            return new WP_Error('invalid_response', __('Некорректный ответ API', 'ai-image-generator'));
        }

        return $data['data'][0]['url'];
    }

    /**
     * Загрузка изображения в медиатеку
     *
     * @param string $image_source URL или путь к файлу
     * @param int $post_id ID поста
     * @param string $title Заголовок
     * @return int|WP_Error ID вложения
     */
    private function upload_image_to_media_library($image_source, $post_id, $title) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Если это URL, скачиваем файл
        if (filter_var($image_source, FILTER_VALIDATE_URL)) {
            $temp_file = download_url($image_source);

            if (is_wp_error($temp_file)) {
                return $temp_file;
            }
        } else {
            $temp_file = $image_source;
        }

        $file_array = array(
            'name' => sanitize_file_name($title) . '_' . time() . '.png',
            'tmp_name' => $temp_file,
        );

        $attachment_id = media_handle_sideload($file_array, $post_id, $title);

        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }

        return $attachment_id;
    }

    /**
     * Получение списка доступных провайдеров
     *
     * @return array
     */
    public static function get_providers() {
        return array(
            'stability' => 'Stability AI (Stable Diffusion)',
            'dalle' => 'OpenAI (DALL-E 3)',
        );
    }
}
