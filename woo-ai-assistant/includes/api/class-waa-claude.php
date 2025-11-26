<?php
/**
 * Claude (Anthropic) API provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Claude extends WAA_API_Base {

    private static $instance = null;
    private $base_url = 'https://api.anthropic.com/v1';
    private $api_version = '2023-06-01';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = get_option('waa_claude_api_key', '');
    }

    public function get_name() {
        return 'Claude';
    }

    /**
     * Get embeddings for text
     * Note: Claude doesn't have native embeddings, using OpenAI for embeddings
     */
    public function get_embedding($text) {
        // Use OpenAI for embeddings when using Claude for chat
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

        $model = isset($options['model']) ? $options['model'] : 'claude-sonnet-4-20250514';
        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : get_option('waa_max_tokens', 1000);

        // Convert OpenAI message format to Claude format
        $system_message = '';
        $claude_messages = array();

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_message = $msg['content'];
            } else {
                $claude_messages[] = array(
                    'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $msg['content']
                );
            }
        }

        $data = array(
            'model' => $model,
            'max_tokens' => (int) $max_tokens,
            'messages' => $claude_messages,
        );

        if (!empty($system_message)) {
            $data['system'] = $system_message;
        }

        $response = $this->request(
            $this->base_url . '/messages',
            $data,
            array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => $this->api_version,
            )
        );

        if (!$response['success']) {
            return $response;
        }

        return array(
            'success' => true,
            'message' => $response['data']['content'][0]['text'],
            'usage' => array(
                'prompt_tokens' => $response['data']['usage']['input_tokens'],
                'completion_tokens' => $response['data']['usage']['output_tokens'],
            )
        );
    }

    /**
     * Validate API key
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return false;
        }

        // Test with a minimal request
        $response = $this->chat(
            array(array('role' => 'user', 'content' => 'Hi')),
            array('max_tokens' => 10)
        );

        return $response['success'];
    }
}
