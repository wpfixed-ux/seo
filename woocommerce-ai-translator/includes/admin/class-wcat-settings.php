<?php
/**
 * Settings functionality
 *
 * @package WC_AI_Translator
 */

class WCAT_Settings {

    /**
     * Plugin name
     */
    private $plugin_name;

    /**
     * Plugin version
     */
    private $version;

    /**
     * Constructor
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wcat_settings_group',
            'wcat_settings',
            array($this, 'sanitize_settings')
        );

        // API Settings section
        add_settings_section(
            'wcat_api_section',
            __('API Settings', 'wc-ai-translator'),
            array($this, 'api_section_callback'),
            'wc-ai-translator-settings'
        );

        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'wc-ai-translator'),
            array($this, 'api_key_callback'),
            'wc-ai-translator-settings',
            'wcat_api_section'
        );

        add_settings_field(
            'openai_model',
            __('OpenAI Model', 'wc-ai-translator'),
            array($this, 'model_callback'),
            'wc-ai-translator-settings',
            'wcat_api_section'
        );

        // Translation Settings section
        add_settings_section(
            'wcat_translation_section',
            __('Translation Settings', 'wc-ai-translator'),
            array($this, 'translation_section_callback'),
            'wc-ai-translator-settings'
        );

        add_settings_field(
            'translation_quality',
            __('Translation Quality', 'wc-ai-translator'),
            array($this, 'quality_callback'),
            'wc-ai-translator-settings',
            'wcat_translation_section'
        );

        add_settings_field(
            'auto_publish',
            __('Auto Publish Translations', 'wc-ai-translator'),
            array($this, 'auto_publish_callback'),
            'wc-ai-translator-settings',
            'wcat_translation_section'
        );

        add_settings_field(
            'translate_seo',
            __('Translate SEO Tags', 'wc-ai-translator'),
            array($this, 'translate_seo_callback'),
            'wc-ai-translator-settings',
            'wcat_translation_section'
        );

        add_settings_field(
            'translate_images',
            __('Translate Image Alt Tags', 'wc-ai-translator'),
            array($this, 'translate_images_callback'),
            'wc-ai-translator-settings',
            'wcat_translation_section'
        );

        add_settings_field(
            'content_types',
            __('Content Types to Translate', 'wc-ai-translator'),
            array($this, 'content_types_callback'),
            'wc-ai-translator-settings',
            'wcat_translation_section'
        );

        // Processing Settings section
        add_settings_section(
            'wcat_processing_section',
            __('Processing Settings', 'wc-ai-translator'),
            array($this, 'processing_section_callback'),
            'wc-ai-translator-settings'
        );

        add_settings_field(
            'batch_size',
            __('Batch Size', 'wc-ai-translator'),
            array($this, 'batch_size_callback'),
            'wc-ai-translator-settings',
            'wcat_processing_section'
        );

        add_settings_field(
            'max_retries',
            __('Max Retries', 'wc-ai-translator'),
            array($this, 'max_retries_callback'),
            'wc-ai-translator-settings',
            'wcat_processing_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }

        if (isset($input['openai_model'])) {
            $sanitized['openai_model'] = sanitize_text_field($input['openai_model']);
        }

        if (isset($input['translation_quality'])) {
            $sanitized['translation_quality'] = sanitize_text_field($input['translation_quality']);
        }

        $sanitized['auto_publish'] = isset($input['auto_publish']) ? 1 : 0;
        $sanitized['translate_seo'] = isset($input['translate_seo']) ? 1 : 0;
        $sanitized['translate_images'] = isset($input['translate_images']) ? 1 : 0;

        if (isset($input['content_types']) && is_array($input['content_types'])) {
            $sanitized['content_types'] = array_map('sanitize_text_field', $input['content_types']);
        }

        if (isset($input['batch_size'])) {
            $sanitized['batch_size'] = absint($input['batch_size']);
        }

        if (isset($input['max_retries'])) {
            $sanitized['max_retries'] = absint($input['max_retries']);
        }

        return $sanitized;
    }

    // Callback functions
    public function api_section_callback() {
        echo '<p>' . __('Configure your OpenAI API settings.', 'wc-ai-translator') . '</p>';
    }

    public function api_key_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
        echo '<input type="password" name="wcat_settings[openai_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your OpenAI API key. You can get one from the OpenAI dashboard.', 'wc-ai-translator') . '</p>';
    }

    public function model_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o';
        $models = array(
            'gpt-4o' => 'GPT-4o (Recommended)',
            'gpt-4o-mini' => 'GPT-4o Mini (Faster, cheaper)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Budget)',
        );
        echo '<select name="wcat_settings[openai_model]">';
        foreach ($models as $model => $label) {
            echo '<option value="' . esc_attr($model) . '" ' . selected($value, $model, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function translation_section_callback() {
        echo '<p>' . __('Configure translation behavior.', 'wc-ai-translator') . '</p>';
    }

    public function quality_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['translation_quality']) ? $settings['translation_quality'] : 'high';
        echo '<select name="wcat_settings[translation_quality]">';
        echo '<option value="high" ' . selected($value, 'high', false) . '>' . __('High Quality', 'wc-ai-translator') . '</option>';
        echo '<option value="standard" ' . selected($value, 'standard', false) . '>' . __('Standard', 'wc-ai-translator') . '</option>';
        echo '</select>';
    }

    public function auto_publish_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['auto_publish']) ? $settings['auto_publish'] : 0;
        echo '<input type="checkbox" name="wcat_settings[auto_publish]" value="1" ' . checked($value, 1, false) . ' />';
        echo '<label>' . __('Automatically publish translated content', 'wc-ai-translator') . '</label>';
    }

    public function translate_seo_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['translate_seo']) ? $settings['translate_seo'] : 1;
        echo '<input type="checkbox" name="wcat_settings[translate_seo]" value="1" ' . checked($value, 1, false) . ' />';
        echo '<label>' . __('Translate SEO meta tags (Yoast, Rank Math, etc.)', 'wc-ai-translator') . '</label>';
    }

    public function translate_images_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['translate_images']) ? $settings['translate_images'] : 1;
        echo '<input type="checkbox" name="wcat_settings[translate_images]" value="1" ' . checked($value, 1, false) . ' />';
        echo '<label>' . __('Translate image alt tags', 'wc-ai-translator') . '</label>';
    }

    public function content_types_callback() {
        $settings = get_option('wcat_settings');
        $selected = isset($settings['content_types']) ? $settings['content_types'] : array('post', 'page', 'product');

        $types = array(
            'post' => __('Posts', 'wc-ai-translator'),
            'page' => __('Pages', 'wc-ai-translator'),
            'product' => __('Products', 'wc-ai-translator'),
        );

        foreach ($types as $type => $label) {
            $checked = in_array($type, $selected) ? 'checked' : '';
            echo '<label><input type="checkbox" name="wcat_settings[content_types][]" value="' . esc_attr($type) . '" ' . $checked . ' /> ' . esc_html($label) . '</label><br>';
        }
    }

    public function processing_section_callback() {
        echo '<p>' . __('Configure how translations are processed.', 'wc-ai-translator') . '</p>';
    }

    public function batch_size_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['batch_size']) ? $settings['batch_size'] : 10;
        echo '<input type="number" name="wcat_settings[batch_size]" value="' . esc_attr($value) . '" min="1" max="50" />';
        echo '<p class="description">' . __('Number of items to process per batch', 'wc-ai-translator') . '</p>';
    }

    public function max_retries_callback() {
        $settings = get_option('wcat_settings');
        $value = isset($settings['max_retries']) ? $settings['max_retries'] : 3;
        echo '<input type="number" name="wcat_settings[max_retries]" value="' . esc_attr($value) . '" min="1" max="10" />';
        echo '<p class="description">' . __('Maximum retry attempts for failed translations', 'wc-ai-translator') . '</p>';
    }
}
