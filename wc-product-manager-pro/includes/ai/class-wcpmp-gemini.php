<?php
/**
 * Google Gemini AI Integration for image generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Gemini {

    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
    private $imagen_url = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict';

    public function __construct() {
        $this->api_key = get_option('wcpmp_gemini_api_key');
    }

    /**
     * Generate product image
     */
    public function generate_image($prompt, $style = 'product-photo') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'wc-product-manager-pro'));
        }

        $style_prompts = array(
            'product-photo' => 'Professional e-commerce product photography, white background, studio lighting, high resolution, 4k quality',
            'lifestyle' => 'Lifestyle product photography, natural setting, warm lighting, realistic environment',
            'minimal' => 'Minimalist product image, clean background, soft shadows, modern aesthetic',
        );

        $full_prompt = $prompt . '. ' . ($style_prompts[$style] ?? $style_prompts['product-photo']);

        $body = array(
            'instances' => array(
                array('prompt' => $full_prompt)
            ),
            'parameters' => array(
                'sampleCount' => 1,
                'aspectRatio' => '1:1',
                'safetyFilterLevel' => 'block_only_high',
            )
        );

        $response = wp_remote_post($this->imagen_url . '?key=' . $this->api_key, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            $this->log_api_call('gemini-imagen', 'generate', $body, $response->get_error_message(), 0);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        $this->log_api_call('gemini-imagen', 'generate', $body, $body_response, $status_code);

        if ($status_code !== 200) {
            return new WP_Error('api_error', $body_response['error']['message'] ?? 'Image generation failed');
        }

        if (isset($body_response['predictions'][0]['bytesBase64Encoded'])) {
            return array(
                'base64' => $body_response['predictions'][0]['bytesBase64Encoded'],
                'mime_type' => 'image/png'
            );
        }

        return new WP_Error('no_image', __('No image generated', 'wc-product-manager-pro'));
    }

    /**
     * Change image background
     */
    public function change_background($image_path, $new_background = 'white', $language = 'uk') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'wc-product-manager-pro'));
        }

        // Read image and convert to base64
        if (!file_exists($image_path)) {
            return new WP_Error('file_not_found', __('Image file not found', 'wc-product-manager-pro'));
        }

        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        $mime_type = mime_content_type($image_path);

        $background_prompts = array(
            'white' => 'pure white background',
            'transparent' => 'transparent background',
            'gradient' => 'soft gradient background',
            'studio' => 'professional studio background with soft shadows',
            'nature' => 'natural outdoor background',
        );

        $bg_prompt = $background_prompts[$new_background] ?? $new_background;

        $prompt = "Remove the current background from this product image and replace it with a $bg_prompt. Keep the product exactly as it is, only change the background. Maintain the product quality and details.";

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'inline_data' => array(
                                'mime_type' => $mime_type,
                                'data' => $base64_image
                            )
                        ),
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.4,
                'maxOutputTokens' => 8192,
            )
        );

        // Use Gemini Vision for analysis, then Imagen for generation
        $response = wp_remote_post($this->api_url . '?key=' . $this->api_key, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            $this->log_api_call('gemini', 'vision', $body, $response->get_error_message(), 0);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        $this->log_api_call('gemini', 'vision', array('prompt' => $prompt), $body_response, $status_code);

        if ($status_code !== 200) {
            return new WP_Error('api_error', $body_response['error']['message'] ?? 'Background change failed');
        }

        // Get product description from Gemini
        $product_description = '';
        if (isset($body_response['candidates'][0]['content']['parts'][0]['text'])) {
            $product_description = $body_response['candidates'][0]['content']['parts'][0]['text'];
        }

        // Generate new image with Imagen
        $generation_prompt = "Product photo: $product_description. $bg_prompt. Professional e-commerce photography, high quality.";

        return $this->generate_image($generation_prompt);
    }

    /**
     * Analyze product image and generate description
     */
    public function analyze_image($image_path, $language = 'uk') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'wc-product-manager-pro'));
        }

        if (!file_exists($image_path)) {
            return new WP_Error('file_not_found', __('Image file not found', 'wc-product-manager-pro'));
        }

        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        $mime_type = mime_content_type($image_path);

        $lang_name = $language === 'uk' ? 'Ukrainian' : 'Russian';

        $prompt = "Analyze this product image and provide in $lang_name language:
1. Product name/type
2. Key visible features
3. Estimated material/quality
4. Color description
5. Suggested target audience
6. SEO keywords (comma-separated)

Return as JSON:
{
  \"product_name\": \"\",
  \"features\": [],
  \"material\": \"\",
  \"color\": \"\",
  \"target_audience\": \"\",
  \"seo_keywords\": []
}";

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'inline_data' => array(
                                'mime_type' => $mime_type,
                                'data' => $base64_image
                            )
                        ),
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.3,
                'maxOutputTokens' => 2048,
            )
        );

        $response = wp_remote_post($this->api_url . '?key=' . $this->api_key, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            return new WP_Error('api_error', $body_response['error']['message'] ?? 'Image analysis failed');
        }

        $text = $body_response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Extract JSON from response
        preg_match('/\{[\s\S]*\}/', $text, $matches);
        if (!empty($matches[0])) {
            return json_decode($matches[0], true);
        }

        return new WP_Error('parse_error', __('Could not parse image analysis', 'wc-product-manager-pro'));
    }

    /**
     * AJAX handler for image generation
     */
    public function ajax_generate_image() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $prompt = sanitize_textarea_field($_POST['prompt']);
        $style = sanitize_text_field($_POST['style'] ?? 'product-photo');
        $product_id = intval($_POST['product_id'] ?? 0);

        if (empty($prompt)) {
            wp_send_json_error(__('Prompt is required', 'wc-product-manager-pro'));
        }

        $result = $this->generate_image($prompt, $style);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save image to media library
        $upload_dir = wp_upload_dir();
        $filename = 'wcpmp-generated-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;

        file_put_contents($filepath, base64_decode($result['base64']));

        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $result['mime_type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ), $filepath);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Associate with product if provided
        if ($product_id > 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'wcpmp_products';
            $product = $wpdb->get_row($wpdb->prepare("SELECT images FROM $table WHERE id = %d", $product_id));

            $images = $product && $product->images ? json_decode($product->images, true) : array();
            $images[] = $attachment_id;

            $wpdb->update($table, array('images' => json_encode($images)), array('id' => $product_id));
        }

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        ));
    }

    /**
     * AJAX handler for background change
     */
    public function ajax_change_background() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $background = sanitize_text_field($_POST['background'] ?? 'white');

        $image_path = get_attached_file($attachment_id);

        if (!$image_path) {
            wp_send_json_error(__('Image not found', 'wc-product-manager-pro'));
        }

        $result = $this->change_background($image_path, $background);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save new image
        $upload_dir = wp_upload_dir();
        $filename = 'wcpmp-bg-changed-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;

        file_put_contents($filepath, base64_decode($result['base64']));

        $new_attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $result['mime_type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ), $filepath);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($new_attachment_id, $filepath);
        wp_update_attachment_metadata($new_attachment_id, $attach_data);

        wp_send_json_success(array(
            'attachment_id' => $new_attachment_id,
            'url' => wp_get_attachment_url($new_attachment_id)
        ));
    }

    /**
     * Log API call
     */
    private function log_api_call($service, $endpoint, $request, $response, $status_code) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_api_logs';

        $wpdb->insert($table, array(
            'service' => $service,
            'endpoint' => $endpoint,
            'method' => 'POST',
            'request_data' => json_encode($request),
            'response_data' => is_string($response) ? $response : json_encode($response),
            'status_code' => $status_code,
            'created_at' => current_time('mysql')
        ));
    }
}
