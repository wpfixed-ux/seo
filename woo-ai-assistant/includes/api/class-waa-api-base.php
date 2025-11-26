<?php
/**
 * Base API class for AI providers
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WAA_API_Base {

    protected $api_key;
    protected $timeout = 60;

    /**
     * Get embeddings for text
     */
    abstract public function get_embedding($text);

    /**
     * Generate chat completion
     */
    abstract public function chat($messages, $options = array());

    /**
     * Make HTTP request
     */
    protected function request($url, $data, $headers = array()) {
        $default_headers = array(
            'Content-Type' => 'application/json',
        );

        $headers = array_merge($default_headers, $headers);

        // Log API request
        error_log('WAA API Request: ' . $url);
        error_log('WAA API Key present: ' . (isset($headers['Authorization']) ? 'Yes (length: ' . strlen($headers['Authorization']) . ')' : 'No'));

        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => json_encode($data),
        ));

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('WAA API Error (WP): ' . $error_msg . ' | URL: ' . $url);
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        // Log response
        error_log('WAA API Response: HTTP ' . $code . ' | URL: ' . $url);

        if ($code >= 400) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message'])
                ? $error_data['error']['message']
                : "HTTP Error: $code";

            error_log('WAA API Error (HTTP ' . $code . '): ' . $error_message . ' | URL: ' . $url);
            error_log('WAA API Error Body: ' . substr($body, 0, 500));

            return array(
                'success' => false,
                'error' => $error_message
            );
        }

        // Log success
        error_log('WAA API Success: ' . $url);

        return array(
            'success' => true,
            'data' => json_decode($body, true)
        );
    }

    /**
     * Validate API key
     */
    abstract public function validate_api_key();

    /**
     * Get provider name
     */
    abstract public function get_name();
}
