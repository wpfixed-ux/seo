<?php
/**
 * Content Translator
 *
 * @package WC_AI_Translator
 */

class WCAT_Content_Translator {

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
     * Translate a post
     *
     * @param int $post_id Post ID
     * @param string $target_lang Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_post($post_id, $target_lang, $options = array()) {
        $post = get_post($post_id);

        if (!$post) {
            return array(
                'success' => false,
                'error' => __('Post not found.', 'wc-ai-translator')
            );
        }

        // Get source language
        $source_lang = $this->polylang->get_post_language($post_id);
        if (!$source_lang) {
            $source_lang = $this->polylang->get_default_language();
        }

        // Check if translation already exists
        $existing_translation = $this->polylang->get_post_translation($post_id, $target_lang);

        if ($existing_translation && !isset($options['overwrite'])) {
            return array(
                'success' => false,
                'error' => __('Translation already exists. Use overwrite option to replace it.', 'wc-ai-translator'),
                'existing_id' => $existing_translation
            );
        }

        // Prepare content for translation
        $content_to_translate = $this->prepare_content_for_translation($post);

        // Translate all fields
        $translated_content = array();
        $total_tokens = 0;
        $total_cost = 0;

        foreach ($content_to_translate as $field => $text) {
            if (empty($text)) {
                $translated_content[$field] = '';
                continue;
            }

            $translation_options = array(
                'content_type' => 'Post ' . $field,
                'quality' => isset($options['quality']) ? $options['quality'] : 'high'
            );

            $result = $this->openai->translate($text, $source_lang, $target_lang, $translation_options);

            if (!$result['success']) {
                $this->log->add_log(array(
                    'content_id' => $post_id,
                    'content_type' => $post->post_type,
                    'source_lang' => $source_lang,
                    'target_lang' => $target_lang,
                    'action' => 'translate_post',
                    'status' => 'failed',
                    'message' => 'Failed to translate ' . $field . ': ' . $result['error']
                ));

                return array(
                    'success' => false,
                    'error' => sprintf(__('Failed to translate %s: %s', 'wc-ai-translator'), $field, $result['error'])
                );
            }

            $translated_content[$field] = $result['translated_text'];
            $total_tokens += $result['tokens_used'];
            $total_cost += $result['cost'];
        }

        // Create or update translation
        if ($existing_translation) {
            $new_post_id = $this->update_translated_post($existing_translation, $translated_content, $post);
        } else {
            $new_post_id = $this->create_translated_post($post_id, $target_lang, $translated_content, $post);
        }

        if (!$new_post_id) {
            return array(
                'success' => false,
                'error' => __('Failed to create/update translated post.', 'wc-ai-translator')
            );
        }

        // Copy taxonomies
        $this->copy_taxonomies($post_id, $new_post_id, $target_lang);

        // Log success
        $this->log->add_log(array(
            'content_id' => $post_id,
            'content_type' => $post->post_type,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'action' => 'translate_post',
            'status' => 'success',
            'message' => sprintf('Post translated successfully. New post ID: %d', $new_post_id),
            'tokens_used' => $total_tokens,
            'cost' => $total_cost,
            'user_id' => get_current_user_id()
        ));

        return array(
            'success' => true,
            'post_id' => $new_post_id,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost,
            'message' => __('Post translated successfully.', 'wc-ai-translator')
        );
    }

    /**
     * Prepare content for translation
     *
     * @param WP_Post $post Post object
     * @return array Array of content to translate
     */
    private function prepare_content_for_translation($post) {
        $content = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        );

        return apply_filters('wcat_content_to_translate', $content, $post);
    }

    /**
     * Create translated post
     *
     * @param int $original_id Original post ID
     * @param string $lang Target language
     * @param array $translated_content Translated content
     * @param WP_Post $original_post Original post object
     * @return int|false New post ID or false
     */
    private function create_translated_post($original_id, $lang, $translated_content, $original_post) {
        $settings = get_option('wcat_settings');
        $auto_publish = isset($settings['auto_publish']) ? $settings['auto_publish'] : false;

        // Create new post
        $new_post_id = $this->polylang->duplicate_post_for_translation($original_id, $lang);

        if (!$new_post_id) {
            return false;
        }

        // Update with translated content
        $update_data = array(
            'ID' => $new_post_id,
            'post_title' => $translated_content['title'],
            'post_content' => $translated_content['content'],
            'post_excerpt' => $translated_content['excerpt'],
            'post_status' => $auto_publish ? 'publish' : 'draft',
        );

        wp_update_post($update_data);

        return $new_post_id;
    }

    /**
     * Update existing translated post
     *
     * @param int $post_id Post ID to update
     * @param array $translated_content Translated content
     * @param WP_Post $original_post Original post object
     * @return int Post ID
     */
    private function update_translated_post($post_id, $translated_content, $original_post) {
        $update_data = array(
            'ID' => $post_id,
            'post_title' => $translated_content['title'],
            'post_content' => $translated_content['content'],
            'post_excerpt' => $translated_content['excerpt'],
        );

        wp_update_post($update_data);

        return $post_id;
    }

    /**
     * Copy taxonomies to translated post
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @param string $lang Target language
     */
    private function copy_taxonomies($source_id, $target_id, $lang) {
        $post_type = get_post_type($source_id);
        $taxonomies = get_object_taxonomies($post_type);

        foreach ($taxonomies as $taxonomy) {
            if (!$this->polylang->is_taxonomy_translatable($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($source_id, $taxonomy, array('fields' => 'ids'));

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            $translated_terms = array();

            foreach ($terms as $term_id) {
                $translated_term = $this->polylang->get_term_translation($term_id, $lang);

                if ($translated_term) {
                    $translated_terms[] = $translated_term;
                }
            }

            if (!empty($translated_terms)) {
                wp_set_post_terms($target_id, $translated_terms, $taxonomy);
            }
        }
    }

    /**
     * Translate term
     *
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name
     * @param string $target_lang Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_term($term_id, $taxonomy, $target_lang, $options = array()) {
        $term = get_term($term_id, $taxonomy);

        if (!$term || is_wp_error($term)) {
            return array(
                'success' => false,
                'error' => __('Term not found.', 'wc-ai-translator')
            );
        }

        // Get source language
        $source_lang = $this->polylang->get_term_language($term_id);
        if (!$source_lang) {
            $source_lang = $this->polylang->get_default_language();
        }

        // Check if translation already exists
        $existing_translation = $this->polylang->get_term_translation($term_id, $target_lang);

        if ($existing_translation && !isset($options['overwrite'])) {
            return array(
                'success' => false,
                'error' => __('Translation already exists. Use overwrite option to replace it.', 'wc-ai-translator'),
                'existing_id' => $existing_translation
            );
        }

        // Translate name and description
        $total_tokens = 0;
        $total_cost = 0;

        $name_result = $this->openai->translate($term->name, $source_lang, $target_lang, array(
            'content_type' => 'Term name'
        ));

        if (!$name_result['success']) {
            return array(
                'success' => false,
                'error' => $name_result['error']
            );
        }

        $translated_name = $name_result['translated_text'];
        $total_tokens += $name_result['tokens_used'];
        $total_cost += $name_result['cost'];

        $translated_description = '';
        if (!empty($term->description)) {
            $desc_result = $this->openai->translate($term->description, $source_lang, $target_lang, array(
                'content_type' => 'Term description'
            ));

            if ($desc_result['success']) {
                $translated_description = $desc_result['translated_text'];
                $total_tokens += $desc_result['tokens_used'];
                $total_cost += $desc_result['cost'];
            }
        }

        // Create or update translation
        if ($existing_translation) {
            wp_update_term($existing_translation, $taxonomy, array(
                'name' => $translated_name,
                'description' => $translated_description,
            ));
            $new_term_id = $existing_translation;
        } else {
            $new_term_id = $this->polylang->duplicate_term_for_translation($term_id, $taxonomy, $target_lang);

            if (!$new_term_id) {
                return array(
                    'success' => false,
                    'error' => __('Failed to create translated term.', 'wc-ai-translator')
                );
            }

            wp_update_term($new_term_id, $taxonomy, array(
                'name' => $translated_name,
                'description' => $translated_description,
            ));
        }

        // Log success
        $this->log->add_log(array(
            'content_id' => $term_id,
            'content_type' => $taxonomy,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'action' => 'translate_term',
            'status' => 'success',
            'message' => sprintf('Term translated successfully. New term ID: %d', $new_term_id),
            'tokens_used' => $total_tokens,
            'cost' => $total_cost,
            'user_id' => get_current_user_id()
        ));

        return array(
            'success' => true,
            'term_id' => $new_term_id,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost,
            'message' => __('Term translated successfully.', 'wc-ai-translator')
        );
    }

    /**
     * Get translation preview
     *
     * @param string $text Text to preview
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return array Preview result
     */
    public function preview_translation($text, $source_lang, $target_lang) {
        if (empty($text)) {
            return array(
                'success' => false,
                'error' => __('No text provided for preview.', 'wc-ai-translator')
            );
        }

        return $this->openai->translate($text, $source_lang, $target_lang, array(
            'quality' => 'high'
        ));
    }
}
