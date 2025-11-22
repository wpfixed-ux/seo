<?php
/**
 * Admin-specific functionality
 *
 * @package    AIImageGenerator
 * @subpackage AIImageGenerator/includes/admin
 */

class AIMG_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, AIMG_ASSETS_URL . 'css/admin.css', array(), $this->version);
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name . '-image-gen', AIMG_ASSETS_URL . 'js/admin/image-generator.js', array('jquery'), $this->version, true);
        wp_localize_script($this->plugin_name . '-image-gen', 'aimgData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aimg_nonce'),
            'strings' => array(
                'generating' => __('Generating...', 'ai-image-generator'),
                'success' => __('Success!', 'ai-image-generator'),
                'error' => __('Error occurred', 'ai-image-generator'),
            )
        ));
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'AI Image Generator',
            'AI Images',
            'manage_options',
            'ai-image-generator',
            array($this, 'display_settings_page'),
            'dashicons-format-image',
            31
        );

        add_submenu_page(
            'ai-image-generator',
            __('Settings', 'ai-image-generator'),
            __('Settings', 'ai-image-generator'),
            'manage_options',
            'ai-image-generator',
            array($this, 'display_settings_page')
        );
    }

    public function register_settings() {
        register_setting('aimg_settings', 'aimg_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['huggingface_api_key'])) {
            $sanitized['huggingface_api_key'] = sanitize_text_field($input['huggingface_api_key']);
        }

        if (isset($input['huggingface_model'])) {
            $sanitized['huggingface_model'] = sanitize_text_field($input['huggingface_model']);
        }

        return $sanitized;
    }

    /**
     * Settings Page
     */
    public function display_settings_page() {
        $settings = get_option('aimg_settings', array());
        ?>
        <div class="wrap aimg-wrap">
            <h1><?php _e('AI Image Generator Settings', 'ai-image-generator'); ?></h1>

            <form method="post" action="options.php" class="aimg-form aimg-form-large">
                <?php settings_fields('aimg_settings'); ?>

                <div class="aimg-form-section">
                    <h2><?php _e('Hugging Face API Configuration', 'ai-image-generator'); ?></h2>
                    <p class="description"><?php _e('Configure your Hugging Face API for FREE AI image generation using Stable Diffusion XL.', 'ai-image-generator'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="huggingface-api-key">
                                    <?php _e('Hugging Face API Token', 'ai-image-generator'); ?> *
                                </label>
                            </th>
                            <td>
                                <input type="password"
                                       id="huggingface-api-key"
                                       name="aimg_settings[huggingface_api_key]"
                                       value="<?php echo esc_attr($settings['huggingface_api_key'] ?? ''); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Required for AI image generation. Completely FREE to use!', 'ai-image-generator'); ?>
                                    <a href="https://huggingface.co/settings/tokens" target="_blank"><?php _e('Get API Token', 'ai-image-generator'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="huggingface-model">
                                    <?php _e('Model (Optional)', 'ai-image-generator'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="huggingface-model"
                                       name="aimg_settings[huggingface_model]"
                                       value="<?php echo esc_attr($settings['huggingface_model'] ?? ''); ?>"
                                       class="regular-text"
                                       placeholder="stabilityai/stable-diffusion-xl-base-1.0">
                                <p class="description">
                                    <?php _e('Leave empty to use default model (Stable Diffusion XL). You can specify a different Hugging Face model here.', 'ai-image-generator'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="aimg-form-section">
                    <h2><?php _e('Features', 'ai-image-generator'); ?></h2>
                    <p class="description"><?php _e('This plugin provides AI-powered image generation for:', 'ai-image-generator'); ?></p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><?php _e('Product images with customizable backgrounds (WooCommerce)', 'ai-image-generator'); ?></li>
                        <li><?php _e('Featured images for articles and posts', 'ai-image-generator'); ?></li>
                        <li><?php _e('Background presets: White, Pastel, Gradient, 3D, Custom, AI-generated', 'ai-image-generator'); ?></li>
                        <li><?php _e('Auto-prompt generation from article content', 'ai-image-generator'); ?></li>
                        <li><?php _e('Multiple aspect ratios (16:9, 1:1, 4:3, 9:16)', 'ai-image-generator'); ?></li>
                    </ul>
                </div>

                <div class="aimg-form-section">
                    <h2><?php _e('How to Use', 'ai-image-generator'); ?></h2>
                    <ol>
                        <li><?php _e('Get your FREE Hugging Face API token from the link above', 'ai-image-generator'); ?></li>
                        <li><?php _e('Save the token in the field above', 'ai-image-generator'); ?></li>
                        <li><?php _e('Test the connection using the button below', 'ai-image-generator'); ?></li>
                        <li><?php _e('Go to any Product, Post, or Page editor', 'ai-image-generator'); ?></li>
                        <li><?php _e('Find the "AI Image Generator" metabox in the sidebar', 'ai-image-generator'); ?></li>
                        <li><?php _e('Generate images with presets or custom prompts!', 'ai-image-generator'); ?></li>
                    </ol>
                </div>

                <p class="submit">
                    <?php submit_button(__('Save Settings', 'ai-image-generator'), 'primary', 'submit', false); ?>
                    <button type="button" class="button" id="test-huggingface-connection"><?php _e('Test Connection', 'ai-image-generator'); ?></button>
                </p>
            </form>

            <div id="api-test-results" class="aimg-results" style="display:none; margin-top: 20px;"></div>
        </div>

        <style>
            .aimg-wrap { max-width: 900px; }
            .aimg-form-section { margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .aimg-form-section h2 { margin-top: 0; }
            .aimg-results { padding: 15px; border-radius: 4px; }
            .aimg-results.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .aimg-results.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        </style>
        <?php
    }
}
