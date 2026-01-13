<?php
/**
 * Класс для работы с водяными знаками
 *
 * @package AI_Image_Generator
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс AIMG_Watermark
 */
class AIMG_Watermark {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Применение водяного знака к изображению
     *
     * @param int $attachment_id ID изображения
     * @return bool|WP_Error
     */
    public function apply_watermark($attachment_id) {
        $enabled = AI_Image_Generator::get_setting('watermark_enabled', false);

        if (!$enabled) {
            return true;
        }

        $image_path = get_attached_file($attachment_id);

        if (!file_exists($image_path)) {
            return new WP_Error('file_not_found', __('Файл не найден', 'ai-image-generator'));
        }

        $image_type = wp_check_filetype($image_path);

        switch ($image_type['type']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                break;
            default:
                return new WP_Error('unsupported_type', __('Неподдерживаемый тип изображения', 'ai-image-generator'));
        }

        if (!$image) {
            return new WP_Error('image_error', __('Не удалось загрузить изображение', 'ai-image-generator'));
        }

        // Получаем настройки водяного знака
        $watermark_text = AI_Image_Generator::get_setting('watermark_text', get_bloginfo('name'));
        $watermark_image = AI_Image_Generator::get_setting('watermark_image', '');
        $position = AI_Image_Generator::get_setting('watermark_position', 'bottom-right');
        $opacity = AI_Image_Generator::get_setting('watermark_opacity', 50);

        // Применяем водяной знак
        if (!empty($watermark_image) && file_exists(get_attached_file($watermark_image))) {
            $this->apply_image_watermark($image, $watermark_image, $position, $opacity);
        } else {
            $this->apply_text_watermark($image, $watermark_text, $position, $opacity);
        }

        // Сохраняем изображение
        switch ($image_type['type']) {
            case 'image/jpeg':
                imagejpeg($image, $image_path, 90);
                break;
            case 'image/png':
                imagepng($image, $image_path);
                break;
        }

        imagedestroy($image);

        return true;
    }

    /**
     * Применение текстового водяного знака
     */
    private function apply_text_watermark($image, $text, $position, $opacity) {
        $width = imagesx($image);
        $height = imagesy($image);

        $font_size = 20;
        $font_file = AIMG_PLUGIN_DIR . 'assets/fonts/arial.ttf';

        // Если шрифт не найден, используем встроенный
        if (!file_exists($font_file)) {
            $font_size = 5; // Встроенный шрифт
        }

        // Вычисляем позицию
        $coords = $this->calculate_position($width, $height, 200, 30, $position);

        // Создаем цвет с прозрачностью
        $alpha = (100 - $opacity) * 1.27;
        $color = imagecolorallocatealpha($image, 255, 255, 255, $alpha);

        // Рисуем текст
        if (file_exists($font_file)) {
            imagettftext($image, $font_size, 0, $coords['x'], $coords['y'], $color, $font_file, $text);
        } else {
            imagestring($image, $font_size, $coords['x'], $coords['y'], $text, $color);
        }
    }

    /**
     * Применение изображения в качестве водяного знака
     */
    private function apply_image_watermark($image, $watermark_id, $position, $opacity) {
        $watermark_path = get_attached_file($watermark_id);
        $watermark_type = wp_check_filetype($watermark_path);

        switch ($watermark_type['type']) {
            case 'image/jpeg':
                $watermark = imagecreatefromjpeg($watermark_path);
                break;
            case 'image/png':
                $watermark = imagecreatefrompng($watermark_path);
                break;
            default:
                return;
        }

        if (!$watermark) {
            return;
        }

        $image_width = imagesx($image);
        $image_height = imagesy($image);
        $watermark_width = imagesx($watermark);
        $watermark_height = imagesy($watermark);

        // Вычисляем позицию
        $coords = $this->calculate_position($image_width, $image_height, $watermark_width, $watermark_height, $position);

        // Применяем прозрачность и накладываем
        imagecopymerge($image, $watermark, $coords['x'], $coords['y'], 0, 0, $watermark_width, $watermark_height, $opacity);

        imagedestroy($watermark);
    }

    /**
     * Вычисление позиции водяного знака
     */
    private function calculate_position($image_width, $image_height, $watermark_width, $watermark_height, $position) {
        $padding = 20;

        switch ($position) {
            case 'top-left':
                return array('x' => $padding, 'y' => $padding);

            case 'top-right':
                return array('x' => $image_width - $watermark_width - $padding, 'y' => $padding);

            case 'bottom-left':
                return array('x' => $padding, 'y' => $image_height - $watermark_height - $padding);

            case 'bottom-right':
                return array('x' => $image_width - $watermark_width - $padding, 'y' => $image_height - $watermark_height - $padding);

            case 'center':
                return array(
                    'x' => ($image_width - $watermark_width) / 2,
                    'y' => ($image_height - $watermark_height) / 2
                );

            default:
                return array('x' => $image_width - $watermark_width - $padding, 'y' => $image_height - $watermark_height - $padding);
        }
    }
}
