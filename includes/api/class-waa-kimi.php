<?php
/**
 * Kimi (Moonshot) API provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Kimi extends WAA_API_Base {

    private static $instance = null;
    private $base_url = 'https://api.moonshot.cn/v1';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = get_option('waa_kimi_api_key', '');
    }

    public function get_name() {
        return 'Kimi';
    }

    /**
     * Get embeddings for text
     * Note: Using OpenAI for embeddings when using Kimi for chat
     */
    public function get_embedding($text) {
        $openai_key = get_option('waa_openai_api_key', '');

        if (empty($openai_key)) {
            return array('success' => false, 'error' => 'OpenAI API key required for embeddings');
        }

        $response = $this->request(
            'https://api.openai.com/v1/embeddings',
            array(
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ),
            array(
                'Authorization' => 'Bearer ' . $openai_key,
            )
        );

        if (!$response['success']) {
            return $response;
        }

        return array(
            'success' => true,
            'embedding' => $response['data']['data'][0]['embedding']
        );
    }

    /**
     * Generate chat completion
     */
    public function chat($messages, $options = array()) {
        if (empty($this->api_key)) {
            return array('success' => false, 'error' => 'API key not configured');
        }

        $model = isset($options['model']) ? $options['model'] : 'moonshot-v1-8k';
        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : get_option('waa_max_tokens', 1000);
        $temperature = isset($options['temperature']) ? $options['temperature'] : get_option('waa_temperature', 0.7);

        $response = $this->request(
            $this->base_url . '/chat/completions',
            array(
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => (int) $max_tokens,
                'temperature' => (float) $temperature,
            ),
            array(
                'Authorization' => 'Bearer ' . $this->api_key,
            )
        );

        if (!$response['success']) {
            return $response;
        }

        return array(
            'success' => true,
            'message' => $response['data']['choices'][0]['message']['content'],
            'usage' => $response['data']['usage']
        );
    }

    /**
     * Validate API key
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return false;
        }

        $response = $this->chat(
            array(array('role' => 'user', 'content' => 'Hi')),
            array('max_tokens' => 10)
        );

        return $response['success'];
    }
}
