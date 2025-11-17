<?php
/**
 * OpenAI API Integration
 *
 * @package WC_AI_Translator
 */

class WCAT_OpenAI {

    /**
     * API key
     */
    private $api_key;

    /**
     * API endpoint
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * Model to use
     */
    private $model;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('wcat_settings');
        $this->api_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
        $this->model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o';
    }

    /**
     * Translate text using OpenAI API
     *
     * @param string $text Text to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @param array $options Additional options
     * @return array Translation result with text, tokens, and cost
     */
    public function translate($text, $source_lang, $target_lang, $options = array()) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => __('OpenAI API key is not configured.', 'wc-ai-translator')
            );
        }

        if (empty($text)) {
            return array(
                'success' => false,
                'error' => __('Text to translate is empty.', 'wc-ai-translator')
            );
        }

        // Build the translation prompt
        $prompt = $this->build_translation_prompt($text, $source_lang, $target_lang, $options);

        // Make API request
        $response = $this->make_api_request($prompt, $options);

        if (!$response['success']) {
            return $response;
        }

        return array(
            'success' => true,
            'translated_text' => $response['text'],
            'tokens_used' => $response['tokens'],
            'cost' => $this->calculate_cost($response['tokens']),
            'model' => $this->model
        );
    }

    /**
     * Translate multiple texts in batch
     *
     * @param array $texts Array of texts to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @param array $options Additional options
     * @return array Array of translation results
     */
    public function batch_translate($texts, $source_lang, $target_lang, $options = array()) {
        $results = array();

        foreach ($texts as $key => $text) {
            if (empty($text)) {
                $results[$key] = array(
                    'success' => false,
                    'error' => __('Empty text', 'wc-ai-translator')
                );
                continue;
            }

            $result = $this->translate($text, $source_lang, $target_lang, $options);
            $results[$key] = $result;

            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }

        return $results;
    }

    /**
     * Build translation prompt
     *
     * @param string $text Text to translate
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @param array $options Additional options
     * @return string Prompt
     */
    private function build_translation_prompt($text, $source_lang, $target_lang, $options = array()) {
        $settings = get_option('wcat_settings');
        $quality = isset($settings['translation_quality']) ? $settings['translation_quality'] : 'high';
        $preserve_html = isset($settings['preserve_html']) ? $settings['preserve_html'] : true;
        $context = isset($settings['translation_context']) ? $settings['translation_context'] : '';

        // Allow override from options
        if (isset($options['quality'])) {
            $quality = $options['quality'];
        }
        if (isset($options['context'])) {
            $context = $options['context'];
        }

        // Get language names
        $source_language = $this->get_language_name($source_lang);
        $target_language = $this->get_language_name($target_lang);

        $prompt = "You are a professional translator specializing in website content translation.\n\n";
        $prompt .= "Task: Translate the following text from {$source_language} to {$target_language}.\n\n";

        if ($quality === 'high') {
            $prompt .= "Requirements:\n";
            $prompt .= "- Maintain the original tone and style\n";
            $prompt .= "- Use natural, native-sounding language\n";
            $prompt .= "- Preserve all formatting and special characters\n";
            $prompt .= "- Keep brand names, product names, and proper nouns unchanged unless they have standard translations\n";
        }

        if ($preserve_html) {
            $prompt .= "- Preserve ALL HTML tags, attributes, and structure exactly as they appear\n";
            $prompt .= "- Only translate the text content, not HTML tags or attributes\n";
        }

        if (!empty($context)) {
            $prompt .= "\nContext: {$context}\n";
        }

        if (isset($options['content_type'])) {
            $prompt .= "\nContent Type: " . $options['content_type'] . "\n";
        }

        $prompt .= "\nText to translate:\n{$text}\n\n";
        $prompt .= "Provide ONLY the translated text without any explanations or notes.";

        return $prompt;
    }

    /**
     * Make API request to OpenAI
     *
     * @param string $prompt The prompt to send
     * @param array $options Additional options
     * @return array API response
     */
    private function make_api_request($prompt, $options = array()) {
        $temperature = isset($options['temperature']) ? $options['temperature'] : 0.3;

        $body = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $temperature,
            'max_tokens' => 4000
        );

        $args = array(
            'method' => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => json_encode($body)
        );

        $response = wp_remote_post($this->api_endpoint, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API error', 'wc-ai-translator');
            return array(
                'success' => false,
                'error' => $error_message
            );
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => __('Invalid API response', 'wc-ai-translator')
            );
        }

        $translated_text = trim($data['choices'][0]['message']['content']);
        $tokens_used = isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : 0;

        return array(
            'success' => true,
            'text' => $translated_text,
            'tokens' => $tokens_used
        );
    }

    /**
     * Calculate cost based on tokens
     *
     * @param int $tokens Number of tokens
     * @return float Cost in USD
     */
    private function calculate_cost($tokens) {
        // Pricing for different models (per 1M tokens)
        $pricing = array(
            'gpt-4o' => array('input' => 2.50, 'output' => 10.00),
            'gpt-4o-mini' => array('input' => 0.150, 'output' => 0.600),
            'gpt-4-turbo' => array('input' => 10.00, 'output' => 30.00),
            'gpt-3.5-turbo' => array('input' => 0.50, 'output' => 1.50),
        );

        if (!isset($pricing[$this->model])) {
            return 0;
        }

        // Estimate 60% input, 40% output
        $input_tokens = $tokens * 0.6;
        $output_tokens = $tokens * 0.4;

        $cost = ($input_tokens / 1000000 * $pricing[$this->model]['input']) +
                ($output_tokens / 1000000 * $pricing[$this->model]['output']);

        return round($cost, 4);
    }

    /**
     * Get language name from code
     *
     * @param string $lang_code Language code
     * @return string Language name
     */
    private function get_language_name($lang_code) {
        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'tr' => 'Turkish',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'cs' => 'Czech',
            'el' => 'Greek',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
        );

        return isset($languages[$lang_code]) ? $languages[$lang_code] : $lang_code;
    }

    /**
     * Test API connection
     *
     * @return array Test result
     */
    public function test_connection() {
        $test_text = "Hello, this is a test.";
        $result = $this->translate($test_text, 'en', 'es');

        if ($result['success']) {
            return array(
                'success' => true,
                'message' => __('API connection successful!', 'wc-ai-translator'),
                'test_translation' => $result['translated_text']
            );
        }

        return array(
            'success' => false,
            'message' => $result['error']
        );
    }
}
