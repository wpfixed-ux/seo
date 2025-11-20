<?php
/**
 * AI Processor Class
 * Handles OpenAI API integration
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_AI_Processor {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * API key
     */
    private $api_key;

    /**
     * Model name
     */
    private $model;

    /**
     * Max tokens
     */
    private $max_tokens;

    /**
     * API timeout
     */
    private $timeout;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $settings = get_option('ail_settings', []);

        $this->api_key = isset($settings['openai_api_key']) ? $this->decrypt_api_key($settings['openai_api_key']) : '';
        $this->model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-3.5-turbo';
        $this->max_tokens = isset($settings['max_tokens']) ? intval($settings['max_tokens']) : 500;
        $this->timeout = isset($settings['api_timeout']) ? intval($settings['api_timeout']) : 30;
    }

    /**
     * Test API connection
     *
     * @return array Response with status and message
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => __('API key is not set', 'ai-seo-interlinking'),
            ];
        }

        $response = $this->make_api_request(
            'Test connection',
            'Respond with "OK" if you receive this message.',
            100
        );

        if (isset($response['error'])) {
            return [
                'success' => false,
                'message' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'message' => __('Connection successful!', 'ai-seo-interlinking'),
            'model' => $this->model,
        ];
    }

    /**
     * Generate keywords for content
     *
     * @param string $content  Post content
     * @param string $title    Post title
     * @param string $category Post category
     * @param string $language Content language
     * @return array|WP_Error  Keywords array or error
     */
    public function generate_keywords($content, $title = '', $category = '', $language = 'default') {
        $cache_key = 'ail_keywords_' . md5($content . $title);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $settings = get_option('ail_settings', []);
        $prompt_template = isset($settings['ai_prompt_template']) ? $settings['ai_prompt_template'] : $this->get_default_prompt();

        // Replace variables in prompt
        $prompt = str_replace(
            ['{post_title}', '{post_content}', '{category}', '{language}'],
            [$title, wp_strip_all_tags(substr($content, 0, 2000)), $category, $language],
            $prompt_template
        );

        $response = $this->make_api_request(
            'Generate keywords',
            $prompt,
            $this->max_tokens
        );

        if (isset($response['error'])) {
            return new WP_Error('api_error', $response['error']);
        }

        $keywords = $this->parse_keywords_response($response['content']);

        // Cache for 24 hours
        set_transient($cache_key, $keywords, 86400);

        return $keywords;
    }

    /**
     * Analyze relevance between two pieces of content
     *
     * @param string $source_content Source content
     * @param string $target_content Target content
     * @param string $language       Content language
     * @return float|WP_Error        Relevance score (0-100) or error
     */
    public function analyze_relevance($source_content, $target_content, $language = 'default') {
        $cache_key = 'ail_relevance_' . md5($source_content . $target_content);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $prompt = sprintf(
            'Analyze the semantic relevance between these two texts in %s language.
            Rate the relevance on a scale of 0-100 where:
            - 0-20: Not relevant
            - 21-40: Slightly relevant
            - 41-60: Moderately relevant
            - 61-80: Highly relevant
            - 81-100: Very highly relevant

            Text 1: %s

            Text 2: %s

            Respond with only the numerical score (0-100).',
            $language,
            wp_strip_all_tags(substr($source_content, 0, 1000)),
            wp_strip_all_tags(substr($target_content, 0, 1000))
        );

        $response = $this->make_api_request(
            'Analyze relevance',
            $prompt,
            50
        );

        if (isset($response['error'])) {
            return new WP_Error('api_error', $response['error']);
        }

        $score = floatval(preg_replace('/[^0-9.]/', '', $response['content']));
        $score = max(0, min(100, $score)); // Ensure 0-100 range

        // Cache for 24 hours
        set_transient($cache_key, $score, 86400);

        return $score;
    }

    /**
     * Suggest anchor text variations
     *
     * @param string $keyword Target keyword
     * @param string $context Surrounding text context
     * @param string $language Content language
     * @return array|WP_Error  Array of anchor variations or error
     */
    public function suggest_anchor_text($keyword, $context = '', $language = 'default') {
        $prompt = sprintf(
            'Generate 5 natural anchor text variations for the keyword "%s" in %s language.
            Context: %s

            Provide variations that include:
            1. Exact match
            2. Partial match
            3. LSI variation
            4. Long-tail phrase
            5. Brand/natural variation

            Format as JSON array: ["variation1", "variation2", ...]',
            $keyword,
            $language,
            wp_strip_all_tags(substr($context, 0, 500))
        );

        $response = $this->make_api_request(
            'Suggest anchor text',
            $prompt,
            200
        );

        if (isset($response['error'])) {
            return new WP_Error('api_error', $response['error']);
        }

        $anchors = $this->parse_json_response($response['content']);

        if (empty($anchors)) {
            $anchors = [$keyword]; // Fallback to original keyword
        }

        return $anchors;
    }

    /**
     * Make API request to OpenAI
     *
     * @param string $operation  Operation name for logging
     * @param string $prompt     Prompt text
     * @param int    $max_tokens Max tokens for response
     * @return array             Response data
     */
    private function make_api_request($operation, $prompt, $max_tokens = null) {
        if (empty($this->api_key)) {
            return ['error' => __('API key is not configured', 'ai-seo-interlinking')];
        }

        $max_tokens = $max_tokens ?: $this->max_tokens;

        $request_data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
        ];

        $start_time = microtime(true);

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body' => wp_json_encode($request_data),
                'timeout' => $this->timeout,
            ]
        );

        $elapsed_time = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            AIL_Logger::log_api_request($request_data, ['error' => $response->get_error_message()], 0, 0);
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            AIL_Logger::log_api_request($request_data, $data, 0, 0);
            return ['error' => $data['error']['message']];
        }

        $tokens_used = isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : 0;
        $cost = $this->calculate_cost($tokens_used, $this->model);

        AIL_Logger::log_api_request($request_data, $data, $tokens_used, $cost);

        return [
            'content' => isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : '',
            'tokens' => $tokens_used,
            'cost' => $cost,
            'elapsed_time' => $elapsed_time,
        ];
    }

    /**
     * Calculate API cost based on tokens and model
     *
     * @param int    $tokens Number of tokens
     * @param string $model  Model name
     * @return float         Cost in USD
     */
    private function calculate_cost($tokens, $model) {
        // Pricing as of 2024 (USD per 1K tokens)
        $pricing = [
            'gpt-3.5-turbo' => 0.0015,
            'gpt-4' => 0.03,
            'gpt-4-turbo' => 0.01,
        ];

        $rate = isset($pricing[$model]) ? $pricing[$model] : 0.0015;

        return ($tokens / 1000) * $rate;
    }

    /**
     * Parse keywords from API response
     *
     * @param string $content Response content
     * @return array          Parsed keywords
     */
    private function parse_keywords_response($content) {
        $keywords = [];

        // Try to parse as JSON first
        $json_data = $this->parse_json_response($content);

        if (!empty($json_data)) {
            foreach ($json_data as $item) {
                if (is_array($item) && isset($item['keyword'])) {
                    $keywords[] = [
                        'keyword' => sanitize_text_field($item['keyword']),
                        'type' => isset($item['type']) ? sanitize_text_field($item['type']) : 'primary',
                    ];
                } elseif (is_string($item)) {
                    $keywords[] = [
                        'keyword' => sanitize_text_field($item),
                        'type' => 'primary',
                    ];
                }
            }
        } else {
            // Fallback: extract keywords from text
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                $line = preg_replace('/^[-*•\d.]+\s*/', '', $line); // Remove bullets/numbers

                if (!empty($line) && strlen($line) > 2) {
                    $keywords[] = [
                        'keyword' => sanitize_text_field($line),
                        'type' => 'primary',
                    ];
                }
            }
        }

        return $keywords;
    }

    /**
     * Parse JSON response
     *
     * @param string $content Response content
     * @return array          Parsed data or empty array
     */
    private function parse_json_response($content) {
        // Extract JSON from markdown code blocks if present
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Get default prompt template
     *
     * @return string Default prompt
     */
    private function get_default_prompt() {
        return 'Given the following content in {language}:
Title: {post_title}
Category: {category}
Content: {post_content}

Generate relevant keywords for internal linking:
1. Primary keywords (3-5 exact match)
2. LSI keywords (5-10 variations)
3. Long-tail keywords (3-5 phrases)

Format: JSON array with structure: [{"keyword": "...", "type": "primary|lsi|long-tail"}]';
    }

    /**
     * Encrypt API key
     *
     * @param string $key API key
     * @return string     Encrypted key
     */
    public static function encrypt_api_key($key) {
        if (empty($key)) {
            return '';
        }

        $method = 'AES-256-CBC';
        $encryption_key = wp_salt('auth');
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);

        return base64_encode(openssl_encrypt($key, $method, $encryption_key, 0, $iv));
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted_key Encrypted API key
     * @return string               Decrypted key
     */
    private function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }

        $method = 'AES-256-CBC';
        $encryption_key = wp_salt('auth');
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);

        return openssl_decrypt(base64_decode($encrypted_key), $method, $encryption_key, 0, $iv);
    }
}
