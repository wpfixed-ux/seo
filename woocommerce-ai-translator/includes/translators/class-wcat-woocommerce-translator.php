<?php
/**
 * WooCommerce Translator
 *
 * @package WC_AI_Translator
 */

class WCAT_WooCommerce_Translator {

    /**
     * OpenAI instance
     */
    private $openai;

    /**
     * Polylang instance
     */
    private $polylang;

    /**
     * Content translator
     */
    private $content_translator;

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
        $this->content_translator = new WCAT_Content_Translator();
        $this->log = new WCAT_Translation_Log();
    }

    /**
     * Translate WooCommerce product
     *
     * @param int $product_id Product ID
     * @param string $target_lang Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_product($product_id, $target_lang, $options = array()) {
        // First translate the post itself (title, content, excerpt)
        $result = $this->content_translator->translate_post($product_id, $target_lang, $options);

        if (!$result['success']) {
            return $result;
        }

        $translated_id = $result['post_id'];

        // Translate product-specific fields
        $product_result = $this->translate_product_fields($product_id, $translated_id, $target_lang);

        return array_merge($result, array(
            'product_fields' => $product_result
        ));
    }

    /**
     * Translate product-specific fields
     *
     * @param int $source_id Source product ID
     * @param int $target_id Target product ID
     * @param string $target_lang Target language
     * @return array Translation result
     */
    private function translate_product_fields($source_id, $target_id, $target_lang) {
        $source_lang = $this->polylang->get_post_language($source_id);
        if (!$source_lang) {
            $source_lang = $this->polylang->get_default_language();
        }

        $total_tokens = 0;
        $total_cost = 0;

        // Get product objects
        $source_product = wc_get_product($source_id);
        $target_product = wc_get_product($target_id);

        if (!$source_product || !$target_product) {
            return array('success' => false, 'error' => __('Product not found.', 'wc-ai-translator'));
        }

        // Translate short description (if not already done)
        $short_description = $source_product->get_short_description();
        if (!empty($short_description)) {
            $result = $this->openai->translate($short_description, $source_lang, $target_lang, array(
                'content_type' => 'Product short description'
            ));

            if ($result['success']) {
                $target_product->set_short_description($result['translated_text']);
                $total_tokens += $result['tokens_used'];
                $total_cost += $result['cost'];
            }
        }

        // Translate product attributes
        $attributes = $source_product->get_attributes();
        if (!empty($attributes)) {
            $translated_attributes = array();

            foreach ($attributes as $key => $attribute) {
                if (is_a($attribute, 'WC_Product_Attribute')) {
                    $attr_name = $attribute->get_name();
                    $attr_options = $attribute->get_options();

                    // Translate attribute values
                    if (!empty($attr_options) && is_array($attr_options)) {
                        $translated_options = array();

                        foreach ($attr_options as $option) {
                            $result = $this->openai->translate($option, $source_lang, $target_lang, array(
                                'content_type' => 'Product attribute'
                            ));

                            if ($result['success']) {
                                $translated_options[] = $result['translated_text'];
                                $total_tokens += $result['tokens_used'];
                                $total_cost += $result['cost'];
                            } else {
                                $translated_options[] = $option;
                            }
                        }

                        $new_attribute = new WC_Product_Attribute();
                        $new_attribute->set_name($attr_name);
                        $new_attribute->set_options($translated_options);
                        $new_attribute->set_visible($attribute->get_visible());
                        $new_attribute->set_variation($attribute->get_variation());

                        $translated_attributes[$key] = $new_attribute;
                    }
                }
            }

            if (!empty($translated_attributes)) {
                $target_product->set_attributes($translated_attributes);
            }
        }

        // Save the product
        $target_product->save();

        return array(
            'success' => true,
            'tokens_used' => $total_tokens,
            'cost' => $total_cost
        );
    }

    /**
     * Translate product category
     *
     * @param int $term_id Category term ID
     * @param string $target_lang Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_product_category($term_id, $target_lang, $options = array()) {
        $result = $this->content_translator->translate_term($term_id, 'product_cat', $target_lang, $options);

        if (!$result['success']) {
            return $result;
        }

        $translated_term_id = $result['term_id'];

        // Translate category thumbnail if needed
        $settings = get_option('wcat_settings');
        if (isset($settings['translate_images']) && $settings['translate_images']) {
            $this->translate_term_thumbnail($term_id, $translated_term_id, $target_lang);
        }

        return $result;
    }

    /**
     * Translate product tag
     *
     * @param int $term_id Tag term ID
     * @param string $target_lang Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_product_tag($term_id, $target_lang, $options = array()) {
        return $this->content_translator->translate_term($term_id, 'product_tag', $target_lang, $options);
    }

    /**
     * Translate product attribute taxonomy
     *
     * @param int $term_id Attribute term ID
     * @param string $taxonomy Attribute taxonomy name
     * @param string $target_lang Target language code
     * @param array $options Translation options
     * @return array Translation result
     */
    public function translate_product_attribute($term_id, $taxonomy, $target_lang, $options = array()) {
        return $this->content_translator->translate_term($term_id, $taxonomy, $target_lang, $options);
    }

    /**
     * Translate term thumbnail alt text
     *
     * @param int $source_term_id Source term ID
     * @param int $target_term_id Target term ID
     * @param string $target_lang Target language
     */
    private function translate_term_thumbnail($source_term_id, $target_term_id, $target_lang) {
        $thumbnail_id = get_term_meta($source_term_id, 'thumbnail_id', true);

        if ($thumbnail_id) {
            // Copy thumbnail to translated term
            update_term_meta($target_term_id, 'thumbnail_id', $thumbnail_id);

            // Translate alt text
            $source_lang = $this->polylang->get_term_language($source_term_id);
            if (!$source_lang) {
                $source_lang = $this->polylang->get_default_language();
            }

            $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);

            if (!empty($alt_text)) {
                $result = $this->openai->translate($alt_text, $source_lang, $target_lang, array(
                    'content_type' => 'Image alt text'
                ));

                if ($result['success']) {
                    // This would typically be stored in a language-specific way
                    // For now, we'll add it as custom meta
                    update_term_meta($target_term_id, '_thumbnail_alt_' . $target_lang, $result['translated_text']);
                }
            }
        }
    }

    /**
     * Translate product variations
     *
     * @param int $parent_id Parent product ID
     * @param string $target_lang Target language
     * @return array Translation results
     */
    public function translate_product_variations($parent_id, $target_lang) {
        $source_product = wc_get_product($parent_id);

        if (!$source_product || !$source_product->is_type('variable')) {
            return array(
                'success' => false,
                'error' => __('Not a variable product.', 'wc-ai-translator')
            );
        }

        $translated_parent_id = $this->polylang->get_post_translation($parent_id, $target_lang);

        if (!$translated_parent_id) {
            return array(
                'success' => false,
                'error' => __('Parent product translation not found. Please translate the parent product first.', 'wc-ai-translator')
            );
        }

        $variations = $source_product->get_children();
        $results = array();

        foreach ($variations as $variation_id) {
            $result = $this->content_translator->translate_post($variation_id, $target_lang);
            $results[$variation_id] = $result;

            if ($result['success']) {
                // Link variation to translated parent
                wp_update_post(array(
                    'ID' => $result['post_id'],
                    'post_parent' => $translated_parent_id
                ));
            }
        }

        return array(
            'success' => true,
            'variations' => $results
        );
    }

    /**
     * Get all WooCommerce content to translate
     *
     * @param array $args Query arguments
     * @return array Array of content items
     */
    public function get_woocommerce_content($args = array()) {
        $defaults = array(
            'include_products' => true,
            'include_categories' => true,
            'include_tags' => true,
            'include_attributes' => true,
        );

        $args = wp_parse_args($args, $defaults);
        $content = array();

        // Get products
        if ($args['include_products']) {
            $products = get_posts(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids',
            ));

            foreach ($products as $product_id) {
                $content[] = array(
                    'id' => $product_id,
                    'type' => 'product',
                    'title' => get_the_title($product_id),
                    'language' => $this->polylang->get_post_language($product_id),
                );
            }
        }

        // Get categories
        if ($args['include_categories']) {
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));

            foreach ($categories as $category) {
                $content[] = array(
                    'id' => $category->term_id,
                    'type' => 'product_cat',
                    'title' => $category->name,
                    'language' => $this->polylang->get_term_language($category->term_id),
                );
            }
        }

        // Get tags
        if ($args['include_tags']) {
            $tags = get_terms(array(
                'taxonomy' => 'product_tag',
                'hide_empty' => false,
            ));

            foreach ($tags as $tag) {
                $content[] = array(
                    'id' => $tag->term_id,
                    'type' => 'product_tag',
                    'title' => $tag->name,
                    'language' => $this->polylang->get_term_language($tag->term_id),
                );
            }
        }

        // Get product attributes
        if ($args['include_attributes']) {
            $attribute_taxonomies = wc_get_attribute_taxonomies();

            foreach ($attribute_taxonomies as $attribute) {
                $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                ));

                foreach ($terms as $term) {
                    $content[] = array(
                        'id' => $term->term_id,
                        'type' => $taxonomy,
                        'title' => $term->name,
                        'language' => $this->polylang->get_term_language($term->term_id),
                    );
                }
            }
        }

        return $content;
    }
}
