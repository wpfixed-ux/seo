<?php
/**
 * OpenAI Integration for SEO descriptions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_OpenAI {

    private $api_key;
    private $model;
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = get_option('wcpmp_openai_api_key');
        $this->model = get_option('wcpmp_openai_model', 'gpt-4o');
    }

    /**
     * Generate SEO description for product
     */
    public function generate_seo_description($product_name, $product_description, $language = 'uk', $keywords = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'wc-product-manager-pro'));
        }

        $lang_name = $language === 'uk' ? 'Ukrainian' : 'Russian';
        $max_length = get_option('wcpmp_seo_description_length', 160);
        $keywords_str = !empty($keywords) ? implode(', ', $keywords) : '';

        $prompt = "Generate an SEO-optimized meta description for an e-commerce product in $lang_name language.

Product Name: $product_name
Product Description: $product_description
" . ($keywords_str ? "Target Keywords: $keywords_str" : "") . "

Requirements:
- Maximum $max_length characters
- Include main product benefits
- Use action-oriented language
- Include call to action
- Optimize for search engines
- Write naturally for humans

Return only the meta description text, no explanations.";

        $response = $this->call_api($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return trim($response);
    }

    /**
     * Generate full product description
     */
    public function generate_product_description($product_name, $attributes, $language = 'uk') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'wc-product-manager-pro'));
        }

        $lang_name = $language === 'uk' ? 'Ukrainian' : 'Russian';
        $attrs_str = is_array($attributes) ? json_encode($attributes, JSON_UNESCAPED_UNICODE) : $attributes;

        $prompt = "Generate a compelling e-commerce product description in $lang_name language.

Product Name: $product_name
Product Attributes: $attrs_str

Requirements:
- 150-300 words
- Highlight key features and benefits
- Use bullet points for specifications
- Include emotional triggers
- SEO-friendly with natural keyword integration
- Professional yet engaging tone

Format:
1. Opening paragraph (2-3 sentences about the product)
2. Key Features (bullet points)
3. Why choose this product (2-3 sentences)";

        $response = $this->call_api($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Generate personalized email content for CRM
     */
    public function generate_email_content($segment_info, $campaign_type, $products, $language = 'uk') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'wc-product-manager-pro'));
        }

        $lang_name = $language === 'uk' ? 'Ukrainian' : 'Russian';
        $products_str = json_encode($products, JSON_UNESCAPED_UNICODE);

        $prompt = "Generate a personalized marketing email in $lang_name language.

Customer Segment: $segment_info
Campaign Type: $campaign_type
Featured Products: $products_str

Requirements:
- Personalized greeting (use {customer_name} placeholder)
- Engaging subject line
- Clear value proposition
- Product recommendations with descriptions
- Strong call to action
- Include {coupon_code} placeholder if discount
- Professional but friendly tone

Return JSON with:
{
  \"subject\": \"email subject line\",
  \"preview_text\": \"email preview text (50 chars)\",
  \"body_html\": \"full email HTML content\"
}";

        $response = $this->call_api($prompt, true);

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode($response, true);
    }

    /**
     * Chat completion for Telegram bot
     */
    public function chat_completion($messages, $context = '', $language = 'uk') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'wc-product-manager-pro'));
        }

        $lang_name = $language === 'uk' ? 'Ukrainian' : 'Russian';

        $system_message = "You are a helpful product consultant for an online store.
Respond in $lang_name language.
You help customers find products, answer questions about specifications, availability, and provide purchase links.
Be friendly, professional, and concise.
When recommending products, include the product name, key features, and price.
If you don't know something, honestly say so and offer to help find the information.

Available product information:
$context";

        $api_messages = array(
            array('role' => 'system', 'content' => $system_message)
        );

        foreach ($messages as $msg) {
            $api_messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }

        $body = array(
            'model' => $this->model,
            'messages' => $api_messages,
            'temperature' => 0.7,
            'max_tokens' => 1000,
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            $this->log_api_call('openai', 'chat/completions', $body, $response->get_error_message(), 0);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        $tokens = isset($body_response['usage']['total_tokens']) ? $body_response['usage']['total_tokens'] : 0;
        $this->log_api_call('openai', 'chat/completions', $body, $body_response, $status_code, $tokens);

        if ($status_code !== 200) {
            return new WP_Error('api_error', $body_response['error']['message'] ?? 'API Error');
        }

        return $body_response['choices'][0]['message']['content'];
    }

    /**
     * AJAX handler for generating SEO description
     */
    public function ajax_generate_description() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_id = intval($_POST['product_id']);
        $language = sanitize_text_field($_POST['language'] ?? 'uk');

        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $product_id));

        if (!$product) {
            wp_send_json_error(__('Product not found', 'wc-product-manager-pro'));
        }

        $name = $language === 'uk' ? $product->name_uk : $product->name_ru;
        $description = $language === 'uk' ? $product->description_uk : $product->description_ru;

        $result = $this->generate_seo_description($name, $description, $language);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save to database
        $field = $language === 'uk' ? 'seo_description_uk' : 'seo_description_ru';
        $wpdb->update($table, array($field => $result), array('id' => $product_id));

        wp_send_json_success(array(
            'description' => $result,
            'product_id' => $product_id
        ));
    }

    /**
     * AJAX handler for bulk generation
     */
    public function ajax_bulk_generate() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_ids = array_map('intval', $_POST['product_ids'] ?? array());
        $language = sanitize_text_field($_POST['language'] ?? 'uk');

        if (empty($product_ids)) {
            wp_send_json_error(__('No products selected', 'wc-product-manager-pro'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_products';
        $results = array();
        $errors = array();

        foreach ($product_ids as $product_id) {
            $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $product_id));

            if (!$product) {
                $errors[] = sprintf(__('Product %d not found', 'wc-product-manager-pro'), $product_id);
                continue;
            }

            $name = $language === 'uk' ? $product->name_uk : $product->name_ru;
            $description = $language === 'uk' ? $product->description_uk : $product->description_ru;

            $result = $this->generate_seo_description($name, $description, $language);

            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Product %d: %s', 'wc-product-manager-pro'), $product_id, $result->get_error_message());
                continue;
            }

            $field = $language === 'uk' ? 'seo_description_uk' : 'seo_description_ru';
            $wpdb->update($table, array($field => $result), array('id' => $product_id));

            $results[] = array(
                'product_id' => $product_id,
                'description' => $result
            );

            // Rate limiting
            usleep(500000); // 0.5 second delay
        }

        wp_send_json_success(array(
            'generated' => $results,
            'errors' => $errors
        ));
    }

    /**
     * Call OpenAI API
     */
    private function call_api($prompt, $json_response = false) {
        $messages = array(
            array('role' => 'user', 'content' => $prompt)
        );

        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => floatval(get_option('wcpmp_ai_temperature', 0.7)),
            'max_tokens' => 2000,
        );

        if ($json_response) {
            $body['response_format'] = array('type' => 'json_object');
        }

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            $this->log_api_call('openai', 'chat/completions', $body, $response->get_error_message(), 0);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        $tokens = isset($body_response['usage']['total_tokens']) ? $body_response['usage']['total_tokens'] : 0;
        $cost = $this->calculate_cost($tokens);
        $this->log_api_call('openai', 'chat/completions', $body, $body_response, $status_code, $tokens, $cost);

        if ($status_code !== 200) {
            return new WP_Error('api_error', $body_response['error']['message'] ?? 'API Error');
        }

        return $body_response['choices'][0]['message']['content'];
    }

    /**
     * Calculate API cost
     */
    private function calculate_cost($tokens) {
        // GPT-4o pricing: $5/1M input, $15/1M output (approximate)
        return ($tokens / 1000000) * 10;
    }

    /**
     * Log API call
     */
    private function log_api_call($service, $endpoint, $request, $response, $status_code, $tokens = 0, $cost = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_api_logs';

        $wpdb->insert($table, array(
            'service' => $service,
            'endpoint' => $endpoint,
            'method' => 'POST',
            'request_data' => json_encode($request),
            'response_data' => is_string($response) ? $response : json_encode($response),
            'status_code' => $status_code,
            'tokens_used' => $tokens,
            'cost' => $cost,
            'created_at' => current_time('mysql')
        ));
    }
}
