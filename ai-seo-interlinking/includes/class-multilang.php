<?php
/**
 * Multilang Support Class
 * Handles multilingual support via Polylang/WPML
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Multilang_Support {

    /**
     * Detected multilang plugin
     */
    private static $plugin = null;

    /**
     * Initialize multilang support
     */
    public static function init() {
        self::detect_plugin();
    }

    /**
     * Detect active multilang plugin
     *
     * @return string|null Plugin name or null
     */
    public static function detect_plugin() {
        if (self::$plugin !== null) {
            return self::$plugin;
        }

        // Check for Polylang
        if (function_exists('pll_languages_list')) {
            self::$plugin = 'polylang';
            return 'polylang';
        }

        // Check for WPML
        if (class_exists('SitePress')) {
            self::$plugin = 'wpml';
            return 'wpml';
        }

        self::$plugin = 'none';
        return 'none';
    }

    /**
     * Get active languages
     *
     * @return array Array of language codes
     */
    public static function get_languages() {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'polylang':
                return self::get_polylang_languages();

            case 'wpml':
                return self::get_wpml_languages();

            default:
                return ['default'];
        }
    }

    /**
     * Get Polylang languages
     *
     * @return array Language codes
     */
    private static function get_polylang_languages() {
        if (!function_exists('pll_languages_list')) {
            return ['default'];
        }

        $languages = pll_languages_list(['fields' => 'slug']);

        return !empty($languages) ? $languages : ['default'];
    }

    /**
     * Get WPML languages
     *
     * @return array Language codes
     */
    private static function get_wpml_languages() {
        if (!class_exists('SitePress')) {
            return ['default'];
        }

        global $sitepress;

        $languages = $sitepress->get_active_languages();
        $language_codes = [];

        foreach ($languages as $language) {
            $language_codes[] = $language['code'];
        }

        return !empty($language_codes) ? $language_codes : ['default'];
    }

    /**
     * Get post language
     *
     * @param int $post_id Post ID
     * @return string      Language code
     */
    public static function get_post_language($post_id) {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'polylang':
                return self::get_polylang_post_language($post_id);

            case 'wpml':
                return self::get_wpml_post_language($post_id);

            default:
                return 'default';
        }
    }

    /**
     * Get Polylang post language
     *
     * @param int $post_id Post ID
     * @return string      Language code
     */
    private static function get_polylang_post_language($post_id) {
        if (!function_exists('pll_get_post_language')) {
            return 'default';
        }

        $language = pll_get_post_language($post_id, 'slug');

        return $language ? $language : 'default';
    }

    /**
     * Get WPML post language
     *
     * @param int $post_id Post ID
     * @return string      Language code
     */
    private static function get_wpml_post_language($post_id) {
        if (!function_exists('wpml_get_language_information')) {
            return 'default';
        }

        $lang_info = wpml_get_language_information($post_id);

        return isset($lang_info['language_code']) ? $lang_info['language_code'] : 'default';
    }

    /**
     * Get translations of a post
     *
     * @param int $post_id Post ID
     * @return array       Array of post IDs keyed by language code
     */
    public static function get_post_translations($post_id) {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'polylang':
                return self::get_polylang_translations($post_id);

            case 'wpml':
                return self::get_wpml_translations($post_id);

            default:
                return [];
        }
    }

    /**
     * Get Polylang translations
     *
     * @param int $post_id Post ID
     * @return array       Translations
     */
    private static function get_polylang_translations($post_id) {
        if (!function_exists('pll_get_post_translations')) {
            return [];
        }

        return pll_get_post_translations($post_id);
    }

    /**
     * Get WPML translations
     *
     * @param int $post_id Post ID
     * @return array       Translations
     */
    private static function get_wpml_translations($post_id) {
        if (!function_exists('wpml_get_language_information')) {
            return [];
        }

        global $sitepress;

        $post_type = get_post_type($post_id);
        $trid = $sitepress->get_element_trid($post_id, 'post_' . $post_type);

        $translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);
        $translation_ids = [];

        foreach ($translations as $lang => $translation) {
            $translation_ids[$lang] = $translation->element_id;
        }

        return $translation_ids;
    }

    /**
     * Add language arguments to WP_Query args
     *
     * @param array  $args     Query arguments
     * @param string $language Language code
     * @return array           Modified arguments
     */
    public static function add_language_args($args, $language) {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'polylang':
                $args['lang'] = $language;
                break;

            case 'wpml':
                global $sitepress;
                $sitepress->switch_lang($language);
                break;
        }

        return $args;
    }

    /**
     * Get default language
     *
     * @return string Default language code
     */
    public static function get_default_language() {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'polylang':
                if (function_exists('pll_default_language')) {
                    return pll_default_language('slug');
                }
                break;

            case 'wpml':
                if (class_exists('SitePress')) {
                    global $sitepress;
                    return $sitepress->get_default_language();
                }
                break;
        }

        return 'default';
    }

    /**
     * Check if multilang is enabled
     *
     * @return bool True if multilang plugin is active
     */
    public static function is_multilang_enabled() {
        $plugin = self::detect_plugin();
        return $plugin !== 'none';
    }

    /**
     * Get language name
     *
     * @param string $language_code Language code
     * @return string               Language name
     */
    public static function get_language_name($language_code) {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'polylang':
                if (function_exists('pll_languages_list')) {
                    $languages = pll_languages_list(['fields' => '']);
                    foreach ($languages as $language) {
                        if ($language->slug === $language_code) {
                            return $language->name;
                        }
                    }
                }
                break;

            case 'wpml':
                if (class_exists('SitePress')) {
                    global $sitepress;
                    $languages = $sitepress->get_active_languages();
                    if (isset($languages[$language_code])) {
                        return $languages[$language_code]['native_name'];
                    }
                }
                break;
        }

        return $language_code;
    }

    /**
     * Translate text using AI
     *
     * @param string $text         Text to translate
     * @param string $from_lang    Source language
     * @param string $to_lang      Target language
     * @return string|WP_Error     Translated text or error
     */
    public static function translate_text($text, $from_lang, $to_lang) {
        $ai = AIL_AI_Processor::get_instance();

        $prompt = sprintf(
            'Translate the following text from %s to %s. Return only the translation, no explanations:

%s',
            $from_lang,
            $to_lang,
            $text
        );

        $response = $ai->make_api_request('translate_text', $prompt, 200);

        if (isset($response['error'])) {
            return new WP_Error('translation_error', $response['error']);
        }

        return trim($response['content']);
    }

    /**
     * Get language statistics
     *
     * @return array Statistics per language
     */
    public static function get_language_statistics() {
        global $wpdb;

        $languages = self::get_languages();
        $stats = [];

        foreach ($languages as $lang) {
            $links_table = $wpdb->prefix . 'ai_interlinking_links';
            $keywords_table = $wpdb->prefix . 'ai_interlinking_keywords';

            $links_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$links_table} WHERE language = %s",
                $lang
            ));

            $keywords_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$keywords_table} WHERE language = %s",
                $lang
            ));

            $stats[$lang] = [
                'language' => $lang,
                'language_name' => self::get_language_name($lang),
                'links_count' => intval($links_count),
                'keywords_count' => intval($keywords_count),
            ];
        }

        return $stats;
    }
}

// Initialize
AIL_Multilang_Support::init();
