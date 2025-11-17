<?php
/**
 * Polylang Integration Translator
 *
 * @package WC_AI_Translator
 */

class WCAT_Polylang_Translator {

    /**
     * Check if Polylang is active
     *
     * @return bool
     */
    public function is_polylang_active() {
        return function_exists('pll_languages_list') && function_exists('pll_get_post') && function_exists('pll_set_post_language');
    }

    /**
     * Get available languages
     *
     * @return array Array of language objects
     */
    public function get_languages() {
        if (!$this->is_polylang_active()) {
            return array();
        }

        $languages = array();
        $pll_languages = pll_languages_list(array('fields' => ''));

        foreach ($pll_languages as $lang) {
            $languages[] = array(
                'code' => $lang->slug,
                'name' => $lang->name,
                'locale' => $lang->locale,
                'flag' => $lang->flag_url,
                'is_default' => pll_default_language() === $lang->slug
            );
        }

        return $languages;
    }

    /**
     * Get default language
     *
     * @return string Language code
     */
    public function get_default_language() {
        if (!$this->is_polylang_active()) {
            return 'en';
        }

        return pll_default_language();
    }

    /**
     * Get post language
     *
     * @param int $post_id Post ID
     * @return string|false Language code or false
     */
    public function get_post_language($post_id) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        return pll_get_post_language($post_id);
    }

    /**
     * Get post translation
     *
     * @param int $post_id Post ID
     * @param string $lang Language code
     * @return int|false Translation post ID or false
     */
    public function get_post_translation($post_id, $lang) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        return pll_get_post($post_id, $lang);
    }

    /**
     * Get all post translations
     *
     * @param int $post_id Post ID
     * @return array Array of translations [lang_code => post_id]
     */
    public function get_post_translations($post_id) {
        if (!$this->is_polylang_active()) {
            return array();
        }

        return pll_get_post_translations($post_id);
    }

    /**
     * Set post language
     *
     * @param int $post_id Post ID
     * @param string $lang Language code
     * @return bool Success
     */
    public function set_post_language($post_id, $lang) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        pll_set_post_language($post_id, $lang);
        return true;
    }

    /**
     * Save post translation relationship
     *
     * @param int $post_id Post ID
     * @param array $translations Array of translations [lang_code => post_id]
     * @return bool Success
     */
    public function save_post_translation($post_id, $translations) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        pll_save_post_translations($translations);
        return true;
    }

    /**
     * Get term language
     *
     * @param int $term_id Term ID
     * @return string|false Language code or false
     */
    public function get_term_language($term_id) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        return pll_get_term_language($term_id);
    }

    /**
     * Get term translation
     *
     * @param int $term_id Term ID
     * @param string $lang Language code
     * @return int|false Translation term ID or false
     */
    public function get_term_translation($term_id, $lang) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        return pll_get_term($term_id, $lang);
    }

    /**
     * Get all term translations
     *
     * @param int $term_id Term ID
     * @return array Array of translations [lang_code => term_id]
     */
    public function get_term_translations($term_id) {
        if (!$this->is_polylang_active()) {
            return array();
        }

        return pll_get_term_translations($term_id);
    }

    /**
     * Set term language
     *
     * @param int $term_id Term ID
     * @param string $lang Language code
     * @return bool Success
     */
    public function set_term_language($term_id, $lang) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        pll_set_term_language($term_id, $lang);
        return true;
    }

    /**
     * Save term translation relationship
     *
     * @param int $term_id Term ID
     * @param array $translations Array of translations [lang_code => term_id]
     * @return bool Success
     */
    public function save_term_translation($term_id, $translations) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        pll_save_term_translations($translations);
        return true;
    }

    /**
     * Check if content type is translatable
     *
     * @param string $post_type Post type
     * @return bool
     */
    public function is_post_type_translatable($post_type) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        return pll_is_translated_post_type($post_type);
    }

    /**
     * Check if taxonomy is translatable
     *
     * @param string $taxonomy Taxonomy
     * @return bool
     */
    public function is_taxonomy_translatable($taxonomy) {
        if (!$this->is_polylang_active()) {
            return false;
        }

        return pll_is_translated_taxonomy($taxonomy);
    }

    /**
     * Get missing translations for a post
     *
     * @param int $post_id Post ID
     * @return array Array of language codes that don't have translations
     */
    public function get_missing_translations($post_id) {
        if (!$this->is_polylang_active()) {
            return array();
        }

        $all_languages = pll_languages_list();
        $translations = $this->get_post_translations($post_id);
        $missing = array();

        foreach ($all_languages as $lang) {
            if (!isset($translations[$lang]) || !$translations[$lang]) {
                $missing[] = $lang;
            }
        }

        return $missing;
    }

    /**
     * Get missing term translations
     *
     * @param int $term_id Term ID
     * @return array Array of language codes that don't have translations
     */
    public function get_missing_term_translations($term_id) {
        if (!$this->is_polylang_active()) {
            return array();
        }

        $all_languages = pll_languages_list();
        $translations = $this->get_term_translations($term_id);
        $missing = array();

        foreach ($all_languages as $lang) {
            if (!isset($translations[$lang]) || !$translations[$lang]) {
                $missing[] = $lang;
            }
        }

        return $missing;
    }

    /**
     * Duplicate post for translation
     *
     * @param int $post_id Post ID to duplicate
     * @param string $lang Target language
     * @return int|false New post ID or false on failure
     */
    public function duplicate_post_for_translation($post_id, $lang) {
        $original_post = get_post($post_id);

        if (!$original_post) {
            return false;
        }

        // Create post array for translation
        $new_post = array(
            'post_title'   => $original_post->post_title,
            'post_content' => $original_post->post_content,
            'post_excerpt' => $original_post->post_excerpt,
            'post_status'  => 'draft',
            'post_type'    => $original_post->post_type,
            'post_author'  => $original_post->post_author,
            'post_parent'  => $original_post->post_parent,
            'menu_order'   => $original_post->menu_order,
        );

        // Insert the new post
        $new_post_id = wp_insert_post($new_post);

        if (is_wp_error($new_post_id)) {
            return false;
        }

        // Set language for new post
        $this->set_post_language($new_post_id, $lang);

        // Link translations
        $translations = $this->get_post_translations($post_id);
        $translations[$lang] = $new_post_id;
        $this->save_post_translation($new_post_id, $translations);

        // Copy post meta
        $post_meta = get_post_meta($post_id);
        foreach ($post_meta as $key => $values) {
            // Skip some meta keys
            if (in_array($key, array('_edit_lock', '_edit_last'))) {
                continue;
            }
            foreach ($values as $value) {
                add_post_meta($new_post_id, $key, maybe_unserialize($value));
            }
        }

        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }

        return $new_post_id;
    }

    /**
     * Duplicate term for translation
     *
     * @param int $term_id Term ID to duplicate
     * @param string $taxonomy Taxonomy name
     * @param string $lang Target language
     * @return int|false New term ID or false on failure
     */
    public function duplicate_term_for_translation($term_id, $taxonomy, $lang) {
        $original_term = get_term($term_id, $taxonomy);

        if (!$original_term || is_wp_error($original_term)) {
            return false;
        }

        // Create term for translation
        $new_term = wp_insert_term(
            $original_term->name,
            $taxonomy,
            array(
                'description' => $original_term->description,
                'slug'        => $original_term->slug . '-' . $lang,
                'parent'      => $original_term->parent,
            )
        );

        if (is_wp_error($new_term)) {
            return false;
        }

        $new_term_id = $new_term['term_id'];

        // Set language for new term
        $this->set_term_language($new_term_id, $lang);

        // Link translations
        $translations = $this->get_term_translations($term_id);
        $translations[$lang] = $new_term_id;
        $this->save_term_translation($new_term_id, $translations);

        // Copy term meta
        $term_meta = get_term_meta($term_id);
        foreach ($term_meta as $key => $values) {
            foreach ($values as $value) {
                add_term_meta($new_term_id, $key, maybe_unserialize($value));
            }
        }

        return $new_term_id;
    }
}
