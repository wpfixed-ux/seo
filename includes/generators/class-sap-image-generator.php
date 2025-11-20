<?php
/**
 * AI Image Generator
 *
 * Manages AI image generation for products and articles using Gemini API
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/generators
 */

class SAP_Image_Generator {

    /**
     * Gemini AI instance
     *
     * @var SAP_Gemini_AI
     */
    private $gemini;

    /**
     * Constructor
     */
    public function __construct() {
        require_once SAP_INCLUDES_DIR . 'api/class-sap-gemini-ai.php';
        $this->gemini = new SAP_Gemini_AI();
    }

    /**
     * Register hooks
     */
    public function init() {
        // Register metaboxes
        add_action('add_meta_boxes', array($this, 'register_metaboxes'));

        // AJAX handlers
        add_action('wp_ajax_sap_generate_product_image', array($this, 'ajax_generate_product_image'));
        add_action('wp_ajax_sap_generate_article_image', array($this, 'ajax_generate_article_image'));
        add_action('wp_ajax_sap_change_image_background', array($this, 'ajax_change_background'));
        add_action('wp_ajax_sap_test_gemini_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Register metaboxes
     */
    public function register_metaboxes() {
        // Product image generator (for WooCommerce)
        if (class_exists('WooCommerce')) {
            add_meta_box(
                'sap_product_image_generator',
                __('AI Image Generator', 'seo-analytics-pro'),
                array($this, 'render_product_metabox'),
                'product',
                'side',
                'default'
            );
        }

        // Article/Post image generator
        add_meta_box(
            'sap_article_image_generator',
            __('AI Image Generator', 'seo-analytics-pro'),
            array($this, 'render_article_metabox'),
            'post',
            'side',
            'default'
        );

        // Page image generator
        add_meta_box(
            'sap_page_image_generator',
            __('AI Image Generator', 'seo-analytics-pro'),
            array($this, 'render_article_metabox'),
            'page',
            'side',
            'default'
        );
    }

    /**
     * Render product image generator metabox
     */
    public function render_product_metabox($post) {
        wp_nonce_field('sap_image_generator_nonce', 'sap_image_generator_nonce');

        $settings = get_option('sap_settings', array());
        $has_gemini_key = !empty($settings['gemini_api_key']);

        ?>
        <div class="sap-image-generator">
            <?php if (!$has_gemini_key): ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e('Gemini API key not configured.', 'seo-analytics-pro'); ?>
                    <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-settings'); ?>"><?php _e('Configure', 'seo-analytics-pro'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <!-- Background Change Section -->
            <div class="sap-section">
                <h4><?php _e('Change Product Background', 'seo-analytics-pro'); ?></h4>

                <div class="sap-background-presets">
                    <button type="button" class="button sap-bg-preset" data-preset="white" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-format-image"></span>
                        <?php _e('White Background', 'seo-analytics-pro'); ?>
                    </button>

                    <button type="button" class="button sap-bg-preset" data-preset="pastel" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-art"></span>
                        <?php _e('Pastel Background', 'seo-analytics-pro'); ?>
                    </button>

                    <button type="button" class="button sap-bg-preset" data-preset="gradient" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-admin-customizer"></span>
                        <?php _e('Gradient Background', 'seo-analytics-pro'); ?>
                    </button>

                    <button type="button" class="button sap-bg-preset" data-preset="3d" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-awards"></span>
                        <?php _e('3D Background', 'seo-analytics-pro'); ?>
                    </button>

                    <button type="button" class="button sap-bg-preset" data-preset="custom" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Custom Background', 'seo-analytics-pro'); ?>
                    </button>

                    <button type="button" class="button sap-bg-preset" data-preset="ai" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php _e('AI Generated', 'seo-analytics-pro'); ?>
                    </button>
                </div>

                <!-- Custom prompt for background -->
                <div class="sap-custom-prompt" style="display:none; margin-top:10px;">
                    <label>
                        <strong><?php _e('Custom Prompt', 'seo-analytics-pro'); ?></strong>
                    </label>
                    <textarea id="sap-bg-custom-prompt"
                              class="widefat"
                              rows="3"
                              placeholder="<?php _e('Describe the background you want...', 'seo-analytics-pro'); ?>"></textarea>
                </div>

                <div style="margin-top:10px;">
                    <button type="button"
                            id="sap-apply-background"
                            class="button button-primary"
                            data-post-id="<?php echo $post->ID; ?>"
                            <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <?php _e('Apply Background', 'seo-analytics-pro'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>

            <hr style="margin: 15px 0;">

            <!-- Generate New Image Section -->
            <div class="sap-section">
                <h4><?php _e('Generate Product Image', 'seo-analytics-pro'); ?></h4>

                <div class="sap-form-field">
                    <label>
                        <strong><?php _e('Image Prompt', 'seo-analytics-pro'); ?></strong>
                    </label>
                    <textarea id="sap-product-image-prompt"
                              class="widefat"
                              rows="4"
                              placeholder="<?php echo esc_attr(sprintf(__('Professional product photo of %s...', 'seo-analytics-pro'), get_the_title())); ?>"></textarea>
                    <p class="description"><?php _e('Describe the product image you want to generate', 'seo-analytics-pro'); ?></p>
                </div>

                <div style="margin-top:10px;">
                    <button type="button"
                            id="sap-generate-product-image"
                            class="button button-primary"
                            data-post-id="<?php echo $post->ID; ?>"
                            <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <?php _e('Generate Image', 'seo-analytics-pro'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>

            <!-- Result area -->
            <div id="sap-image-result" class="sap-result" style="display:none; margin-top:15px;"></div>

            <!-- Generation Log -->
            <div id="sap-generation-log" class="sap-log" style="display:none; margin-top:15px;">
                <h4><?php _e('Generation Log', 'seo-analytics-pro'); ?></h4>
                <div class="sap-log-content" style="background:#f5f5f5; padding:10px; max-height:200px; overflow-y:auto; font-family:monospace; font-size:12px;"></div>
            </div>
        </div>

        <style>
            .sap-image-generator .sap-section { margin-bottom: 15px; }
            .sap-image-generator .sap-section h4 { margin: 0 0 10px 0; }
            .sap-background-presets { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
            .sap-background-presets .button { text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .sap-background-presets .button.active { background: #2271b1; color: #fff; }
            .sap-background-presets .dashicons { font-size: 16px; width: 16px; height: 16px; }
            .sap-result.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
            .sap-result.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
            .sap-log { border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 3px; }
        </style>
        <?php
    }

    /**
     * Render article image generator metabox
     */
    public function render_article_metabox($post) {
        wp_nonce_field('sap_image_generator_nonce', 'sap_image_generator_nonce');

        $settings = get_option('sap_settings', array());
        $has_gemini_key = !empty($settings['gemini_api_key']);

        ?>
        <div class="sap-image-generator">
            <?php if (!$has_gemini_key): ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e('Gemini API key not configured.', 'seo-analytics-pro'); ?>
                    <a href="<?php echo admin_url('admin.php?page=seo-analytics-pro-settings'); ?>"><?php _e('Configure', 'seo-analytics-pro'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <div class="sap-section">
                <h4><?php _e('Generate Featured Image', 'seo-analytics-pro'); ?></h4>

                <div class="sap-form-field">
                    <label>
                        <input type="checkbox" id="sap-use-auto-prompt" checked>
                        <?php _e('Auto-generate prompt from content', 'seo-analytics-pro'); ?>
                    </label>
                </div>

                <div class="sap-form-field" id="sap-manual-prompt-field" style="display:none;">
                    <label>
                        <strong><?php _e('Image Prompt', 'seo-analytics-pro'); ?></strong>
                    </label>
                    <textarea id="sap-article-image-prompt"
                              class="widefat"
                              rows="4"
                              placeholder="<?php _e('Describe the image you want to generate...', 'seo-analytics-pro'); ?>"></textarea>
                </div>

                <div class="sap-form-field">
                    <label>
                        <strong><?php _e('Aspect Ratio', 'seo-analytics-pro'); ?></strong>
                    </label>
                    <select id="sap-article-aspect-ratio" class="widefat">
                        <option value="16:9"><?php _e('16:9 (Landscape)', 'seo-analytics-pro'); ?></option>
                        <option value="1:1"><?php _e('1:1 (Square)', 'seo-analytics-pro'); ?></option>
                        <option value="4:3"><?php _e('4:3 (Standard)', 'seo-analytics-pro'); ?></option>
                        <option value="9:16"><?php _e('9:16 (Portrait)', 'seo-analytics-pro'); ?></option>
                    </select>
                </div>

                <div style="margin-top:10px;">
                    <button type="button"
                            id="sap-generate-article-image"
                            class="button button-primary"
                            data-post-id="<?php echo $post->ID; ?>"
                            <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                        <?php _e('Generate Image', 'seo-analytics-pro'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>

            <!-- Result area -->
            <div id="sap-image-result" class="sap-result" style="display:none; margin-top:15px;"></div>

            <!-- Generation Log -->
            <div id="sap-generation-log" class="sap-log" style="display:none; margin-top:15px;">
                <h4><?php _e('Generation Log', 'seo-analytics-pro'); ?></h4>
                <div class="sap-log-content" style="background:#f5f5f5; padding:10px; max-height:200px; overflow-y:auto; font-family:monospace; font-size:12px;"></div>
            </div>
        </div>

        <style>
            .sap-image-generator .sap-section { margin-bottom: 15px; }
            .sap-image-generator .sap-section h4 { margin: 0 0 10px 0; }
            .sap-image-generator .sap-form-field { margin-bottom: 10px; }
            .sap-result.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
            .sap-result.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
            .sap-log { border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 3px; }
        </style>
        <?php
    }

    /**
     * AJAX: Generate product image
     */
    public function ajax_generate_product_image() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'seo-analytics-pro')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'seo-analytics-pro')));
        }

        // Build prompt if not provided
        if (empty($prompt)) {
            $prompt = sprintf(__('Professional product photo of %s, high quality, studio lighting, white background, 4k', 'seo-analytics-pro'), $post->post_title);
        }

        // Log generation start
        $log = array();
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Starting image generation...', 'seo-analytics-pro');
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Prompt:', 'seo-analytics-pro') . ' ' . $prompt;

        // Generate image
        $result = $this->gemini->generate_image($prompt, array(
            'aspect_ratio' => '1:1',
            'number_of_images' => 1
        ));

        if (is_wp_error($result)) {
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Error:', 'seo-analytics-pro') . ' ' . $result->get_error_message();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'log' => implode("\n", $log)
            ));
        }

        $log[] = '[' . current_time('H:i:s') . '] ' . sprintf(__('Generated %d image(s)', 'seo-analytics-pro'), $result['count']);

        // Save to media library
        if (!empty($result['images'][0]['data'])) {
            $filename = 'product-' . $post_id . '-' . time() . '.png';
            $attachment_id = $this->gemini->save_to_media_library($result['images'][0]['data'], $filename, $post_id);

            if (is_wp_error($attachment_id)) {
                $log[] = '[' . current_time('H:i:s') . '] ' . __('Error saving image:', 'seo-analytics-pro') . ' ' . $attachment_id->get_error_message();
                wp_send_json_error(array(
                    'message' => $attachment_id->get_error_message(),
                    'log' => implode("\n", $log)
                ));
            }

            $log[] = '[' . current_time('H:i:s') . '] ' . __('Image saved to media library', 'seo-analytics-pro');

            // Set as featured image if none exists
            if (!has_post_thumbnail($post_id)) {
                set_post_thumbnail($post_id, $attachment_id);
                $log[] = '[' . current_time('H:i:s') . '] ' . __('Set as featured image', 'seo-analytics-pro');
            }

            $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Generation completed successfully!', 'seo-analytics-pro');

            wp_send_json_success(array(
                'message' => __('Image generated successfully!', 'seo-analytics-pro'),
                'attachment_id' => $attachment_id,
                'image_url' => $image_url,
                'log' => implode("\n", $log)
            ));
        }

        wp_send_json_error(array(
            'message' => __('No image data received', 'seo-analytics-pro'),
            'log' => implode("\n", $log)
        ));
    }

    /**
     * AJAX: Generate article image
     */
    public function ajax_generate_article_image() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $auto_prompt = !empty($_POST['auto_prompt']);
        $aspect_ratio = sanitize_text_field($_POST['aspect_ratio'] ?? '16:9');

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'seo-analytics-pro')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'seo-analytics-pro')));
        }

        // Log generation start
        $log = array();
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Starting image generation...', 'seo-analytics-pro');

        // Generate image using article content
        $options = array(
            'aspect_ratio' => $aspect_ratio,
            'custom_prompt' => $auto_prompt ? '' : $prompt
        );

        $result = $this->gemini->generate_article_image($post->post_title, $post->post_content, $options);

        if (is_wp_error($result)) {
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Error:', 'seo-analytics-pro') . ' ' . $result->get_error_message();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'log' => implode("\n", $log)
            ));
        }

        $log[] = '[' . current_time('H:i:s') . '] ' . sprintf(__('Generated %d image(s)', 'seo-analytics-pro'), $result['count']);

        // Save to media library
        if (!empty($result['images'][0]['data'])) {
            $filename = 'article-' . $post_id . '-' . time() . '.png';
            $attachment_id = $this->gemini->save_to_media_library($result['images'][0]['data'], $filename, $post_id);

            if (is_wp_error($attachment_id)) {
                $log[] = '[' . current_time('H:i:s') . '] ' . __('Error saving image:', 'seo-analytics-pro') . ' ' . $attachment_id->get_error_message();
                wp_send_json_error(array(
                    'message' => $attachment_id->get_error_message(),
                    'log' => implode("\n", $log)
                ));
            }

            $log[] = '[' . current_time('H:i:s') . '] ' . __('Image saved to media library', 'seo-analytics-pro');

            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Set as featured image', 'seo-analytics-pro');

            $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Generation completed successfully!', 'seo-analytics-pro');

            wp_send_json_success(array(
                'message' => __('Image generated successfully!', 'seo-analytics-pro'),
                'attachment_id' => $attachment_id,
                'image_url' => $image_url,
                'log' => implode("\n", $log)
            ));
        }

        wp_send_json_error(array(
            'message' => __('No image data received', 'seo-analytics-pro'),
            'log' => implode("\n", $log)
        ));
    }

    /**
     * AJAX: Change image background
     */
    public function ajax_change_background() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $preset = sanitize_text_field($_POST['preset'] ?? 'white');
        $custom_prompt = sanitize_textarea_field($_POST['custom_prompt'] ?? '');

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'seo-analytics-pro')));
        }

        // Get current featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            wp_send_json_error(array('message' => __('No featured image found', 'seo-analytics-pro')));
        }

        $image_url = wp_get_attachment_url($thumbnail_id);

        // Log generation start
        $log = array();
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Starting background change...', 'seo-analytics-pro');
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Preset:', 'seo-analytics-pro') . ' ' . $preset;

        // Change background
        $options = array(
            'aspect_ratio' => '1:1',
            'custom_background' => $custom_prompt
        );

        $result = $this->gemini->change_background($image_url, $preset, $options);

        if (is_wp_error($result)) {
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Error:', 'seo-analytics-pro') . ' ' . $result->get_error_message();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'log' => implode("\n", $log)
            ));
        }

        $log[] = '[' . current_time('H:i:s') . '] ' . __('Background changed successfully', 'seo-analytics-pro');

        // Save new image
        if (!empty($result['images'][0]['data'])) {
            $filename = 'product-' . $post_id . '-bg-' . $preset . '-' . time() . '.png';
            $attachment_id = $this->gemini->save_to_media_library($result['images'][0]['data'], $filename, $post_id);

            if (!is_wp_error($attachment_id)) {
                $log[] = '[' . current_time('H:i:s') . '] ' . __('New image saved to media library', 'seo-analytics-pro');
                set_post_thumbnail($post_id, $attachment_id);
                $image_url = wp_get_attachment_image_url($attachment_id, 'medium');

                wp_send_json_success(array(
                    'message' => __('Background changed successfully!', 'seo-analytics-pro'),
                    'attachment_id' => $attachment_id,
                    'image_url' => $image_url,
                    'log' => implode("\n", $log)
                ));
            }
        }

        wp_send_json_error(array(
            'message' => __('Failed to process image', 'seo-analytics-pro'),
            'log' => implode("\n", $log)
        ));
    }

    /**
     * AJAX: Test Gemini connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('sap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'seo-analytics-pro')));
        }

        $result = $this->gemini->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }
}
