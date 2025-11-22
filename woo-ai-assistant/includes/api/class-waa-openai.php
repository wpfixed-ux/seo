<?php
/**
 * OpenAI API provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_OpenAI extends WAA_API_Base {

    private static $instance = null;
    private $base_url = 'https://api.openai.com/v1';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = get_option('waa_openai_api_key', '');
    }

    public function get_name() {
        return 'OpenAI';
    }

    /**
     * Get embeddings for text
     */
    public function get_embedding($text) {
        if (empty($this->api_key)) {
            return array('success' => false, 'error' => 'API key not configured');
        }

        $model = get_option('waa_embedding_model', 'text-embedding-3-small');

        $response = $this->request(
            $this->base_url . '/embeddings',
            array(
                'model' => $model,
                'input' => $text,
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

        $model = isset($options['model']) ? $options['model'] : get_option('waa_chat_model', 'gpt-4o-mini');
        if (empty($model)) {
            $model = 'gpt-4o-mini';
        }

        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : get_option('waa_max_tokens', 1000);
        if (empty($max_tokens)) {
            $max_tokens = 1000;
        }

        $temperature = isset($options['temperature']) ? $options['temperature'] : get_option('waa_temperature', 0.7);
        if ($temperature === '' || $temperature === false) {
            $temperature = 0.7;
        }

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
     * Transcribe audio using Whisper API
     */
    public function transcribe_audio($audio_data, $filename = 'audio.webm') {
        if (empty($this->api_key)) {
            return array('success' => false, 'error' => 'API key not configured');
        }

        $model = get_option('waa_whisper_model', 'whisper-1');

        // Create boundary for multipart/form-data
        $boundary = wp_generate_password(24, false);

        // Build multipart body
        $body = '';

        // Add file field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: audio/webm\r\n\r\n";
        $body .= $audio_data . "\r\n";

        // Add model field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "{$model}\r\n";

        // Add language field (optional, let Whisper auto-detect)
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
        $body .= "auto\r\n";

        $body .= "--{$boundary}--\r\n";

        // Make request with wp_remote_post
        $response = wp_remote_post(
            $this->base_url . '/audio/transcriptions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ),
                'body' => $body,
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            return array(
                'success' => false,
                'error' => $error,
                'status_code' => $status_code
            );
        }

        return array(
            'success' => true,
            'text' => $data['text']
        );
    }

    /**
     * Validate API key
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return false;
        }

        $response = $this->request(
            $this->base_url . '/models',
            array(),
            array(
                'Authorization' => 'Bearer ' . $this->api_key,
            )
        );

        return $response['success'];
    }
}
