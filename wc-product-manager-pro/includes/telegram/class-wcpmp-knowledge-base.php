<?php
/**
 * Knowledge Base for Telegram Bot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Knowledge_Base {

    /**
     * Add knowledge entry
     */
    public function add($data) {
        global $wpdb;

        return $wpdb->insert($wpdb->prefix . 'wcpmp_knowledge_base', array(
            'title' => sanitize_text_field($data['title']),
            'content' => sanitize_textarea_field($data['content']),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'language' => sanitize_text_field($data['language'] ?? 'uk')
        ));
    }

    /**
     * Update knowledge entry
     */
    public function update($id, $data) {
        global $wpdb;

        $update = array();

        if (isset($data['title'])) {
            $update['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['content'])) {
            $update['content'] = sanitize_textarea_field($data['content']);
        }
        if (isset($data['category'])) {
            $update['category'] = sanitize_text_field($data['category']);
        }
        if (isset($data['tags'])) {
            $update['tags'] = sanitize_text_field($data['tags']);
        }

        return $wpdb->update(
            $wpdb->prefix . 'wcpmp_knowledge_base',
            $update,
            array('id' => $id)
        );
    }

    /**
     * Delete knowledge entry
     */
    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'wcpmp_knowledge_base', array('id' => $id));
    }

    /**
     * Get all entries
     */
    public function get_all($args = array()) {
        global $wpdb;

        $defaults = array(
            'category' => '',
            'language' => '',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['category']) {
            $where[] = 'category = %s';
            $values[] = $args['category'];
        }

        if ($args['language']) {
            $where[] = 'language = %s';
            $values[] = $args['language'];
        }

        if ($args['search']) {
            $where[] = '(title LIKE %s OR content LIKE %s OR tags LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT * FROM {$wpdb->prefix}wcpmp_knowledge_base WHERE $where_sql ORDER BY title";

        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, $values));
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get single entry
     */
    public function get($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_knowledge_base WHERE id = %d",
            $id
        ));
    }

    /**
     * Search knowledge base
     */
    public function search($query, $language = 'uk') {
        global $wpdb;

        // Simple keyword search
        $keywords = explode(' ', $query);
        $results = array();

        foreach ($keywords as $keyword) {
            if (strlen($keyword) < 3) continue;

            $search = '%' . $wpdb->esc_like($keyword) . '%';

            $entries = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}wcpmp_knowledge_base
                WHERE (language = %s OR language = '')
                AND (title LIKE %s OR content LIKE %s OR tags LIKE %s)
            ", $language, $search, $search, $search));

            foreach ($entries as $entry) {
                if (!isset($results[$entry->id])) {
                    $results[$entry->id] = $entry;
                }
            }
        }

        // Build context from results
        $context = '';
        foreach ($results as $entry) {
            $context .= "## {$entry->title}\n{$entry->content}\n\n";
        }

        return $context;
    }

    /**
     * Get categories
     */
    public function get_categories() {
        global $wpdb;

        return $wpdb->get_col("
            SELECT DISTINCT category
            FROM {$wpdb->prefix}wcpmp_knowledge_base
            WHERE category != ''
            ORDER BY category
        ");
    }

    /**
     * Import from products
     */
    public function import_from_products($language = 'uk') {
        global $wpdb;

        $products = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}wcpmp_products
            WHERE stock_quantity > 0
        ");

        $imported = 0;

        foreach ($products as $product) {
            $name = $language === 'uk' ? $product->name_uk : ($product->name_ru ?: $product->name_uk);
            $description = $language === 'uk' ? $product->description_uk : ($product->description_ru ?: $product->description_uk);

            if (empty($name)) continue;

            // Check if already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wcpmp_knowledge_base WHERE title = %s AND language = %s",
                $name, $language
            ));

            if ($exists) continue;

            $content = "Product: $name\nSKU: {$product->sku}\nPrice: {$product->price} UAH\n";
            if ($product->sale_price) {
                $content .= "Sale Price: {$product->sale_price} UAH\n";
            }
            $content .= "Stock: {$product->stock_quantity}\n\n";
            $content .= $description;

            $this->add(array(
                'title' => $name,
                'content' => $content,
                'category' => 'products',
                'tags' => $product->sku,
                'language' => $language
            ));

            $imported++;
        }

        return $imported;
    }

    /**
     * Bulk import
     */
    public function bulk_import($entries) {
        $imported = 0;

        foreach ($entries as $entry) {
            $result = $this->add($entry);
            if ($result) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Export all
     */
    public function export($language = null) {
        $entries = $this->get_all(array('language' => $language));

        return array_map(function($entry) {
            return array(
                'title' => $entry->title,
                'content' => $entry->content,
                'category' => $entry->category,
                'tags' => $entry->tags,
                'language' => $entry->language
            );
        }, $entries);
    }
}
