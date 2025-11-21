<?php
/**
 * Класс для удаления и замены фона изображений
 *
 * @package AI_Image_Generator
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс AIMG_Background_Remover
 */
class AIMG_Background_Remover {

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
     * Замена фона для изображения товара
     *
     * @param int $attachment_id ID изображения
     * @param string $background_type Тип фона (white, gradient, 3d, ai)
     * @param array $options Дополнительные опции
     * @return int|WP_Error ID нового изображения
     */
    public function replace_background($attachment_id, $background_type = 'white', $options = array()) {
        // Получаем путь к изображению
        $image_path = get_attached_file($attachment_id);

        if (!file_exists($image_path)) {
            return new WP_Error('file_not_found', __('Файл изображения не найден', 'ai-image-generator'));
        }

        // Удаляем фон
        $no_bg_path = $this->remove_background($image_path);

        if (is_wp_error($no_bg_path)) {
            return $no_bg_path;
        }

        // Применяем новый фон
        $new_image_path = $this->apply_background($no_bg_path, $background_type, $options);

        if (is_wp_error($new_image_path)) {
            @unlink($no_bg_path);
            return $new_image_path;
        }

        // Загружаем новое изображение в медиатеку
        $post_id = get_post_field('post_parent', $attachment_id);
        $title = get_the_title($attachment_id) . ' (' . $background_type . ')';

        $new_attachment_id = $this->upload_to_media_library($new_image_path, $post_id, $title);

        // Удаляем временные файлы
        @unlink($no_bg_path);
        @unlink($new_image_path);

        if (is_wp_error($new_attachment_id)) {
            return $new_attachment_id;
        }

        // Применяем водяной знак если нужно
        if (AI_Image_Generator::get_setting('watermark_enabled', false)) {
            $watermark = AIMG_Watermark::get_instance();
            $watermark->apply_watermark($new_attachment_id);
        }

        // Сохраняем метаданные
        update_post_meta($new_attachment_id, '_aimg_original_id', $attachment_id);
        update_post_meta($new_attachment_id, '_aimg_background_type', $background_type);
        update_post_meta($new_attachment_id, '_aimg_generated_date', current_time('mysql'));

        return $new_attachment_id;
    }

    /**
     * Удаление фона с изображения
     *
     * @param string $image_path Путь к изображению
     * @return string|WP_Error Путь к изображению без фона
     */
    private function remove_background($image_path) {
        $provider = AI_Image_Generator::get_setting('bg_removal_provider', 'removebg');

        switch ($provider) {
            case 'removebg':
                return $this->remove_bg_with_removebg($image_path);

            case 'clipdrop':
                return $this->remove_bg_with_clipdrop($image_path);

            default:
                return new WP_Error('unsupported_provider', __('Неподдерживаемый провайдер', 'ai-image-generator'));
        }
    }

    /**
     * Удаление фона через Remove.bg API
     *
     * @param string $image_path Путь к изображению
     * @return string|WP_Error
     */
    private function remove_bg_with_removebg($image_path) {
        $api_key = AI_Image_Generator::get_setting('bg_removal_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API ключ не настроен', 'ai-image-generator'));
        }

        $url = 'https://api.remove.bg/v1.0/removebg';

        $boundary = wp_generate_password(24);
        $body = '';

        // Подготовка multipart/form-data
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image_file"; filename="' . basename($image_path) . '"' . "\r\n";
        $body .= 'Content-Type: image/png' . "\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="size"' . "\r\n\r\n";
        $body .= 'auto' . "\r\n";
        $body .= '--' . $boundary . '--';

        $args = array(
            'body' => $body,
            'headers' => array(
                'X-Api-Key' => $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'timeout' => 60,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', __('Ошибка API удаления фона', 'ai-image-generator'));
        }

        // Сохраняем результат
        $image_data = wp_remote_retrieve_body($response);
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/ai-image-generator-cache/nobg_' . uniqid() . '.png';

        file_put_contents($temp_file, $image_data);

        return $temp_file;
    }

    /**
     * Удаление фона через ClipDrop API
     *
     * @param string $image_path Путь к изображению
     * @return string|WP_Error
     */
    private function remove_bg_with_clipdrop($image_path) {
        $api_key = AI_Image_Generator::get_setting('bg_removal_api_key', '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API ключ не настроен', 'ai-image-generator'));
        }

        $url = 'https://clipdrop-api.co/remove-background/v1';

        $boundary = wp_generate_password(24);
        $body = '';

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image_file"; filename="' . basename($image_path) . '"' . "\r\n";
        $body .= 'Content-Type: image/png' . "\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";
        $body .= '--' . $boundary . '--';

        $args = array(
            'body' => $body,
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'timeout' => 60,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return new WP_Error('api_error', __('Ошибка API удаления фона', 'ai-image-generator'));
        }

        $image_data = wp_remote_retrieve_body($response);
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/ai-image-generator-cache/nobg_' . uniqid() . '.png';

        file_put_contents($temp_file, $image_data);

        return $temp_file;
    }

    /**
     * Применение нового фона
     *
     * @param string $image_path Путь к изображению без фона
     * @param string $background_type Тип фона
     * @param array $options Дополнительные опции
     * @return string|WP_Error
     */
    private function apply_background($image_path, $background_type, $options = array()) {
        // Загружаем изображение
        $image = imagecreatefrompng($image_path);

        if (!$image) {
            return new WP_Error('image_error', __('Не удалось загрузить изображение', 'ai-image-generator'));
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Создаем новое изображение с фоном
        $new_image = imagecreatetruecolor($width, $height);

        switch ($background_type) {
            case 'white':
                $bg_color = imagecolorallocate($new_image, 255, 255, 255);
                imagefill($new_image, 0, 0, $bg_color);
                break;

            case 'gradient':
                $this->apply_gradient_background($new_image, $width, $height, $options);
                break;

            case '3d':
                $this->apply_3d_background($new_image, $width, $height, $options);
                break;

            case 'ai':
                // Для AI фона используем сгенерированное изображение
                return $this->apply_ai_background($image_path, $options);

            default:
                $bg_color = imagecolorallocate($new_image, 255, 255, 255);
                imagefill($new_image, 0, 0, $bg_color);
        }

        // Накладываем изображение товара поверх фона
        imagecopy($new_image, $image, 0, 0, 0, 0, $width, $height);

        // Сохраняем результат
        $upload_dir = wp_upload_dir();
        $output_path = $upload_dir['basedir'] . '/ai-image-generator-cache/result_' . uniqid() . '.png';

        imagepng($new_image, $output_path);

        imagedestroy($image);
        imagedestroy($new_image);

        return $output_path;
    }

    /**
     * Применение градиентного фона
     */
    private function apply_gradient_background($image, $width, $height, $options) {
        $colors = isset($options['colors']) ? $options['colors'] : array('#667eea', '#764ba2');

        // Преобразуем hex в RGB
        $color1_rgb = $this->hex_to_rgb($colors[0]);
        $color2_rgb = $this->hex_to_rgb($colors[1]);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;

            $r = $color1_rgb['r'] + ($color2_rgb['r'] - $color1_rgb['r']) * $ratio;
            $g = $color1_rgb['g'] + ($color2_rgb['g'] - $color1_rgb['g']) * $ratio;
            $b = $color1_rgb['b'] + ($color2_rgb['b'] - $color1_rgb['b']) * $ratio;

            $color = imagecolorallocate($image, $r, $g, $b);
            imagefilledrectangle($image, 0, $y, $width, $y + 1, $color);
        }
    }

    /**
     * Применение 3D фона (имитация студийного освещения)
     */
    private function apply_3d_background($image, $width, $height, $options) {
        // Создаем градиент с эффектом освещения
        $center_x = $width / 2;
        $center_y = $height / 2;
        $max_distance = sqrt($center_x * $center_x + $center_y * $center_y);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $distance = sqrt(pow($x - $center_x, 2) + pow($y - $center_y, 2));
                $ratio = $distance / $max_distance;

                // От светлого в центре к темному по краям
                $brightness = 255 - ($ratio * 100);
                $color = imagecolorallocate($image, $brightness, $brightness, $brightness + 10);

                imagesetpixel($image, $x, $y, $color);
            }
        }
    }

    /**
     * Применение AI-генерированного фона
     */
    private function apply_ai_background($foreground_path, $options) {
        // Генерируем промпт для фона через Gemini
        $gemini = AIMG_Gemini_API::get_instance();

        $product_title = isset($options['product_title']) ? $options['product_title'] : '';
        $product_description = isset($options['product_description']) ? $options['product_description'] : '';
        $category = isset($options['category']) ? $options['category'] : '';

        $prompt_data = $gemini->generate_product_background_prompt($product_title, $product_description, $category);

        if (is_wp_error($prompt_data)) {
            return $prompt_data;
        }

        // Генерируем фон через Image Generator
        $generator = AIMG_Image_Generator::get_instance();
        // Здесь нужна доработка - composite изображений

        return $foreground_path; // Временно возвращаем исходное изображение
    }

    /**
     * Конвертация HEX в RGB
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Загрузка изображения в медиатеку
     */
    private function upload_to_media_library($file_path, $post_id, $title) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_array = array(
            'name' => basename($file_path),
            'tmp_name' => $file_path,
        );

        return media_handle_sideload($file_array, $post_id, $title);
    }

    /**
     * Получение списка доступных типов фона
     */
    public static function get_background_types() {
        return array(
            'white' => __('Белый фон', 'ai-image-generator'),
            'gradient' => __('Градиентный фон', 'ai-image-generator'),
            '3d' => __('3D студийный фон', 'ai-image-generator'),
            'ai' => __('AI-генерированный фон', 'ai-image-generator'),
        );
    }
}
