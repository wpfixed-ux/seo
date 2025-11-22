<?php
/**
 * Hugging Face AI API Integration
 *
 * Handles image generation using Hugging Face Inference API (Stable Diffusion)
 *
 * @package    AIImageGenerator
 * @subpackage AIImageGenerator/includes/api
 */

class AIMG_HuggingFace {

    /**
     * Hugging Face API endpoint
     */
    private const API_ENDPOINT = 'https://api-inference.huggingface.co/models/';

    /**
     * Default model for image generation
     */
    private const DEFAULT_MODEL = 'stabilityai/stable-diffusion-xl-base-1.0';

    /**
     * Alternative models (faster but lower quality)
     */
    private const FAST_MODEL = 'runwayml/stable-diffusion-v1-5';

    /**
     * Check if Hugging Face API is configured
     *
     * @return bool
     */
    public function is_configured() {
        $settings = get_option('aimg_settings', array());
        return !empty($settings['huggingface_api_key']);
    }

    /**
     * Get API key
     *
     * @return string
     */
    private function get_api_key() {
        $settings = get_option('aimg_settings', array());
        return $settings['huggingface_api_key'] ?? '';
    }

    /**
     * Get model to use
     *
     * @param bool $fast_mode Use faster model
     * @return string
     */
    private function get_model($fast_mode = false) {
        $settings = get_option('aimg_settings', array());

        // Check if user specified custom model
        if (!empty($settings['huggingface_model'])) {
            return $settings['huggingface_model'];
        }

        return $fast_mode ? self::FAST_MODEL : self::DEFAULT_MODEL;
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
            return new WP_Error('no_api_key', __('Hugging Face API token not configured', 'seo-analytics-pro'));
        }

        $defaults = array(
            'negative_prompt' => 'blurry, bad quality, distorted, ugly',
            'num_inference_steps' => 30,
            'guidance_scale' => 7.5,
            'fast_mode' => false,
            'width' => 1024,
            'height' => 1024
        );

        $options = wp_parse_args($options, $defaults);

        // Adjust dimensions based on aspect ratio if provided
        if (isset($options['aspect_ratio'])) {
            $dimensions = $this->get_dimensions_from_aspect_ratio($options['aspect_ratio']);
            $options['width'] = $dimensions['width'];
            $options['height'] = $dimensions['height'];
        }

        $api_key = $this->get_api_key();
        $model = $this->get_model($options['fast_mode']);

        // Prepare request payload
        $payload = array(
            'inputs' => $prompt,
            'parameters' => array(
                'negative_prompt' => $options['negative_prompt'],
                'num_inference_steps' => $options['num_inference_steps'],
                'guidance_scale' => $options['guidance_scale'],
                'width' => $options['width'],
                'height' => $options['height']
            )
        );

        // Make API request
        $response = wp_remote_post(self::API_ENDPOINT . $model, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 120 // Increase timeout for image generation
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Check if model is loading
        if ($status_code === 503) {
            $data = json_decode($response_body, true);
            if (isset($data['error']) && strpos($data['error'], 'loading') !== false) {
                return new WP_Error('model_loading', __('Model is loading. Please wait a moment and try again.', 'ai-image-generator'));
            }
        }

        if ($status_code !== 200) {
            $data = json_decode($response_body, true);
            $error_message = $data['error'] ?? __('Unknown error occurred', 'ai-image-generator');
            return new WP_Error('api_error', 'Hugging Face API error: ' . $error_message);
        }

        // Hugging Face returns raw image bytes
        $image_data = base64_encode($response_body);

        return array(
            'images' => array(
                array(
                    'data' => $image_data,
                    'mime_type' => 'image/png'
                )
            ),
            'count' => 1,
            'model' => $model
        );
    }

    /**
     * Get dimensions from aspect ratio
     *
     * @param string $aspect_ratio Aspect ratio (e.g., "16:9", "1:1")
     * @return array Width and height
     */
    private function get_dimensions_from_aspect_ratio($aspect_ratio) {
        $dimensions = array(
            'width' => 1024,
            'height' => 1024
        );

        switch ($aspect_ratio) {
            case '16:9':
                $dimensions = array('width' => 1024, 'height' => 576);
                break;
            case '9:16':
                $dimensions = array('width' => 576, 'height' => 1024);
                break;
            case '4:3':
                $dimensions = array('width' => 1024, 'height' => 768);
                break;
            case '3:4':
                $dimensions = array('width' => 768, 'height' => 1024);
                break;
            case '1:1':
            default:
                $dimensions = array('width' => 1024, 'height' => 1024);
                break;
        }

        return $dimensions;
    }

    /**
     * Change image background
     *
     * @param string $image_url URL of the original image
     * @param string $background_type Background type
     * @param array $options Additional options
     * @return array|WP_Error Processed image data or error
     */
    public function change_background($image_url, $background_type, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Hugging Face API token not configured', 'seo-analytics-pro'));
        }

        // Build prompt for background change
        $prompt = $this->build_background_prompt($background_type, $options);

        // Generate new image with the background
        $result = $this->generate_image($prompt, array(
            'aspect_ratio' => $options['aspect_ratio'] ?? '1:1',
            'fast_mode' => false
        ));

        return $result;
    }

    /**
     * Build prompt for background change
     *
     * @param string $background_type Background type
     * @param array $options Options
     * @return string Generated prompt
     */
    private function build_background_prompt($background_type, $options) {
        $base_prompt = $options['base_prompt'] ?? __('Product photography', 'ai-image-generator');

        switch ($background_type) {
            case 'white':
                return "{$base_prompt}, clean white background, professional studio lighting, high quality, sharp focus, 8k";

            case 'pastel':
                $color = $options['pastel_color'] ?? 'soft pink';
                return "{$base_prompt}, pastel {$color} background, soft lighting, minimalist, high quality, 8k";

            case 'gradient':
                $colors = $options['gradient_colors'] ?? array('blue', 'purple');
                $color1 = $colors[0] ?? 'blue';
                $color2 = $colors[1] ?? 'purple';
                return "{$base_prompt}, smooth gradient background from {$color1} to {$color2}, modern, high quality, 8k";

            case 'custom':
                $custom_bg = $options['custom_background'] ?? 'abstract colorful background';
                return "{$base_prompt}, {$custom_bg}, high quality, professional, 8k";

            case 'ai':
                $ai_prompt = $options['ai_prompt'] ?? 'creative artistic background';
                return "{$base_prompt}, {$ai_prompt}, professional, high quality, 8k";

            case '3d':
                return "{$base_prompt}, 3D rendered background, modern, sleek, professional, octane render, high quality, 8k";

            default:
                return "{$base_prompt}, professional background, high quality, sharp focus, 8k";
        }
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
            return new WP_Error('decode_error', __('Failed to decode image data', 'ai-image-generator'));
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
            return new WP_Error('no_api_key', __('Hugging Face API token not configured', 'seo-analytics-pro'));
        }

        // Build prompt from article content
        $prompt = $this->build_article_image_prompt($article_title, $article_content, $options);

        return $this->generate_image($prompt, array(
            'aspect_ratio' => $options['aspect_ratio'] ?? '16:9',
            'fast_mode' => false
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
        $prompt = "Professional featured image for article: {$title}";

        if (!empty($content)) {
            $excerpt = wp_trim_words($content, 30);
            $prompt .= ". Context: {$excerpt}";
        }

        $prompt .= ". High quality, modern, professional, 8k, sharp focus, detailed";

        return $prompt;
    }

    /**
     * Test API connection
     *
     * @return array|WP_Error Test result
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('Hugging Face API token not configured', 'seo-analytics-pro'));
        }

        // Generate a simple test image
        $result = $this->generate_image('a simple red circle on white background', array(
            'fast_mode' => true, // Use fast model for testing
            'num_inference_steps' => 20 // Fewer steps for faster testing
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'status' => 'success',
            'message' => __('Hugging Face API connection successful', 'ai-image-generator'),
            'images_generated' => $result['count'],
            'model_used' => $result['model']
        );
    }
}
