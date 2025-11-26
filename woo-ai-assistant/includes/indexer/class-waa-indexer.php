<?php
/**
 * Content indexer for products and posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Indexer {

    private static $instance = null;
    private $vector_db;
    private $batch_size = 50; // Increased for faster indexing
    private $max_content_length = 6000; // Safe limit for embeddings (~1500 tokens)

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->vector_db = WAA_Vector_DB::get_instance();

        // AJAX handlers
        add_action('wp_ajax_waa_start_indexing', array($this, 'ajax_start_indexing'));
        add_action('wp_ajax_waa_index_batch', array($this, 'ajax_index_batch'));
        add_action('wp_ajax_waa_get_index_status', array($this, 'ajax_get_index_status'));
        add_action('wp_ajax_waa_clear_index', array($this, 'ajax_clear_index'));

        // Auto-index on product save
        add_action('save_post_product', array($this, 'index_single_product'), 10, 3);
        add_action('save_post', array($this, 'index_single_post'), 10, 3);

        // Cleanup on product/post delete
        add_action('before_delete_post', array($this, 'cleanup_deleted_post'));
        add_action('wp_trash_post', array($this, 'cleanup_deleted_post'));

        // Scheduled reindexing
        add_action('waa_scheduled_reindex', array($this, 'scheduled_reindex'));

        // Scheduled cleanup
        add_action('waa_scheduled_cleanup', array($this, 'scheduled_cleanup'));
    }

    /**
     * Cleanup embedding when post/product is deleted or trashed
     */
    public function cleanup_deleted_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        if ($post->post_type === 'product') {
            $this->vector_db->delete_embedding($post_id, 'product');
        } else {
            $post_types = get_option('waa_post_types', array('post', 'page'));
            if (in_array($post->post_type, $post_types)) {
                $this->vector_db->delete_embedding($post_id, 'post');
            }
        }
    }

    /**
     * Scheduled cleanup task
     * - Removes orphaned embeddings (products that no longer exist)
     * - Cleans old chat history (older than 90 days)
     */
    public function scheduled_cleanup() {
        global $wpdb;

        // 1. Remove orphaned product embeddings
        $orphaned_products = $wpdb->query(
            "DELETE v FROM {$wpdb->prefix}waa_vectors v
             LEFT JOIN {$wpdb->posts} p ON v.object_id = p.ID
             WHERE v.object_type = 'product' AND p.ID IS NULL"
        );

        // 2. Remove orphaned post embeddings
        $orphaned_posts = $wpdb->query(
            "DELETE v FROM {$wpdb->prefix}waa_vectors v
             LEFT JOIN {$wpdb->posts} p ON v.object_id = p.ID
             WHERE v.object_type = 'post' AND p.ID IS NULL"
        );

        // 3. Clean old chat history (keep last 90 days)
        $days_to_keep = apply_filters('waa_chat_history_days', 90);
        $old_chats = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}waa_chat_history
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ));

        // Log results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WAA Cleanup: Removed $orphaned_products orphaned products, $orphaned_posts orphaned posts, $old_chats old chat messages");
        }
    }

    /**
     * Scheduled automatic reindexing
     * Runs via WP Cron at configured time (e.g., 6:00 AM)
     */
    public function scheduled_reindex() {
        if (!get_option('waa_auto_reindex', true)) {
            return;
        }

        global $wpdb;
        $ai_provider = WAA_Core::get_ai_provider();
        $languages = get_option('waa_languages', array('ru'));

        // Get all published products
        $products = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );

        $updated = 0;
        $errors = array();

        foreach ($products as $product_id) {
            foreach ($languages as $language) {
                $content_data = $this->get_product_content($product_id, $language);

                if (!$content_data) {
                    continue;
                }

                // Only reindex if content changed (price, stock, description)
                if (!$this->vector_db->needs_reindex($product_id, 'product', $content_data['hash'], $language)) {
                    continue;
                }

                $embedding_result = $ai_provider->get_embedding($content_data['content']);

                if (!$embedding_result['success']) {
                    $errors[] = "Product $product_id: " . $embedding_result['error'];
                    continue;
                }

                $this->vector_db->store_embedding(
                    $product_id,
                    'product',
                    $embedding_result['embedding'],
                    $content_data['metadata'],
                    $language
                );

                $updated++;

                // Small delay to avoid API rate limits
                usleep(100000); // 100ms
            }
        }

        // Update last index date
        $wpdb->update(
            $wpdb->prefix . 'waa_index_status',
            array('last_index_date' => current_time('mysql')),
            array('id' => 1)
        );

        // Log results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WAA Scheduled Reindex: Updated $updated products. Errors: " . count($errors));
        }
    }

    /**
     * Truncate text to safe length for embeddings
     */
    private function truncate_text($text, $max_length = null) {
        if ($max_length === null) {
            $max_length = $this->max_content_length;
        }

        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace

        if (mb_strlen($text) > $max_length) {
            $text = mb_substr($text, 0, $max_length) . '...';
        }

        return trim($text);
    }

    /**
     * Get content to index for a product
     */
    private function get_product_content($product_id, $language = 'ru') {
        $product = wc_get_product($product_id);

        if (!$product) {
            return null;
        }

        // Basic product data
        $title = $product->get_name();
        $description = $product->get_description();
        $short_description = $product->get_short_description();
        $price = $product->get_price();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $sku = $product->get_sku();
        $stock_status = $product->get_stock_status();
        $stock_quantity = $product->get_stock_quantity();

        // Categories
        $categories = array();
        $terms = get_the_terms($product_id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = $term->name;
            }
        }

        // Tags
        $tags = array();
        $tag_terms = get_the_terms($product_id, 'product_tag');
        if ($tag_terms && !is_wp_error($tag_terms)) {
            foreach ($tag_terms as $term) {
                $tags[] = $term->name;
            }
        }

        // Attributes
        $attributes = array();
        foreach ($product->get_attributes() as $attr) {
            if (is_a($attr, 'WC_Product_Attribute')) {
                $attr_name = wc_attribute_label($attr->get_name());
                $attr_values = $attr->get_options();
                if ($attr->is_taxonomy()) {
                    $attr_values = array();
                    foreach ($attr->get_terms() as $term) {
                        $attr_values[] = $term->name;
                    }
                }
                $attributes[$attr_name] = implode(', ', $attr_values);
            }
        }

        // Stock status text
        $stock_text = '';
        if ($stock_status === 'instock') {
            $stock_text = $language === 'uk' ? 'в наявності' : 'в наличии';
            if ($stock_quantity !== null) {
                $stock_text .= " ($stock_quantity " . ($language === 'uk' ? 'шт' : 'шт') . ")";
            }
        } elseif ($stock_status === 'outofstock') {
            $stock_text = $language === 'uk' ? 'немає в наявності' : 'нет в наличии';
        } elseif ($stock_status === 'onbackorder') {
            $stock_text = $language === 'uk' ? 'під замовлення' : 'под заказ';
        }

        // Build text for embedding
        $content_parts = array(
            "Название: $title",
            "Цена: " . wc_price($price),
        );

        if ($sale_price) {
            $content_parts[] = "Скидка: было " . wc_price($regular_price) . ", стало " . wc_price($sale_price);
        }

        $content_parts[] = "Наличие: $stock_text";

        if (!empty($categories)) {
            $content_parts[] = "Категории: " . implode(', ', $categories);
        }

        if (!empty($short_description)) {
            $content_parts[] = "Краткое описание: " . $this->truncate_text($short_description, 500);
        }

        if (!empty($description)) {
            $content_parts[] = "Описание: " . $this->truncate_text($description, 2000);
        }

        if (!empty($attributes)) {
            $attr_text = array();
            foreach ($attributes as $name => $value) {
                $attr_text[] = "$name: $value";
            }
            $content_parts[] = "Характеристики: " . implode('; ', $attr_text);
        }

        if (!empty($tags)) {
            $content_parts[] = "Теги: " . implode(', ', $tags);
        }

        $content = implode("\n", $content_parts);

        // Ensure total content doesn't exceed embedding limit
        if (mb_strlen($content) > $this->max_content_length) {
            $content = mb_substr($content, 0, $this->max_content_length) . '...';
        }

        // Metadata for retrieval
        $metadata = array(
            'title' => $title,
            'price' => $price,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'stock_status' => $stock_status,
            'stock_quantity' => $stock_quantity,
            'stock_text' => $stock_text,
            'categories' => $categories,
            'url' => get_permalink($product_id),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'sku' => $sku,
        );

        return array(
            'content' => $content,
            'metadata' => $metadata,
            'hash' => md5($content)
        );
    }

    /**
     * Get content to index for a post
     */
    private function get_post_content($post_id, $language = 'ru') {
        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return null;
        }

        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = $post->post_excerpt;

        // Categories
        $categories = array();
        $terms = get_the_category($post_id);
        if ($terms) {
            foreach ($terms as $term) {
                $categories[] = $term->name;
            }
        }

        // Tags
        $tags = array();
        $tag_terms = get_the_tags($post_id);
        if ($tag_terms) {
            foreach ($tag_terms as $term) {
                $tags[] = $term->name;
            }
        }

        // Build text for embedding
        $content_parts = array(
            "Заголовок: $title",
        );

        if (!empty($excerpt)) {
            $content_parts[] = "Описание: $excerpt";
        }

        if (!empty($categories)) {
            $content_parts[] = "Категории: " . implode(', ', $categories);
        }

        $content_parts[] = "Содержание: " . $this->truncate_text($content, 2000);

        if (!empty($tags)) {
            $content_parts[] = "Теги: " . implode(', ', $tags);
        }

        $full_content = implode("\n", $content_parts);

        // Ensure total content doesn't exceed embedding limit
        if (mb_strlen($full_content) > $this->max_content_length) {
            $full_content = mb_substr($full_content, 0, $this->max_content_length) . '...';
        }

        $metadata = array(
            'title' => $title,
            'excerpt' => $excerpt,
            'categories' => $categories,
            'url' => get_permalink($post_id),
            'type' => $post->post_type,
        );

        return array(
            'content' => $full_content,
            'metadata' => $metadata,
            'hash' => md5($full_content)
        );
    }

    /**
     * Start indexing process
     */
    public function ajax_start_indexing() {
        check_ajax_referer('waa_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;

        // Count products
        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );

        // Count posts
        $post_types = get_option('waa_post_types', array('post', 'page'));
        $post_types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
        $total_posts = 0;

        if (get_option('waa_index_posts', true)) {
            $total_posts = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($post_types_sql) AND post_status = 'publish'"
            );
        }

        // Update status
        $wpdb->update(
            $wpdb->prefix . 'waa_index_status',
            array(
                'total_products' => $total_products,
                'indexed_products' => 0,
                'total_posts' => $total_posts,
                'indexed_posts' => 0,
                'status' => 'indexing',
                'last_index_date' => current_time('mysql')
            ),
            array('id' => 1)
        );

        wp_send_json_success(array(
            'total_products' => $total_products,
            'total_posts' => $total_posts,
            'message' => 'Indexing started'
        ));
    }

    /**
     * Index a batch of items
     */
    public function ajax_index_batch() {
        check_ajax_referer('waa_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;

        $status = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}waa_index_status WHERE id = 1");
        $ai_provider = WAA_Core::get_ai_provider();
        $languages = get_option('waa_languages', array('ru'));

        $indexed = 0;
        $errors = array();

        // Index products first
        if ($status->indexed_products < $status->total_products) {
            $products = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID LIMIT %d OFFSET %d",
                $this->batch_size,
                $status->indexed_products
            ));

            foreach ($products as $product_id) {
                foreach ($languages as $language) {
                    $content_data = $this->get_product_content($product_id, $language);

                    if (!$content_data) {
                        continue;
                    }

                    // Check if needs reindexing
                    if (!$this->vector_db->needs_reindex($product_id, 'product', $content_data['hash'], $language)) {
                        $indexed++;
                        continue;
                    }

                    // Get embedding
                    $embedding_result = $ai_provider->get_embedding($content_data['content']);

                    if (!$embedding_result['success']) {
                        $errors[] = "Product $product_id: " . $embedding_result['error'];
                        continue;
                    }

                    // Store embedding
                    $this->vector_db->store_embedding(
                        $product_id,
                        'product',
                        $embedding_result['embedding'],
                        $content_data['metadata'],
                        $language
                    );

                    $indexed++;
                }
            }

            // Update progress
            $wpdb->update(
                $wpdb->prefix . 'waa_index_status',
                array('indexed_products' => $status->indexed_products + count($products)),
                array('id' => 1)
            );

            $status->indexed_products += count($products);
        }

        // Then index posts
        if ($status->indexed_products >= $status->total_products && $status->indexed_posts < $status->total_posts) {
            $post_types = get_option('waa_post_types', array('post', 'page'));
            $post_types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

            $posts = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($post_types_sql) AND post_status = 'publish' ORDER BY ID LIMIT %d OFFSET %d",
                $this->batch_size,
                $status->indexed_posts
            ));

            foreach ($posts as $post_id) {
                foreach ($languages as $language) {
                    $content_data = $this->get_post_content($post_id, $language);

                    if (!$content_data) {
                        continue;
                    }

                    if (!$this->vector_db->needs_reindex($post_id, 'post', $content_data['hash'], $language)) {
                        $indexed++;
                        continue;
                    }

                    $embedding_result = $ai_provider->get_embedding($content_data['content']);

                    if (!$embedding_result['success']) {
                        $errors[] = "Post $post_id: " . $embedding_result['error'];
                        continue;
                    }

                    $this->vector_db->store_embedding(
                        $post_id,
                        'post',
                        $embedding_result['embedding'],
                        $content_data['metadata'],
                        $language
                    );

                    $indexed++;
                }
            }

            $wpdb->update(
                $wpdb->prefix . 'waa_index_status',
                array('indexed_posts' => $status->indexed_posts + count($posts)),
                array('id' => 1)
            );

            $status->indexed_posts += count($posts);
        }

        // Check if complete
        $is_complete = ($status->indexed_products >= $status->total_products) &&
                       ($status->indexed_posts >= $status->total_posts);

        if ($is_complete) {
            $wpdb->update(
                $wpdb->prefix . 'waa_index_status',
                array('status' => 'complete'),
                array('id' => 1)
            );
        }

        wp_send_json_success(array(
            'indexed_products' => $status->indexed_products,
            'indexed_posts' => $status->indexed_posts,
            'total_products' => $status->total_products,
            'total_posts' => $status->total_posts,
            'is_complete' => $is_complete,
            'errors' => $errors
        ));
    }

    /**
     * Get current index status
     */
    public function ajax_get_index_status() {
        check_ajax_referer('waa_admin_nonce', 'nonce');

        global $wpdb;
        $status = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}waa_index_status WHERE id = 1");

        $stats = $this->vector_db->get_stats();

        wp_send_json_success(array(
            'status' => $status,
            'stats' => $stats
        ));
    }

    /**
     * Clear all index data
     */
    public function ajax_clear_index() {
        check_ajax_referer('waa_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $this->vector_db->clear_all();

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'waa_index_status',
            array(
                'indexed_products' => 0,
                'indexed_posts' => 0,
                'status' => 'idle'
            ),
            array('id' => 1)
        );

        wp_send_json_success('Index cleared');
    }

    /**
     * Index single product on save
     */
    public function index_single_product($post_id, $post, $update) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            $this->vector_db->delete_embedding($post_id, 'product');
            return;
        }

        $ai_provider = WAA_Core::get_ai_provider();
        $languages = get_option('waa_languages', array('ru'));

        foreach ($languages as $language) {
            $content_data = $this->get_product_content($post_id, $language);

            if (!$content_data) {
                continue;
            }

            $embedding_result = $ai_provider->get_embedding($content_data['content']);

            if ($embedding_result['success']) {
                $this->vector_db->store_embedding(
                    $post_id,
                    'product',
                    $embedding_result['embedding'],
                    $content_data['metadata'],
                    $language
                );
            }
        }
    }

    /**
     * Index single post on save
     */
    public function index_single_post($post_id, $post, $update) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $post_types = get_option('waa_post_types', array('post', 'page'));

        if (!in_array($post->post_type, $post_types)) {
            return;
        }

        if (!get_option('waa_index_posts', true)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            $this->vector_db->delete_embedding($post_id, 'post');
            return;
        }

        $ai_provider = WAA_Core::get_ai_provider();
        $languages = get_option('waa_languages', array('ru'));

        foreach ($languages as $language) {
            $content_data = $this->get_post_content($post_id, $language);

            if (!$content_data) {
                continue;
            }

            $embedding_result = $ai_provider->get_embedding($content_data['content']);

            if ($embedding_result['success']) {
                $this->vector_db->store_embedding(
                    $post_id,
                    'post',
                    $embedding_result['embedding'],
                    $content_data['metadata'],
                    $language
                );
            }
        }
    }
}
