<?php
/**
 * SEO Translator
 *
 * @package WC_AI_Translator
 */

class WCAT_SEO_Translator {

    /**
     * OpenAI instance
     */
    private $openai;

    /**
     * Polylang instance
     */
    private $polylang;

    /**
     * Translation log
     */
    private $log;

    /**
     * Constructor
     */
    public function __construct() {
        $this->openai = new WCAT_OpenAI();
        $this->polylang = new WCAT_Polylang_Translator();
        $this->log = new WCAT_Translation_Log();
    }

    /**
     * Translate SEO meta for a post
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $target_lang Target language
     * @return array Translation result
     */
    public function translate_seo_meta($source_id, $target_id, $target_lang) {
        $source_lang = $this->polylang->get_post_language($source_id);
        if (!$source_lang) {
            $source_lang = $this->polylang->get_default_language();
        }

        $total_tokens = 0;
        $total_cost = 0;

        // Detect active SEO plugin
        $seo_plugin = $this->detect_seo_plugin();

        if ($seo_plugin === 'yoast') {
            $result = $this->translate_yoast_seo($source_id, $target_id, $source_lang, $target_lang);
        } elseif ($seo_plugin === 'rankmath') {
            $result = $this->translate_rankmath_seo($source_id, $target_id, $source_lang, $target_lang);
        } elseif ($seo_plugin === 'aioseo') {
            $result = $this->translate_aioseo($source_id, $target_id, $source_lang, $target_lang);
        } else {
            // Generic meta translation
            $result = $this->translate_generic_meta($source_id, $target_id, $source_lang, $target_lang);
        }

        if ($result['success']) {
            $total_tokens += $result['tokens_used'];
            $total_cost += $result['cost'];
        }

        // Translate image alt texts
        $image_result = $this->translate_post_images_alt($source_id, $target_id, $source_lang, $target_lang);
        if ($image_result['success']) {
            $total_tokens += $image_result['tokens_used'];
            $total_cost += $image_result['cost'];
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost,
            'seo_plugin' => $seo_plugin
        );
    }

    /**
     * Detect active SEO plugin
     *
     * @return string|false Plugin name or false
     */
    private function detect_seo_plugin() {
        if (defined('WPSEO_VERSION')) {
            return 'yoast';
        } elseif (class_exists('RankMath')) {
            return 'rankmath';
        } elseif (function_exists('aioseo')) {
            return 'aioseo';
        }
        return false;
    }

    /**
     * Translate Yoast SEO meta
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_yoast_seo($source_id, $target_id, $source_lang, $target_lang) {
        $total_tokens = 0;
        $total_cost = 0;

        // SEO title
        $seo_title = get_post_meta($source_id, '_yoast_wpseo_title', true);
        if (!empty($seo_title)) {
            $result = $this->openai->translate($seo_title, $source_lang, $target_lang, array(
                'content_type' => 'SEO title'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_title', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Meta description
        $meta_desc = get_post_meta($source_id, '_yoast_wpseo_metadesc', true);
        if (!empty($meta_desc)) {
            $result = $this->openai->translate($meta_desc, $source_lang, $target_lang, array(
                'content_type' => 'Meta description'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_metadesc', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Focus keyphrases (keep as is, or optionally translate)
        $focuskw = get_post_meta($source_id, '_yoast_wpseo_focuskw', true);
        if (!empty($focuskw)) {
            $result = $this->openai->translate($focuskw, $source_lang, $target_lang, array(
                'content_type' => 'Focus keyphrase'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_focuskw', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // OpenGraph title
        $og_title = get_post_meta($source_id, '_yoast_wpseo_opengraph-title', true);
        if (!empty($og_title)) {
            $result = $this->openai->translate($og_title, $source_lang, $target_lang, array(
                'content_type' => 'OpenGraph title'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_opengraph-title', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // OpenGraph description
        $og_desc = get_post_meta($source_id, '_yoast_wpseo_opengraph-description', true);
        if (!empty($og_desc)) {
            $result = $this->openai->translate($og_desc, $source_lang, $target_lang, array(
                'content_type' => 'OpenGraph description'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_opengraph-description', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Twitter title
        $twitter_title = get_post_meta($source_id, '_yoast_wpseo_twitter-title', true);
        if (!empty($twitter_title)) {
            $result = $this->openai->translate($twitter_title, $source_lang, $target_lang, array(
                'content_type' => 'Twitter title'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_twitter-title', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Twitter description
        $twitter_desc = get_post_meta($source_id, '_yoast_wpseo_twitter-description', true);
        if (!empty($twitter_desc)) {
            $result = $this->openai->translate($twitter_desc, $source_lang, $target_lang, array(
                'content_type' => 'Twitter description'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_yoast_wpseo_twitter-description', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }

    /**
     * Translate Rank Math SEO meta
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_rankmath_seo($source_id, $target_id, $source_lang, $target_lang) {
        $total_tokens = 0;
        $total_cost = 0;

        // SEO title
        $seo_title = get_post_meta($source_id, 'rank_math_title', true);
        if (!empty($seo_title)) {
            $result = $this->openai->translate($seo_title, $source_lang, $target_lang, array(
                'content_type' => 'SEO title'
            ));

            if ($result['success']) {
                update_post_meta($target_id, 'rank_math_title', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Meta description
        $meta_desc = get_post_meta($source_id, 'rank_math_description', true);
        if (!empty($meta_desc)) {
            $result = $this->openai->translate($meta_desc, $source_lang, $target_lang, array(
                'content_type' => 'Meta description'
            ));

            if ($result['success']) {
                update_post_meta($target_id, 'rank_math_description', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Focus keyword
        $focus_keyword = get_post_meta($source_id, 'rank_math_focus_keyword', true);
        if (!empty($focus_keyword)) {
            $result = $this->openai->translate($focus_keyword, $source_lang, $target_lang, array(
                'content_type' => 'Focus keyword'
            ));

            if ($result['success']) {
                update_post_meta($target_id, 'rank_math_focus_keyword', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }

    /**
     * Translate All in One SEO meta
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_aioseo($source_id, $target_id, $source_lang, $target_lang) {
        $total_tokens = 0;
        $total_cost = 0;

        // SEO title
        $seo_title = get_post_meta($source_id, '_aioseo_title', true);
        if (!empty($seo_title)) {
            $result = $this->openai->translate($seo_title, $source_lang, $target_lang, array(
                'content_type' => 'SEO title'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_aioseo_title', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Meta description
        $meta_desc = get_post_meta($source_id, '_aioseo_description', true);
        if (!empty($meta_desc)) {
            $result = $this->openai->translate($meta_desc, $source_lang, $target_lang, array(
                'content_type' => 'Meta description'
            ));

            if ($result['success']) {
                update_post_meta($target_id, '_aioseo_description', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }

    /**
     * Translate generic meta tags
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_generic_meta($source_id, $target_id, $source_lang, $target_lang) {
        // Translate basic WordPress excerpt if not already done
        $excerpt = get_post_field('post_excerpt', $source_id);

        if (!empty($excerpt)) {
            $result = $this->openai->translate($excerpt, $source_lang, $target_lang, array(
                'content_type' => 'Excerpt'
            ));

            if ($result['success']) {
                wp_update_post(array(
                    'ID' => $target_id,
                    'post_excerpt' => $result['translated_text']
                ));

                return array(
                    'success' => true,
                    'tokens_used' => $result['tokens_used'],
                    'cost' => $result['cost']
                );
            }
        }

        return array(
            'success' => true,
            'tokens_used' => 0,
            'cost' => 0
        );
    }

    /**
     * Translate image alt texts in post content
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_post_images_alt($source_id, $target_id, $source_lang, $target_lang) {
        $total_tokens = 0;
        $total_cost = 0;

        // Get all images from post content
        $content = get_post_field('post_content', $target_id);
        preg_match_all('/<img[^>]+>/i', $content, $images);

        if (empty($images[0])) {
            return array('success' => true, 'tokens_used' => 0, 'cost' => 0);
        }

        foreach ($images[0] as $img_tag) {
            // Extract image ID from class or data attribute
            if (preg_match('/wp-image-(\d+)/i', $img_tag, $match)) {
                $image_id = $match[1];
                $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);

                if (!empty($alt_text)) {
                    $result = $this->openai->translate($alt_text, $source_lang, $target_lang, array(
                        'content_type' => 'Image alt text'
                    ));

                    if ($result['success']) {
                        // Store translated alt text for this language
                        update_post_meta($image_id, '_wp_attachment_image_alt_' . $target_lang, $result['translated_text']);
                        $total_tokens += $result['tokens_used'];
                        $total_cost += $result['cost'];

                        // Update the image in content
                        $new_img_tag = preg_replace('/alt="[^"]*"/i', 'alt="' . esc_attr($result['translated_text']) . '"', $img_tag);
                        $content = str_replace($img_tag, $new_img_tag, $content);
                    }
                }
            }
        }

        // Update post content with translated alt texts
        wp_update_post(array(
            'ID' => $target_id,
            'post_content' => $content
        ));

        // Translate featured image alt text
        $thumbnail_id = get_post_thumbnail_id($source_id);
        if ($thumbnail_id) {
            $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);

            if (!empty($alt_text)) {
                $result = $this->openai->translate($alt_text, $source_lang, $target_lang, array(
                    'content_type' => 'Featured image alt text'
                ));

                if ($result['success']) {
                    update_post_meta($thumbnail_id, '_wp_attachment_image_alt_' . $target_lang, $result['translated_text']);
                    $total_tokens += $result['tokens_used'];
                    $total_cost += $result['cost'];
                }
            }
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }

    /**
     * Translate term SEO meta
     *
     * @param int $source_term_id Source term ID
     * @param int $target_term_id Target term ID
     * @param string $taxonomy Taxonomy name
     * @param string $target_lang Target language
     * @return array Translation result
     */
    public function translate_term_seo_meta($source_term_id, $target_term_id, $taxonomy, $target_lang) {
        $source_lang = $this->polylang->get_term_language($source_term_id);
        if (!$source_lang) {
            $source_lang = $this->polylang->get_default_language();
        }

        $seo_plugin = $this->detect_seo_plugin();

        if ($seo_plugin === 'yoast') {
            return $this->translate_term_yoast_seo($source_term_id, $target_term_id, $taxonomy, $source_lang, $target_lang);
        } elseif ($seo_plugin === 'rankmath') {
            return $this->translate_term_rankmath_seo($source_term_id, $target_term_id, $taxonomy, $source_lang, $target_lang);
        }

        return array('success' => true, 'tokens_used' => 0, 'cost' => 0);
    }

    /**
     * Translate term Yoast SEO meta
     *
     * @param int $source_term_id Source term ID
     * @param int $target_term_id Target term ID
     * @param string $taxonomy Taxonomy name
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_term_yoast_seo($source_term_id, $target_term_id, $taxonomy, $source_lang, $target_lang) {
        $total_tokens = 0;
        $total_cost = 0;

        $wpseo_taxonomy_meta = get_option('wpseo_taxonomy_meta');

        if (isset($wpseo_taxonomy_meta[$taxonomy][$source_term_id])) {
            $source_meta = $wpseo_taxonomy_meta[$taxonomy][$source_term_id];

            if (!isset($wpseo_taxonomy_meta[$taxonomy][$target_term_id])) {
                $wpseo_taxonomy_meta[$taxonomy][$target_term_id] = array();
            }

            // Translate title
            if (!empty($source_meta['wpseo_title'])) {
                $result = $this->openai->translate($source_meta['wpseo_title'], $source_lang, $target_lang, array(
                    'content_type' => 'Term SEO title'
                ));

                if ($result['success']) {
                    $wpseo_taxonomy_meta[$taxonomy][$target_term_id]['wpseo_title'] = $result['translated_text'];
                    $total_tokens += $result['tokens_used'];
                    $total_cost += $result['cost'];
                }
            }

            // Translate description
            if (!empty($source_meta['wpseo_desc'])) {
                $result = $this->openai->translate($source_meta['wpseo_desc'], $source_lang, $target_lang, array(
                    'content_type' => 'Term meta description'
                ));

                if ($result['success']) {
                    $wpseo_taxonomy_meta[$taxonomy][$target_term_id]['wpseo_desc'] = $result['translated_text'];
                    $total_tokens += $result['tokens_used'];
                    $total_cost += $result['cost'];
                }
            }

            update_option('wpseo_taxonomy_meta', $wpseo_taxonomy_meta);
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }

    /**
     * Translate term Rank Math SEO meta
     *
     * @param int $source_term_id Source term ID
     * @param int $target_term_id Target term ID
     * @param string $taxonomy Taxonomy name
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_term_rankmath_seo($source_term_id, $target_term_id, $taxonomy, $source_lang, $target_lang) {
        $total_tokens = 0;
        $total_cost = 0;

        // Rank Math stores term meta differently
        $title = get_term_meta($source_term_id, 'rank_math_title', true);
        if (!empty($title)) {
            $result = $this->openai->translate($title, $source_lang, $target_lang, array(
                'content_type' => 'Term SEO title'
            ));

            if ($result['success']) {
                update_term_meta($target_term_id, 'rank_math_title', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        $description = get_term_meta($source_term_id, 'rank_math_description', true);
        if (!empty($description)) {
            $result = $this->openai->translate($description, $source_lang, $target_lang, array(
                'content_type' => 'Term meta description'
            ));

            if ($result['success']) {
                update_term_meta($target_term_id, 'rank_math_description', $result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }
}
