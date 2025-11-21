<?php
/**
 * OpenAI API Integration
 *
 * Handles all communication with the OpenAI API for SEO analysis and content generation.
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/api
 */

class SAP_OpenAI {

    /**
     * API endpoint for OpenAI
     *
     * @var string
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * API key for authentication
     *
     * @var string
     */
    private $api_key;

    /**
     * Model to use
     *
     * @var string
     */
    private $model;

    /**
     * Maximum tokens for responses
     *
     * @var int
     */
    private $max_tokens = 4096;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('sap_settings', array());
        $this->api_key = $settings['openai_api_key'] ?? '';
        $this->model = $settings['openai_model'] ?? 'gpt-4o';
    }

    /**
     * Send request to OpenAI API
     *
     * @param string $prompt The prompt to send
     * @param array $options Additional options
     * @return array|WP_Error Response data or error
     */
    private function send_request($prompt, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is not configured.', 'seo-analytics-pro'));
        }

        $start_time = microtime(true);

        $defaults = array(
            'temperature' => 0.7,
            'max_tokens' => $this->max_tokens,
            'system' => 'You are an expert SEO analyst and content writer.'
        );

        $options = wp_parse_args($options, $defaults);

        $messages = array();

        // Add system message if provided
        if (!empty($options['system'])) {
            $messages[] = array(
                'role' => 'system',
                'content' => $options['system']
            );
        }

        // Add user prompt
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );

        $request_body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens']
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => wp_json_encode($request_body),
            'timeout' => 60
        );

        $response = wp_remote_post($this->api_endpoint, $args);

        $execution_time = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            $this->log_api_call($request_body, null, null, $execution_time);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            $this->log_api_call($request_body, $data, $status_code, $execution_time);
            return new WP_Error('api_error', sprintf(__('OpenAI error: %s', 'seo-analytics-pro'), $error_message));
        }

        $tokens_used = $data['usage']['total_tokens'] ?? 0;
        $this->log_api_call($request_body, $data, $status_code, $execution_time, $tokens_used);

        return $data;
    }

    /**
     * Generate content from specification
     *
     * @param string $prompt The generation prompt
     * @param array $options Additional options
     * @return string|WP_Error Generated content or error
     */
    public function generate_content($prompt, $options = array()) {
        $response = $this->send_request($prompt, $options);

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $response['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            return new WP_Error('empty_response', __('Empty response from OpenAI', 'seo-analytics-pro'));
        }

        return $content;
    }

    /**
     * Analyze keyword competitiveness and opportunity
     *
     * @param string $keyword The keyword to analyze
     * @param array $serp_data SERP results data
     * @param array $competitors Competitor website data
     * @return array|WP_Error Analysis results or error
     */
    public function analyze_keyword($keyword, $serp_data, $competitors = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is not configured.', 'seo-analytics-pro'));
        }

        $prompt = $this->build_keyword_analysis_prompt($keyword, $serp_data, $competitors);

        $response = $this->send_request($prompt, array(
            'system' => 'You are an expert SEO analyst. Analyze keyword data and provide structured JSON responses with detailed insights about keyword competitiveness, difficulty, and opportunity.',
            'max_tokens' => 2000
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        return $this->parse_keyword_analysis($content);
    }

    /**
     * Build keyword analysis prompt
     *
     * @param string $keyword The keyword
     * @param array $serp_data SERP data
     * @param array $competitors Competitor data
     * @return string Prompt
     */
    private function build_keyword_analysis_prompt($keyword, $serp_data, $competitors) {
        $prompt = "Analyze this keyword for SEO opportunity:\n\n";
        $prompt .= "Keyword: {$keyword}\n\n";
        $prompt .= "SERP Data:\n";
        $prompt .= "Total Results: " . ($serp_data['total_results'] ?? 0) . "\n";
        $prompt .= "Top 10 Rankings:\n";

        if (!empty($serp_data['results'])) {
            foreach (array_slice($serp_data['results'], 0, 10) as $result) {
                $prompt .= "- Position {$result['position']}: {$result['domain']}\n";
                $prompt .= "  Title: {$result['title']}\n";
            }
        }

        if (!empty($serp_data['related_keywords'])) {
            $prompt .= "\nRelated Keywords: " . implode(', ', array_slice($serp_data['related_keywords'], 0, 10)) . "\n";
        }

        $prompt .= "\nProvide analysis in JSON format with: difficulty (1-100), opportunity (1-100), competition_level (low/medium/high), search_intent, recommended_content_type, and key_insights array.";

        return $prompt;
    }

    /**
     * Parse keyword analysis from response
     *
     * @param string $content Response content
     * @return array Parsed analysis
     */
    private function parse_keyword_analysis($content) {
        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        // Fallback if JSON parsing fails
        return array(
            'difficulty' => 50,
            'opportunity' => 50,
            'competition_level' => 'medium',
            'search_intent' => 'informational',
            'recommended_content_type' => 'article',
            'key_insights' => array('Analysis completed', 'Manual review recommended')
        );
    }

    /**
     * Log API call to database
     *
     * @param array $request_data Request data
     * @param array $response_data Response data
     * @param int $status_code HTTP status code
     * @param float $execution_time Execution time in seconds
     * @param int $tokens_used Tokens used
     */
    private function log_api_call($request_data, $response_data, $status_code, $execution_time, $tokens_used = 0) {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_api_logs';

        // Calculate approximate cost (pricing as of 2024)
        $cost = 0;
        if ($tokens_used > 0) {
            switch ($this->model) {
                case 'gpt-4o':
                    $cost = ($tokens_used / 1000) * 0.005; // $5 per 1M tokens (average)
                    break;
                case 'gpt-4-turbo':
                    $cost = ($tokens_used / 1000) * 0.01; // $10 per 1M tokens (average)
                    break;
                case 'gpt-4':
                    $cost = ($tokens_used / 1000) * 0.03; // $30 per 1M tokens (average)
                    break;
                case 'gpt-3.5-turbo':
                    $cost = ($tokens_used / 1000) * 0.0015; // $1.5 per 1M tokens (average)
                    break;
            }
        }

        $wpdb->insert(
            $table,
            array(
                'service_name' => 'openai',
                'endpoint' => $this->api_endpoint,
                'request_data' => wp_json_encode($request_data),
                'response_data' => wp_json_encode($response_data),
                'status_code' => $status_code,
                'execution_time' => $execution_time,
                'tokens_used' => $tokens_used,
                'cost' => $cost,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%f', '%d', '%f', '%s')
        );
    }

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Validate API key
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key is required', 'seo-analytics-pro'));
        }

        // Send a simple test request
        $result = $this->generate_content('Say "OK"', array('max_tokens' => 10));

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}
