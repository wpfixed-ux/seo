<?php
/**
 * Класс для работы с Gemini API
 *
 * @package AI_Image_Generator
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс AIMG_Gemini_API
 */
class AIMG_Gemini_API {

    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;

    /**
     * API Key
     */
    private $api_key;

    /**
     * API Endpoint
     */
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Модель
     */
    private $model = 'gemini-pro';

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
        $this->api_key = AI_Image_Generator::get_setting('gemini_api_key', '');
        $this->model = AI_Image_Generator::get_setting('gemini_model', 'gemini-pro');
    }

    /**
     * Проверка наличия API ключа
     */
    public function has_api_key() {
        return !empty($this->api_key);
    }

    /**
     * Анализ контента и генерация промпта для изображения
     *
     * @param string $content Содержимое статьи
     * @param string $title Заголовок статьи
     * @return array|WP_Error
     */
    public function generate_image_prompt($content, $title = '') {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Gemini API ключ не настроен', 'ai-image-generator'));
        }

        // Очистка контента от HTML тегов
        $clean_content = wp_strip_all_tags($content);
        $clean_content = substr($clean_content, 0, 3000); // Ограничение по длине

        // Создание промпта для Gemini
        $system_prompt = "Ты - эксперт по созданию промптов для генерации изображений. Проанализируй следующую статью и создай детальный промпт на английском языке для генерации фонового изображения, которое идеально подойдет к этой статье. Промпт должен быть подробным, описывать стиль, настроение, цветовую гамму и основные визуальные элементы. Ответ должен содержать ТОЛЬКО промпт, без дополнительных объяснений.";

        $user_prompt = "Заголовок: " . $title . "\n\nСодержание статьи:\n" . $clean_content . "\n\nСоздай детальный промпт для генерации изображения:";

        $response = $this->send_request($system_prompt . "\n\n" . $user_prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'prompt' => $response,
            'title' => $title,
            'content_length' => strlen($clean_content)
        );
    }

    /**
     * Улучшение существующего промпта
     *
     * @param string $prompt Исходный промпт
     * @return string|WP_Error
     */
    public function enhance_prompt($prompt) {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Gemini API ключ не настроен', 'ai-image-generator'));
        }

        $system_prompt = "Ты - эксперт по улучшению промптов для генерации изображений. Улучши следующий промпт, сделав его более детальным и подходящим для создания высококачественного изображения. Добавь детали про освещение, композицию, стиль. Ответ должен содержать ТОЛЬКО улучшенный промпт.";

        $user_prompt = "Исходный промпт: " . $prompt;

        return $this->send_request($system_prompt . "\n\n" . $user_prompt);
    }

    /**
     * Анализ изображения товара и создание промпта для фона
     *
     * @param string $product_title Название товара
     * @param string $product_description Описание товара
     * @param string $category Категория товара
     * @return array|WP_Error
     */
    public function generate_product_background_prompt($product_title, $product_description = '', $category = '') {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Gemini API ключ не настроен', 'ai-image-generator'));
        }

        $system_prompt = "Ты - эксперт по созданию фонов для фотографий товаров в e-commerce. Создай промпт на английском языке для генерации красивого, привлекательного фона, который подчеркнет товар и повысит его привлекательность. Фон должен быть не слишком ярким, чтобы не отвлекать от товара. Ответ должен содержать ТОЛЬКО промпт.";

        $user_prompt = "Товар: " . $product_title;

        if (!empty($product_description)) {
            $clean_description = wp_strip_all_tags($product_description);
            $clean_description = substr($clean_description, 0, 500);
            $user_prompt .= "\nОписание: " . $clean_description;
        }

        if (!empty($category)) {
            $user_prompt .= "\nКатегория: " . $category;
        }

        $user_prompt .= "\n\nСоздай промпт для генерации фона:";

        $response = $this->send_request($system_prompt . "\n\n" . $user_prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'prompt' => $response,
            'product_title' => $product_title,
            'category' => $category
        );
    }

    /**
     * Отправка запроса к Gemini API
     *
     * @param string $prompt Промпт
     * @return string|WP_Error
     */
    private function send_request($prompt) {
        $url = $this->api_endpoint . $this->model . ':generateContent?key=' . $this->api_key;

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            )
        );

        $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';

            return new WP_Error(
                'gemini_api_error',
                sprintf(__('Gemini API error (code %d): %s', 'ai-image-generator'), $response_code, $error_message)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('gemini_invalid_response', __('Некорректный ответ от Gemini API', 'ai-image-generator'));
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];

        // Очистка ответа от возможных лишних символов
        $text = trim($text);
        $text = str_replace(array('```', '**'), '', $text);

        return $text;
    }

    /**
     * Тестирование API ключа
     *
     * @return bool|WP_Error
     */
    public function test_api_key() {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('API ключ не настроен', 'ai-image-generator'));
        }

        $response = $this->send_request('Hello, this is a test. Please respond with "OK".');

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Получение списка доступных моделей
     *
     * @return array
     */
    public static function get_available_models() {
        return array(
            'gemini-pro' => 'Gemini Pro (рекомендуется)',
            'gemini-pro-vision' => 'Gemini Pro Vision (с поддержкой изображений)',
        );
    }
}
