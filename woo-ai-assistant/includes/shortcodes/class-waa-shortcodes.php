<?php
/**
 * Shortcodes for displaying AI assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Shortcodes {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcodes
        add_shortcode('waa_search', array($this, 'render_search'));
        add_shortcode('waa_chat', array($this, 'render_chat'));
        add_shortcode('waa_floating', array($this, 'render_floating'));

        // Auto-add floating button if enabled
        add_action('wp_footer', array($this, 'auto_floating_button'));
    }

    /**
     * Detect current site language for multilingual sites
     * Supports: WPML, Polylang, TranslatePress, qTranslate-X
     */
    private function get_current_language() {
        $supported = get_option('waa_languages', array('ru', 'uk'));
        $default = get_option('waa_primary_language', 'ru');
        $detected = null;

        // WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            $detected = ICL_LANGUAGE_CODE;
        }
        // Polylang
        elseif (function_exists('pll_current_language')) {
            $detected = pll_current_language();
        }
        // TranslatePress
        elseif (class_exists('TRP_Translate_Press')) {
            global $TRP_LANGUAGE;
            if (!empty($TRP_LANGUAGE)) {
                $detected = substr($TRP_LANGUAGE, 0, 2); // 'uk_UA' -> 'uk'
            }
        }
        // qTranslate-X
        elseif (function_exists('qtranxf_getLanguage')) {
            $detected = qtranxf_getLanguage();
        }
        // WordPress locale fallback
        else {
            $locale = get_locale();
            // Map common locales to language codes
            $locale_map = array(
                'ru_RU' => 'ru',
                'uk' => 'uk',
                'uk_UA' => 'uk',
                'ru' => 'ru',
            );
            $detected = isset($locale_map[$locale]) ? $locale_map[$locale] : substr($locale, 0, 2);
        }

        // Return detected language if supported, otherwise default
        if ($detected && in_array($detected, $supported)) {
            return $detected;
        }

        return $default;
    }

    /**
     * Render search bar shortcode
     * [waa_search placeholder="Поиск товаров..." language="ru"]
     */
    public function render_search($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Умный поиск товаров...', 'woo-ai-assistant'),
            'language' => $this->get_current_language(),
            'class' => '',
        ), $atts);

        ob_start();
        ?>
        <div class="waa-search-wrapper <?php echo esc_attr($atts['class']); ?>" data-language="<?php echo esc_attr($atts['language']); ?>">
            <form class="waa-search-form" onsubmit="return false;">
                <input type="text"
                       class="waa-search-input"
                       placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                       autocomplete="off">
                <button type="submit" class="waa-search-button">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                </button>
            </form>
            <div class="waa-search-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render chat widget shortcode
     * [waa_chat title="AI Консультант" language="ru" height="400"]
     */
    public function render_chat($atts) {
        $atts = shortcode_atts(array(
            'title' => __('AI Консультант', 'woo-ai-assistant'),
            'language' => $this->get_current_language(),
            'height' => '400',
            'class' => '',
        ), $atts);

        $session_id = wp_generate_uuid4();
        $assistant = WAA_Assistant::get_instance();
        $welcome = $assistant->get_welcome_message($atts['language']);

        ob_start();
        ?>
        <div class="waa-chat-widget <?php echo esc_attr($atts['class']); ?>"
             data-language="<?php echo esc_attr($atts['language']); ?>"
             data-session="<?php echo esc_attr($session_id); ?>"
             style="height: <?php echo esc_attr($atts['height']); ?>px;">

            <div class="waa-chat-header">
                <span class="waa-chat-title"><?php echo esc_html($atts['title']); ?></span>
                <span class="waa-chat-status"></span>
            </div>

            <div class="waa-chat-messages">
                <div class="waa-message waa-message-assistant">
                    <div class="waa-message-content"><?php echo esc_html($welcome); ?></div>
                </div>
            </div>

            <div class="waa-chat-input-wrapper">
                <textarea class="waa-chat-input"
                          placeholder="<?php esc_attr_e('Задайте вопрос о товарах...', 'woo-ai-assistant'); ?>"
                          rows="1"></textarea>
                <button class="waa-chat-voice" title="<?php esc_attr_e('Голосовой ввод', 'woo-ai-assistant'); ?>">
                    <svg class="waa-mic-icon" viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/>
                    </svg>
                    <svg class="waa-mic-recording" viewBox="0 0 24 24" width="20" height="20" style="display:none;">
                        <circle cx="12" cy="12" r="8" fill="#ff0000">
                            <animate attributeName="opacity" values="1;0.5;1" dur="1s" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                </button>
                <button class="waa-chat-send" disabled>
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render floating button shortcode
     * [waa_floating position="bottom-right" language="ru"]
     */
    public function render_floating($atts) {
        $atts = shortcode_atts(array(
            'position' => get_option('waa_floating_button_position', 'bottom-right'),
            'language' => $this->get_current_language(),
            'title' => __('AI Консультант', 'woo-ai-assistant'),
        ), $atts);

        $session_id = wp_generate_uuid4();
        $assistant = WAA_Assistant::get_instance();
        $welcome = $assistant->get_welcome_message($atts['language']);

        ob_start();
        ?>
        <div class="waa-floating-wrapper waa-position-<?php echo esc_attr($atts['position']); ?>"
             data-language="<?php echo esc_attr($atts['language']); ?>"
             data-session="<?php echo esc_attr($session_id); ?>">

            <!-- Floating Button -->
            <button class="waa-floating-button" aria-label="<?php esc_attr_e('Открыть чат', 'woo-ai-assistant'); ?>">
                <svg class="waa-icon-chat" viewBox="0 0 24 24" width="24" height="24">
                    <path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
                <svg class="waa-icon-close" viewBox="0 0 24 24" width="24" height="24" style="display:none;">
                    <path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>

            <!-- Chat Window -->
            <div class="waa-floating-chat" style="display: none;">
                <div class="waa-chat-header">
                    <span class="waa-chat-title"><?php echo esc_html($atts['title']); ?></span>
                    <button class="waa-chat-close" aria-label="<?php esc_attr_e('Закрыть', 'woo-ai-assistant'); ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>

                <div class="waa-chat-messages">
                    <div class="waa-message waa-message-assistant">
                        <div class="waa-message-content"><?php echo esc_html($welcome); ?></div>
                    </div>
                </div>

                <div class="waa-chat-input-wrapper">
                    <textarea class="waa-chat-input"
                              placeholder="<?php esc_attr_e('Задайте вопрос о товарах...', 'woo-ai-assistant'); ?>"
                              rows="1"></textarea>
                    <button class="waa-chat-voice" title="<?php esc_attr_e('Голосовой ввод', 'woo-ai-assistant'); ?>">
                        <svg class="waa-mic-icon" viewBox="0 0 24 24" width="20" height="20">
                            <path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/>
                        </svg>
                        <svg class="waa-mic-recording" viewBox="0 0 24 24" width="20" height="20" style="display:none;">
                            <circle cx="12" cy="12" r="8" fill="#ff0000">
                                <animate attributeName="opacity" values="1;0.5;1" dur="1s" repeatCount="indefinite"/>
                            </circle>
                        </svg>
                    </button>
                    <button class="waa-chat-send" disabled>
                        <svg viewBox="0 0 24 24" width="20" height="20">
                            <path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Auto-add floating button based on settings
     */
    public function auto_floating_button() {
        if (!get_option('waa_auto_floating', false)) {
            return;
        }

        echo do_shortcode('[waa_floating]');
    }
}
