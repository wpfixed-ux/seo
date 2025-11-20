<?php
/**
 * Gemini AI API Integration
 *
 * Handles image generation using Google Gemini Imagen API
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/api
 */

class SAP_Gemini_AI {

    /**
     * Gemini API endpoint for image generation
     */
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:predict';

    /**
     * Check if Gemini API is configured
     *
     * @return bool
     */
    public function is_configured() {
        $settings = get_option('sap_settings', array());
        return !empty($settings['gemini_api_key']);
    }

    /**
     * Get API key
     *
     * @return string
     */
    private function get_api_key() {
        $settings = get_option('sap_settings', array());
        return $settings['gemini_api_key'] ?? '';
    }

    /**
     * Generate image from prompt
     *
     * @param string $prompt Image generation prompt
     * @param array $options Generation options
     * @return array|WP_Error Generated image data or error
     */
    public function generate_image($prompt, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'seo-analytics-pro'));
        }

        $defaults = array(
            'number_of_images' => 1,
            'aspect_ratio' => '1:1', // 1:1, 3:4, 4:3, 9:16, 16:9
            'safety_filter_level' => 'block_medium_and_above',
            'person_generation' => 'allow_adult',
        );

        $options = wp_parse_args($options, $defaults);

        $api_key = $this->get_api_key();

        // Prepare request body
        $body = array(
            'instances' => array(
                array(
                    'prompt' => $prompt
                )
            ),
            'parameters' => array(
                'sampleCount' => $options['number_of_images'],
                'aspectRatio' => $options['aspect_ratio'],
                'safetyFilterLevel' => $options['safety_filter_level'],
                'personGeneration' => $options['person_generation']
            )
        );

        // Make API request
        $response = wp_remote_post($this->get_endpoint_url($api_key), array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? __('Unknown error occurred', 'seo-analytics-pro');
            return new WP_Error('api_error', $error_message);
        }

        return $this->process_response($data);
    }

    /**
     * Change image background
     *
     * @param string $image_url URL of the original image
     * @param string $background_type Background type (white, pastel, gradient, custom, ai, 3d)
     * @param array $options Additional options
     * @return array|WP_Error Processed image data or error
     */
    public function change_background($image_url, $background_type, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'seo-analytics-pro'));
        }

        // Download original image
        $image_data = $this->download_image($image_url);
        if (is_wp_error($image_data)) {
            return $image_data;
        }

        // Generate prompt based on background type
        $prompt = $this->build_background_prompt($background_type, $options);

        // For now, we'll use image generation with the prompt
        // In production, you might want to use image editing API if available
        $result = $this->generate_image($prompt, array(
            'aspect_ratio' => $options['aspect_ratio'] ?? '1:1'
        ));

        return $result;
    }

    /**
     * Remove background from image
     *
     * @param string $image_url URL of the original image
     * @return array|WP_Error Processed image data or error
     */
    public function remove_background($image_url) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'seo-analytics-pro'));
        }

        // Note: Background removal might need a different API
        // This is a placeholder implementation
        return new WP_Error('not_implemented', __('Background removal is not yet implemented', 'seo-analytics-pro'));
    }

    /**
     * Build prompt for background change
     *
     * @param string $background_type Background type
     * @param array $options Options
     * @return string Generated prompt
     */
    private function build_background_prompt($background_type, $options) {
        $base_prompt = $options['base_prompt'] ?? __('Product photography', 'seo-analytics-pro');

        switch ($background_type) {
            case 'white':
                return "{$base_prompt}, clean white background, professional studio lighting, high quality, 4k";

            case 'pastel':
                $color = $options['pastel_color'] ?? 'soft pink';
                return "{$base_prompt}, pastel {$color} background, soft lighting, minimalist, high quality, 4k";

            case 'gradient':
                $colors = $options['gradient_colors'] ?? array('blue', 'purple');
                return "{$base_prompt}, smooth gradient background from {$colors[0]} to {$colors[1]}, modern, high quality, 4k";

            case 'custom':
                $custom_bg = $options['custom_background'] ?? 'abstract colorful background';
                return "{$base_prompt}, {$custom_bg}, high quality, 4k";

            case 'ai':
                $ai_prompt = $options['ai_prompt'] ?? 'creative artistic background';
                return "{$base_prompt}, {$ai_prompt}, high quality, 4k";

            case '3d':
                return "{$base_prompt}, 3D rendered background, modern, sleek, professional, high quality, 4k";

            default:
                return "{$base_prompt}, professional background, high quality, 4k";
        }
    }

    /**
     * Get API endpoint URL with key
     *
     * @param string $api_key API key
     * @return string Complete endpoint URL
     */
    private function get_endpoint_url($api_key) {
        return self::API_ENDPOINT . '?key=' . $api_key;
    }

    /**
     * Process API response
     *
     * @param array $data Response data
     * @return array Processed image data
     */
    private function process_response($data) {
        $images = array();

        if (isset($data['predictions']) && is_array($data['predictions'])) {
            foreach ($data['predictions'] as $prediction) {
                if (isset($prediction['bytesBase64Encoded'])) {
                    $images[] = array(
                        'data' => $prediction['bytesBase64Encoded'],
                        'mime_type' => 'image/png'
                    );
                }
            }
        }

        return array(
            'images' => $images,
            'count' => count($images)
        );
    }

    /**
     * Download image from URL
     *
     * @param string $url Image URL
     * @return array|WP_Error Image data or error
     */
    private function download_image($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $image_data = wp_remote_retrieve_body($response);
        $mime_type = wp_remote_retrieve_header($response, 'content-type');

        return array(
            'data' => base64_encode($image_data),
            'mime_type' => $mime_type
        );
    }

    /**
     * Save generated image to WordPress media library
     *
     * @param string $image_base64 Base64 encoded image data
     * @param string $filename Filename for the image
     * @param int $post_id Associated post ID
     * @return int|WP_Error Attachment ID or error
     */
    public function save_to_media_library($image_base64, $filename, $post_id = 0) {
        // Decode base64 image
        $image_data = base64_decode($image_base64);

        if ($image_data === false) {
            return new WP_Error('decode_error', __('Failed to decode image data', 'seo-analytics-pro'));
        }

        // Create upload
        $upload = wp_upload_bits($filename, null, $image_data);

        if ($upload['error']) {
            return new WP_Error('upload_error', $upload['error']);
        }

        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    /**
     * Generate image for article content
     *
     * @param string $article_title Article title
     * @param string $article_content Article content (excerpt)
     * @param array $options Generation options
     * @return array|WP_Error Generated image data or error
     */
    public function generate_article_image($article_title, $article_content = '', $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'seo-analytics-pro'));
        }

        // Build prompt from article content
        $prompt = $this->build_article_image_prompt($article_title, $article_content, $options);

        return $this->generate_image($prompt, array(
            'aspect_ratio' => $options['aspect_ratio'] ?? '16:9',
            'number_of_images' => 1
        ));
    }

    /**
     * Build prompt for article image generation
     *
     * @param string $title Article title
     * @param string $content Article content
     * @param array $options Options
     * @return string Generated prompt
     */
    private function build_article_image_prompt($title, $content, $options) {
        $custom_prompt = $options['custom_prompt'] ?? '';

        if (!empty($custom_prompt)) {
            return $custom_prompt;
        }

        // Auto-generate prompt from title and content
        $prompt = "Create a professional, high-quality featured image for an article titled: {$title}. ";

        if (!empty($content)) {
            $excerpt = wp_trim_words($content, 50);
            $prompt .= "Article summary: {$excerpt}. ";
        }

        $prompt .= "The image should be modern, professional, relevant to the topic, high quality, 4k resolution.";

        return $prompt;
    }

    /**
     * Test API connection
     *
     * @return array|WP_Error Test result
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'seo-analytics-pro'));
        }

        // Generate a simple test image
        $result = $this->generate_image('A simple red circle on white background', array(
            'number_of_images' => 1
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'status' => 'success',
            'message' => __('Gemini API connection successful', 'seo-analytics-pro'),
            'images_generated' => $result['count']
        );
    }
}
