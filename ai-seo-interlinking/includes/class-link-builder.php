<?php
/**
 * Link Builder Class
 * Handles internal link creation based on various strategies
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Link_Builder {

    /**
     * Settings
     */
    private $settings;

    /**
     * AI Processor instance
     */
    private $ai;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('ail_settings', []);
        $this->ai = AIL_AI_Processor::get_instance();
    }

    /**
     * Build links for a post
     *
     * @param int    $post_id  Post ID
     * @param string $strategy Linking strategy (optional)
     * @return array|WP_Error  Result data or error
     */
    public function build_links($post_id, $strategy = null) {
        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('invalid_post', __('Post not found or not published', 'ai-seo-interlinking'));
        }

        // Check if post should be excluded
        if ($this->is_post_excluded($post_id)) {
            return new WP_Error('excluded_post', __('Post is excluded from interlinking', 'ai-seo-interlinking'));
        }

        // Check minimum content length
        $word_count = str_word_count(wp_strip_all_tags($post->post_content));
        $min_length = isset($this->settings['min_content_length']) ? intval($this->settings['min_content_length']) : 300;

        if ($word_count < $min_length) {
            return new WP_Error('content_too_short', __('Content is too short for interlinking', 'ai-seo-interlinking'));
        }

        // Determine strategy
        if (!$strategy) {
            $strategy = isset($this->settings['linking_strategy']) ? $this->settings['linking_strategy'] : 'pyramid';
        }

        // Get language
        $language = AIL_Multilang_Support::get_post_language($post_id);

        // Find target posts based on strategy
        $target_posts = $this->find_target_posts($post, $strategy, $language);

        if (empty($target_posts)) {
            return new WP_Error('no_targets', __('No suitable target posts found', 'ai-seo-interlinking'));
        }

        // Apply SEO rules and create links
        $created_links = $this->create_links($post, $target_posts, $strategy, $language);

        AIL_Logger::log_operation(
            'build_links',
            [
                'post_id' => $post_id,
                'strategy' => $strategy,
                'links_created' => count($created_links),
            ]
        );

        return [
            'success' => true,
            'post_id' => $post_id,
            'links_created' => count($created_links),
            'strategy' => $strategy,
            'targets' => $created_links,
        ];
    }

    /**
     * Find target posts based on strategy
     *
     * @param WP_Post $post     Source post
     * @param string  $strategy Linking strategy
     * @param string  $language Content language
     * @return array            Array of target post IDs with scores
     */
    private function find_target_posts($post, $strategy, $language) {
        $targets = [];

        switch ($strategy) {
            case 'pyramid':
                $targets = $this->strategy_pyramid($post, $language);
                break;

            case 'circular':
                $targets = $this->strategy_circular($post, $language);
                break;

            case 'cluster':
                $targets = $this->strategy_cluster($post, $language);
                break;

            case 'hub':
                $targets = $this->strategy_hub($post, $language);
                break;

            case 'ai_auto':
                $targets = $this->strategy_ai_auto($post, $language);
                break;

            default:
                $targets = $this->strategy_pyramid($post, $language);
        }

        // Limit number of targets
        $max_links = isset($this->settings['max_outbound_links']) ? intval($this->settings['max_outbound_links']) : 5;
        $targets = array_slice($targets, 0, $max_links);

        return $targets;
    }

    /**
     * Pyramid strategy: Homepage → Categories → Products
     *
     * @param WP_Post $post     Source post
     * @param string  $language Content language
     * @return array            Target posts
     */
    private function strategy_pyramid($post, $language) {
        $targets = [];

        if ($post->post_type === 'product') {
            // Products link to categories and homepage
            $categories = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'ids']);

            foreach ($categories as $cat_id) {
                $targets[] = [
                    'post_id' => -$cat_id, // Negative ID for taxonomy term
                    'score' => 80,
                    'type' => 'category',
                ];
            }

            // Add homepage
            $targets[] = [
                'post_id' => get_option('page_on_front'),
                'score' => 60,
                'type' => 'homepage',
            ];

        } elseif ($post->post_type === 'page') {
            // Category pages link to related products and homepage
            $products = $this->get_related_products($post, $language, 5);

            foreach ($products as $product_id) {
                $targets[] = [
                    'post_id' => $product_id,
                    'score' => 70,
                    'type' => 'product',
                ];
            }
        }

        return $targets;
    }

    /**
     * Circular strategy: Same level pages
     *
     * @param WP_Post $post     Source post
     * @param string  $language Content language
     * @return array            Target posts
     */
    private function strategy_circular($post, $language) {
        $targets = [];

        $args = [
            'post_type' => $post->post_type,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'post__not_in' => [$post->ID],
            'orderby' => 'rand',
        ];

        if ($language !== 'default') {
            $args = AIL_Multilang_Support::add_language_args($args, $language);
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $targets[] = [
                    'post_id' => get_the_ID(),
                    'score' => 50,
                    'type' => 'same_level',
                ];
            }
            wp_reset_postdata();
        }

        return $targets;
    }

    /**
     * Cluster strategy: Semantic relevance
     *
     * @param WP_Post $post     Source post
     * @param string  $language Content language
     * @return array            Target posts
     */
    private function strategy_cluster($post, $language) {
        $targets = [];

        // Get all published posts
        $args = [
            'post_type' => ['post', 'page', 'product'],
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'post__not_in' => [$post->ID],
        ];

        if ($language !== 'default') {
            $args = AIL_Multilang_Support::add_language_args($args, $language);
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $target_id = get_the_ID();

                // Analyze relevance using AI
                $score = $this->ai->analyze_relevance(
                    $post->post_content,
                    get_the_content(),
                    $language
                );

                if (!is_wp_error($score) && $score >= 40) {
                    $targets[] = [
                        'post_id' => $target_id,
                        'score' => $score,
                        'type' => 'semantic',
                    ];
                }
            }
            wp_reset_postdata();
        }

        // Sort by score descending
        usort($targets, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $targets;
    }

    /**
     * Hub strategy: Pillar pages + supporting content
     *
     * @param WP_Post $post     Source post
     * @param string  $language Content language
     * @return array            Target posts
     */
    private function strategy_hub($post, $language) {
        $targets = [];

        // Identify pillar pages (pages with custom field or specific category)
        $pillar_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'meta_key' => 'is_pillar_page',
            'meta_value' => '1',
            'posts_per_page' => -1,
        ]);

        foreach ($pillar_pages as $pillar) {
            if ($pillar->ID !== $post->ID) {
                $targets[] = [
                    'post_id' => $pillar->ID,
                    'score' => 90,
                    'type' => 'pillar',
                ];
            }
        }

        return $targets;
    }

    /**
     * AI Auto strategy: Let AI decide
     *
     * @param WP_Post $post     Source post
     * @param string  $language Content language
     * @return array            Target posts
     */
    private function strategy_ai_auto($post, $language) {
        // Combine all strategies and let AI rank them
        $all_targets = array_merge(
            $this->strategy_pyramid($post, $language),
            $this->strategy_circular($post, $language),
            $this->strategy_cluster($post, $language)
        );

        // Remove duplicates
        $unique_targets = [];
        $seen_ids = [];

        foreach ($all_targets as $target) {
            if (!in_array($target['post_id'], $seen_ids)) {
                $unique_targets[] = $target;
                $seen_ids[] = $target['post_id'];
            }
        }

        // Sort by score
        usort($unique_targets, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $unique_targets;
    }

    /**
     * Create links in post content
     *
     * @param WP_Post $post        Source post
     * @param array   $targets     Target posts
     * @param string  $strategy    Linking strategy
     * @param string  $language    Content language
     * @return array               Created links
     */
    private function create_links($post, $targets, $strategy, $language) {
        global $wpdb;

        $created_links = [];
        $content = $post->post_content;
        $keywords = $this->get_keywords_for_post($post->ID, $language);

        foreach ($targets as $target_data) {
            $target_id = $target_data['post_id'];

            // Skip if already linked
            if ($this->already_linked($post->ID, $target_id)) {
                continue;
            }

            // Check inbound link limit
            if ($this->exceeds_inbound_limit($target_id)) {
                continue;
            }

            // Get anchor text
            $anchor = $this->get_anchor_text($target_id, $keywords, $language);

            if (!$anchor) {
                continue;
            }

            // Find and replace in content
            $pattern = '/\b' . preg_quote($anchor, '/') . '\b/i';

            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $target_url = get_permalink($target_id);
                $replacement = sprintf('<a href="%s">%s</a>', esc_url($target_url), $matches[0][0]);

                // Replace only first occurrence
                $content = substr_replace(
                    $content,
                    $replacement,
                    $matches[0][1],
                    strlen($matches[0][0])
                );

                // Save to database
                $wpdb->insert(
                    $wpdb->prefix . 'ai_interlinking_links',
                    [
                        'source_post_id' => $post->ID,
                        'target_post_id' => $target_id,
                        'anchor_text' => $anchor,
                        'anchor_type' => 'exact_match',
                        'language' => $language,
                        'strategy' => $strategy,
                        'relevance_score' => $target_data['score'],
                    ],
                    ['%d', '%d', '%s', '%s', '%s', '%s', '%f']
                );

                $created_links[] = [
                    'target_id' => $target_id,
                    'anchor' => $anchor,
                    'score' => $target_data['score'],
                ];

                AIL_Logger::log_link_creation($post->ID, $target_id, $anchor, $strategy);
            }
        }

        // Update post content
        if (!empty($created_links)) {
            wp_update_post([
                'ID' => $post->ID,
                'post_content' => $content,
            ]);
        }

        return $created_links;
    }

    /**
     * Get keywords for post
     *
     * @param int    $post_id  Post ID
     * @param string $language Language code
     * @return array           Keywords
     */
    private function get_keywords_for_post($post_id, $language) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_interlinking_keywords';

        $keywords = $wpdb->get_col($wpdb->prepare(
            "SELECT keyword FROM {$table} WHERE (post_id = %d OR post_id IS NULL) AND language = %s ORDER BY priority DESC",
            $post_id,
            $language
        ));

        return $keywords;
    }

    /**
     * Get anchor text for target post
     *
     * @param int    $target_id Target post ID
     * @param array  $keywords  Available keywords
     * @param string $language  Language code
     * @return string|false     Anchor text or false
     */
    private function get_anchor_text($target_id, $keywords, $language) {
        $target_post = get_post($target_id);

        if (!$target_post) {
            return false;
        }

        // Try to find matching keyword in content
        foreach ($keywords as $keyword) {
            if (stripos($target_post->post_title, $keyword) !== false) {
                return $keyword;
            }
        }

        // Use post title as fallback
        return $target_post->post_title;
    }

    /**
     * Check if posts are already linked
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     * @return bool          True if already linked
     */
    private function already_linked($source_id, $target_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_interlinking_links';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE source_post_id = %d AND target_post_id = %d",
            $source_id,
            $target_id
        ));

        return $count > 0;
    }

    /**
     * Check if target exceeds inbound link limit
     *
     * @param int $target_id Target post ID
     * @return bool          True if exceeds limit
     */
    private function exceeds_inbound_limit($target_id) {
        global $wpdb;

        $max_inbound = isset($this->settings['max_inbound_links']) ? intval($this->settings['max_inbound_links']) : 10;
        $table = $wpdb->prefix . 'ai_interlinking_links';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE target_post_id = %d",
            $target_id
        ));

        return $count >= $max_inbound;
    }

    /**
     * Check if post is excluded
     *
     * @param int $post_id Post ID
     * @return bool        True if excluded
     */
    private function is_post_excluded($post_id) {
        $excluded_pages = isset($this->settings['excluded_pages']) ? $this->settings['excluded_pages'] : [];

        if (in_array($post_id, $excluded_pages)) {
            return true;
        }

        $post = get_post($post_id);
        if ($post->post_type === 'product') {
            $categories = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'ids']);
            $excluded_cats = isset($this->settings['excluded_categories']) ? $this->settings['excluded_categories'] : [];

            foreach ($categories as $cat_id) {
                if (in_array($cat_id, $excluded_cats)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get related products
     *
     * @param WP_Post $post     Source post
     * @param string  $language Language code
     * @param int     $limit    Number of products
     * @return array            Product IDs
     */
    private function get_related_products($post, $language, $limit = 5) {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'rand',
        ];

        if ($language !== 'default') {
            $args = AIL_Multilang_Support::add_language_args($args, $language);
        }

        $query = new WP_Query($args);
        $product_ids = wp_list_pluck($query->posts, 'ID');

        return $product_ids;
    }
}
