<?php
/**
 * AI Image Generator
 *
 * Manages AI image generation for products and articles using Hugging Face API
 *
 * @package    AIImageGenerator
 * @subpackage AIImageGenerator/includes/generators
 */

class AIMG_Image_Generator {

    /**
     * Hugging Face AI instance
     *
     * @var AIMG_HuggingFace
     */
    private $huggingface;

    /**
     * Constructor
     */
    public function __construct() {
        require_once AIMG_INCLUDES_DIR . 'api/class-aimg-huggingface.php';
        $this->huggingface = new AIMG_HuggingFace();
    }

    /**
     * Register hooks
     */
    public function init() {
        // Register metaboxes
        add_action('add_meta_boxes', array($this, 'register_metaboxes'));

        // AJAX handlers
        add_action('wp_ajax_aimg_generate_product_image', array($this, 'ajax_generate_product_image'));
        add_action('wp_ajax_aimg_generate_article_image', array($this, 'ajax_generate_article_image'));
        add_action('wp_ajax_aimg_change_image_background', array($this, 'ajax_change_background'));
        add_action('wp_ajax_aimg_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Register metaboxes
     */
    public function register_metaboxes() {
        // Product image generator (for WooCommerce)
        if (class_exists('WooCommerce')) {
            add_meta_box(
                'aimg_product_image_generator',
                __('AI Image Generator', 'ai-image-generator'),
                array($this, 'render_product_metabox'),
                'product',
                'side',
                'default'
            );
        }

        // Article/Post image generator
        add_meta_box(
            'aimg_article_image_generator',
            __('AI Image Generator', 'ai-image-generator'),
            array($this, 'render_article_metabox'),
            'post',
            'side',
            'default'
        );

        // Page image generator
        add_meta_box(
            'aimg_page_image_generator',
            __('AI Image Generator', 'ai-image-generator'),
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
        wp_nonce_field('aimg_image_generator_nonce', 'aimg_image_generator_nonce');

        $settings = get_option('aimg_settings', array());
        $has_hf_key = !empty($settings['huggingface_api_key']);

        ?>
        <div class="aimg-image-generator">
            <?php if (!$has_hf_key): ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e('Hugging Face API token not configured.', 'ai-image-generator'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-image-generator'); ?>"><?php _e('Configure', 'ai-image-generator'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <!-- Background Change Section -->
            <div class="aimg-section">
                <h4><?php _e('Change Product Background', 'ai-image-generator'); ?></h4>

                <div class="aimg-background-presets">
                    <button type="button" class="button aimg-bg-preset" data-preset="white" <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-format-image"></span>
                        <?php _e('White Background', 'ai-image-generator'); ?>
                    </button>

                    <button type="button" class="button aimg-bg-preset" data-preset="pastel" <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-art"></span>
                        <?php _e('Pastel Background', 'ai-image-generator'); ?>
                    </button>

                    <button type="button" class="button aimg-bg-preset" data-preset="gradient" <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-admin-customizer"></span>
                        <?php _e('Gradient Background', 'ai-image-generator'); ?>
                    </button>

                    <button type="button" class="button aimg-bg-preset" data-preset="3d" <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-awards"></span>
                        <?php _e('3D Background', 'ai-image-generator'); ?>
                    </button>

                    <button type="button" class="button aimg-bg-preset" data-preset="custom" <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Custom Background', 'ai-image-generator'); ?>
                    </button>

                    <button type="button" class="button aimg-bg-preset" data-preset="ai" <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php _e('AI Generated', 'ai-image-generator'); ?>
                    </button>
                </div>

                <!-- Custom prompt for background -->
                <div class="aimg-custom-prompt" style="display:none; margin-top:10px;">
                    <label>
                        <strong><?php _e('Custom Prompt', 'ai-image-generator'); ?></strong>
                    </label>
                    <textarea id="aimg-bg-custom-prompt"
                              class="widefat"
                              rows="3"
                              placeholder="<?php _e('Describe the background you want...', 'ai-image-generator'); ?>"></textarea>
                </div>

                <div style="margin-top:10px;">
                    <button type="button"
                            id="aimg-apply-background"
                            class="button button-primary"
                            data-post-id="<?php echo $post->ID; ?>"
                            <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <?php _e('Apply Background', 'ai-image-generator'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>

            <hr style="margin: 15px 0;">

            <!-- Generate New Image Section -->
            <div class="aimg-section">
                <h4><?php _e('Generate Product Image', 'ai-image-generator'); ?></h4>

                <div class="aimg-form-field">
                    <label>
                        <strong><?php _e('Image Prompt', 'ai-image-generator'); ?></strong>
                    </label>
                    <textarea id="aimg-product-image-prompt"
                              class="widefat"
                              rows="4"
                              placeholder="<?php echo esc_attr(sprintf(__('Professional product photo of %s...', 'ai-image-generator'), get_the_title())); ?>"></textarea>
                    <p class="description"><?php _e('Describe the product image you want to generate', 'ai-image-generator'); ?></p>
                </div>

                <div style="margin-top:10px;">
                    <button type="button"
                            id="aimg-generate-product-image"
                            class="button button-primary"
                            data-post-id="<?php echo $post->ID; ?>"
                            <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <?php _e('Generate Image', 'ai-image-generator'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>

            <!-- Result area -->
            <div id="aimg-image-result" class="aimg-result" style="display:none; margin-top:15px;"></div>

            <!-- Generation Log -->
            <div id="aimg-generation-log" class="aimg-log" style="display:none; margin-top:15px;">
                <h4><?php _e('Generation Log', 'ai-image-generator'); ?></h4>
                <div class="aimg-log-content" style="background:#f5f5f5; padding:10px; max-height:200px; overflow-y:auto; font-family:monospace; font-size:12px;"></div>
            </div>
        </div>

        <style>
            .aimg-image-generator .aimg-section { margin-bottom: 15px; }
            .aimg-image-generator .aimg-section h4 { margin: 0 0 10px 0; }
            .aimg-background-presets { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
            .aimg-background-presets .button { text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .aimg-background-presets .button.active { background: #2271b1; color: #fff; }
            .aimg-background-presets .dashicons { font-size: 16px; width: 16px; height: 16px; }
            .aimg-result.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
            .aimg-result.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
            .aimg-log { border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 3px; }
        </style>
        <?php
    }

    /**
     * Render article image generator metabox
     */
    public function render_article_metabox($post) {
        wp_nonce_field('aimg_image_generator_nonce', 'aimg_image_generator_nonce');

        $settings = get_option('aimg_settings', array());
        $has_hf_key = !empty($settings['huggingface_api_key']);

        ?>
        <div class="aimg-image-generator">
            <?php if (!$has_hf_key): ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e('Hugging Face API token not configured.', 'ai-image-generator'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-image-generator'); ?>"><?php _e('Configure', 'ai-image-generator'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <div class="aimg-section">
                <h4><?php _e('Generate Featured Image', 'ai-image-generator'); ?></h4>

                <div class="aimg-form-field">
                    <label>
                        <input type="checkbox" id="aimg-use-auto-prompt" checked>
                        <?php _e('Auto-generate prompt from content', 'ai-image-generator'); ?>
                    </label>
                </div>

                <div class="aimg-form-field" id="aimg-manual-prompt-field" style="display:none;">
                    <label>
                        <strong><?php _e('Image Prompt', 'ai-image-generator'); ?></strong>
                    </label>
                    <textarea id="aimg-article-image-prompt"
                              class="widefat"
                              rows="4"
                              placeholder="<?php _e('Describe the image you want to generate...', 'ai-image-generator'); ?>"></textarea>
                </div>

                <div class="aimg-form-field">
                    <label>
                        <strong><?php _e('Aspect Ratio', 'ai-image-generator'); ?></strong>
                    </label>
                    <select id="aimg-article-aspect-ratio" class="widefat">
                        <option value="16:9"><?php _e('16:9 (Landscape)', 'ai-image-generator'); ?></option>
                        <option value="1:1"><?php _e('1:1 (Square)', 'ai-image-generator'); ?></option>
                        <option value="4:3"><?php _e('4:3 (Standard)', 'ai-image-generator'); ?></option>
                        <option value="9:16"><?php _e('9:16 (Portrait)', 'ai-image-generator'); ?></option>
                    </select>
                </div>

                <div style="margin-top:10px;">
                    <button type="button"
                            id="aimg-generate-article-image"
                            class="button button-primary"
                            data-post-id="<?php echo $post->ID; ?>"
                            <?php echo !$has_hf_key ? 'disabled' : ''; ?>>
                        <?php _e('Generate Image', 'ai-image-generator'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>

            <!-- Result area -->
            <div id="aimg-image-result" class="aimg-result" style="display:none; margin-top:15px;"></div>

            <!-- Generation Log -->
            <div id="aimg-generation-log" class="aimg-log" style="display:none; margin-top:15px;">
                <h4><?php _e('Generation Log', 'ai-image-generator'); ?></h4>
                <div class="aimg-log-content" style="background:#f5f5f5; padding:10px; max-height:200px; overflow-y:auto; font-family:monospace; font-size:12px;"></div>
            </div>
        </div>

        <style>
            .aimg-image-generator .aimg-section { margin-bottom: 15px; }
            .aimg-image-generator .aimg-section h4 { margin: 0 0 10px 0; }
            .aimg-image-generator .aimg-form-field { margin-bottom: 10px; }
            .aimg-result.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
            .aimg-result.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
            .aimg-log { border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 3px; }
        </style>
        <?php
    }

    /**
     * AJAX: Generate product image
     */
    public function ajax_generate_product_image() {
        check_ajax_referer('aimg_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-image-generator')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'ai-image-generator')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'ai-image-generator')));
        }

        // Build prompt if not provided
        if (empty($prompt)) {
            $prompt = sprintf(__('Professional product photo of %s, high quality, studio lighting, white background, 4k', 'ai-image-generator'), $post->post_title);
        }

        // Log generation start
        $log = array();
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Starting image generation...', 'ai-image-generator');
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Prompt:', 'ai-image-generator') . ' ' . $prompt;

        // Generate image
        $result = $this->huggingface->generate_image($prompt, array(
            'aspect_ratio' => '1:1'
        ));

        if (is_wp_error($result)) {
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Error:', 'ai-image-generator') . ' ' . $result->get_error_message();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'log' => implode("\n", $log)
            ));
        }

        $log[] = '[' . current_time('H:i:s') . '] ' . sprintf(__('Generated %d image(s)', 'ai-image-generator'), $result['count']);

        // Save to media library
        if (!empty($result['images'][0]['data'])) {
            $filename = 'product-' . $post_id . '-' . time() . '.png';
            $attachment_id = $this->huggingface->save_to_media_library($result['images'][0]['data'], $filename, $post_id);

            if (is_wp_error($attachment_id)) {
                $log[] = '[' . current_time('H:i:s') . '] ' . __('Error saving image:', 'ai-image-generator') . ' ' . $attachment_id->get_error_message();
                wp_send_json_error(array(
                    'message' => $attachment_id->get_error_message(),
                    'log' => implode("\n", $log)
                ));
            }

            $log[] = '[' . current_time('H:i:s') . '] ' . __('Image saved to media library', 'ai-image-generator');

            // Set as featured image if none exists
            if (!has_post_thumbnail($post_id)) {
                set_post_thumbnail($post_id, $attachment_id);
                $log[] = '[' . current_time('H:i:s') . '] ' . __('Set as featured image', 'ai-image-generator');
            }

            $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Generation completed successfully!', 'ai-image-generator');

            wp_send_json_success(array(
                'message' => __('Image generated successfully!', 'ai-image-generator'),
                'attachment_id' => $attachment_id,
                'image_url' => $image_url,
                'log' => implode("\n", $log)
            ));
        }

        wp_send_json_error(array(
            'message' => __('No image data received', 'ai-image-generator'),
            'log' => implode("\n", $log)
        ));
    }

    /**
     * AJAX: Generate article image
     */
    public function ajax_generate_article_image() {
        check_ajax_referer('aimg_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-image-generator')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $auto_prompt = !empty($_POST['auto_prompt']);
        $aspect_ratio = sanitize_text_field($_POST['aspect_ratio'] ?? '16:9');

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'ai-image-generator')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'ai-image-generator')));
        }

        // Log generation start
        $log = array();
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Starting image generation...', 'ai-image-generator');

        // Generate image using article content
        $options = array(
            'aspect_ratio' => $aspect_ratio,
            'custom_prompt' => $auto_prompt ? '' : $prompt
        );

        $result = $this->huggingface->generate_article_image($post->post_title, $post->post_content, $options);

        if (is_wp_error($result)) {
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Error:', 'ai-image-generator') . ' ' . $result->get_error_message();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'log' => implode("\n", $log)
            ));
        }

        $log[] = '[' . current_time('H:i:s') . '] ' . sprintf(__('Generated %d image(s)', 'ai-image-generator'), $result['count']);

        // Save to media library
        if (!empty($result['images'][0]['data'])) {
            $filename = 'article-' . $post_id . '-' . time() . '.png';
            $attachment_id = $this->huggingface->save_to_media_library($result['images'][0]['data'], $filename, $post_id);

            if (is_wp_error($attachment_id)) {
                $log[] = '[' . current_time('H:i:s') . '] ' . __('Error saving image:', 'ai-image-generator') . ' ' . $attachment_id->get_error_message();
                wp_send_json_error(array(
                    'message' => $attachment_id->get_error_message(),
                    'log' => implode("\n", $log)
                ));
            }

            $log[] = '[' . current_time('H:i:s') . '] ' . __('Image saved to media library', 'ai-image-generator');

            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Set as featured image', 'ai-image-generator');

            $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Generation completed successfully!', 'ai-image-generator');

            wp_send_json_success(array(
                'message' => __('Image generated successfully!', 'ai-image-generator'),
                'attachment_id' => $attachment_id,
                'image_url' => $image_url,
                'log' => implode("\n", $log)
            ));
        }

        wp_send_json_error(array(
            'message' => __('No image data received', 'ai-image-generator'),
            'log' => implode("\n", $log)
        ));
    }

    /**
     * AJAX: Change image background
     */
    public function ajax_change_background() {
        check_ajax_referer('aimg_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-image-generator')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $preset = sanitize_text_field($_POST['preset'] ?? 'white');
        $custom_prompt = sanitize_textarea_field($_POST['custom_prompt'] ?? '');

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'ai-image-generator')));
        }

        // Get current featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            wp_send_json_error(array('message' => __('No featured image found', 'ai-image-generator')));
        }

        $image_url = wp_get_attachment_url($thumbnail_id);

        // Log generation start
        $log = array();
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Starting background change...', 'ai-image-generator');
        $log[] = '[' . current_time('H:i:s') . '] ' . __('Preset:', 'ai-image-generator') . ' ' . $preset;

        // Change background
        $options = array(
            'aspect_ratio' => '1:1',
            'custom_background' => $custom_prompt
        );

        $result = $this->huggingface->change_background($image_url, $preset, $options);

        if (is_wp_error($result)) {
            $log[] = '[' . current_time('H:i:s') . '] ' . __('Error:', 'ai-image-generator') . ' ' . $result->get_error_message();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'log' => implode("\n", $log)
            ));
        }

        $log[] = '[' . current_time('H:i:s') . '] ' . __('Background changed successfully', 'ai-image-generator');

        // Save new image
        if (!empty($result['images'][0]['data'])) {
            $filename = 'product-' . $post_id . '-bg-' . $preset . '-' . time() . '.png';
            $attachment_id = $this->huggingface->save_to_media_library($result['images'][0]['data'], $filename, $post_id);

            if (!is_wp_error($attachment_id)) {
                $log[] = '[' . current_time('H:i:s') . '] ' . __('New image saved to media library', 'ai-image-generator');
                set_post_thumbnail($post_id, $attachment_id);
                $image_url = wp_get_attachment_image_url($attachment_id, 'medium');

                wp_send_json_success(array(
                    'message' => __('Background changed successfully!', 'ai-image-generator'),
                    'attachment_id' => $attachment_id,
                    'image_url' => $image_url,
                    'log' => implode("\n", $log)
                ));
            }
        }

        wp_send_json_error(array(
            'message' => __('Failed to process image', 'ai-image-generator'),
            'log' => implode("\n", $log)
        ));
    }

    /**
     * AJAX: Test Hugging Face connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('aimg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-image-generator')));
        }

        $result = $this->huggingface->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }
}
