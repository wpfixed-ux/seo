<?php
/**
 * Content Scanner
 *
 * @package WC_AI_Translator
 */

class WCAT_Content_Scanner {

    /**
     * Polylang instance
     */
    private $polylang;

    /**
     * Constructor
     */
    public function __construct() {
        $this->polylang = new WCAT_Polylang_Translator();
    }

    /**
     * Scan all translatable content
     *
     * @param array $args Scan arguments
     * @return array Scan results
     */
    public function scan_content($args = array()) {
        $defaults = array(
            'content_types' => array('post', 'page', 'product'),
            'taxonomies' => array('category', 'post_tag', 'product_cat', 'product_tag'),
            'include_drafts' => false,
            'source_lang' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $results = array(
            'posts' => array(),
            'terms' => array(),
            'summary' => array(),
        );

        // Get source language
        $source_lang = $args['source_lang'] ? $args['source_lang'] : $this->polylang->get_default_language();

        // Scan posts
        foreach ($args['content_types'] as $post_type) {
            if (!$this->polylang->is_post_type_translatable($post_type)) {
                continue;
            }

            $posts = $this->scan_post_type($post_type, $source_lang, $args['include_drafts']);
            $results['posts'][$post_type] = $posts;
            $results['summary'][$post_type] = count($posts);
        }

        // Scan taxonomies
        foreach ($args['taxonomies'] as $taxonomy) {
            if (!$this->polylang->is_taxonomy_translatable($taxonomy)) {
                continue;
            }

            $terms = $this->scan_taxonomy($taxonomy, $source_lang);
            $results['terms'][$taxonomy] = $terms;
            $results['summary'][$taxonomy] = count($terms);
        }

        return $results;
    }

    /**
     * Scan post type
     *
     * @param string $post_type Post type
     * @param string $source_lang Source language
     * @param bool $include_drafts Include drafts
     * @return array Array of posts with translation status
     */
    private function scan_post_type($post_type, $source_lang, $include_drafts = false) {
        $post_status = $include_drafts ? array('publish', 'draft') : array('publish');

        $query_args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => $post_status,
            'lang' => $source_lang,
        );

        $posts = get_posts($query_args);
        $results = array();

        foreach ($posts as $post) {
            $missing_translations = $this->polylang->get_missing_translations($post->ID);

            if (!empty($missing_translations)) {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post_type,
                    'status' => $post->post_status,
                    'language' => $source_lang,
                    'missing_languages' => $missing_translations,
                    'word_count' => str_word_count(strip_tags($post->post_content)),
                );
            }
        }

        return $results;
    }

    /**
     * Scan taxonomy
     *
     * @param string $taxonomy Taxonomy name
     * @param string $source_lang Source language
     * @return array Array of terms with translation status
     */
    private function scan_taxonomy($taxonomy, $source_lang) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'lang' => $source_lang,
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        $results = array();

        foreach ($terms as $term) {
            $missing_translations = $this->polylang->get_missing_term_translations($term->term_id);

            if (!empty($missing_translations)) {
                $results[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'taxonomy' => $taxonomy,
                    'language' => $source_lang,
                    'missing_languages' => $missing_translations,
                    'count' => $term->count,
                );
            }
        }

        return $results;
    }

    /**
     * Get translation statistics
     *
     * @return array Statistics
     */
    public function get_translation_statistics() {
        $settings = get_option('wcat_settings');
        $content_types = isset($settings['content_types']) ? $settings['content_types'] : array('post', 'page', 'product');
        $taxonomies = isset($settings['taxonomies']) ? $settings['taxonomies'] : array('category', 'post_tag', 'product_cat', 'product_tag');

        $languages = $this->polylang->get_languages();
        $default_lang = $this->polylang->get_default_language();

        $stats = array(
            'languages' => $languages,
            'default_language' => $default_lang,
            'content_types' => array(),
            'total_translatable' => 0,
            'total_missing' => 0,
        );

        // Count posts
        foreach ($content_types as $post_type) {
            if (!$this->polylang->is_post_type_translatable($post_type)) {
                continue;
            }

            $total = wp_count_posts($post_type);
            $total_count = isset($total->publish) ? $total->publish : 0;

            $posts = $this->scan_post_type($post_type, $default_lang, false);
            $missing_count = count($posts);

            $stats['content_types'][$post_type] = array(
                'total' => $total_count,
                'missing' => $missing_count,
                'translated' => $total_count - $missing_count,
            );

            $stats['total_translatable'] += $total_count;
            $stats['total_missing'] += $missing_count;
        }

        // Count terms
        foreach ($taxonomies as $taxonomy) {
            if (!$this->polylang->is_taxonomy_translatable($taxonomy)) {
                continue;
            }

            $terms_count = wp_count_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
            $terms = $this->scan_taxonomy($taxonomy, $default_lang);
            $missing_count = count($terms);

            $stats['content_types'][$taxonomy] = array(
                'total' => $terms_count,
                'missing' => $missing_count,
                'translated' => $terms_count - $missing_count,
            );

            $stats['total_translatable'] += $terms_count;
            $stats['total_missing'] += $missing_count;
        }

        return $stats;
    }

    /**
     * Estimate translation cost
     *
     * @param array $content_items Array of content items to translate
     * @return array Cost estimation
     */
    public function estimate_translation_cost($content_items) {
        $total_words = 0;
        $settings = get_option('wcat_settings');
        $model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o';

        // Estimate tokens (1 word ≈ 1.3 tokens on average)
        $token_multiplier = 1.3;

        foreach ($content_items as $item) {
            if ($item['type'] === 'post' || $item['type'] === 'page' || $item['type'] === 'product') {
                $post = get_post($item['id']);
                if ($post) {
                    $words = str_word_count(strip_tags($post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt));
                    $total_words += $words;
                }
            } else {
                // Taxonomy terms
                $term = get_term($item['id']);
                if ($term && !is_wp_error($term)) {
                    $words = str_word_count(strip_tags($term->name . ' ' . $term->description));
                    $total_words += $words;
                }
            }
        }

        $estimated_tokens = $total_words * $token_multiplier;

        // Calculate number of target languages
        $target_languages = 1;
        if (!empty($content_items[0]['missing_languages'])) {
            $target_languages = count($content_items[0]['missing_languages']);
        }

        $total_tokens = $estimated_tokens * $target_languages * 2; // *2 for input + output

        // Pricing per model (per 1M tokens)
        $pricing = array(
            'gpt-4o' => 6.25, // Average of input and output
            'gpt-4o-mini' => 0.375,
            'gpt-4-turbo' => 20.00,
            'gpt-3.5-turbo' => 1.00,
        );

        $price_per_million = isset($pricing[$model]) ? $pricing[$model] : 6.25;
        $estimated_cost = ($total_tokens / 1000000) * $price_per_million;

        return array(
            'total_words' => $total_words,
            'estimated_tokens' => (int) $total_tokens,
            'target_languages' => $target_languages,
            'estimated_cost' => round($estimated_cost, 2),
            'model' => $model,
        );
    }
}
