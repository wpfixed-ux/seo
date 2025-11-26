<?php
/**
 * Vector Database for storing and searching embeddings
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Vector_DB {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'waa_vectors';
    }

    /**
     * Store embedding for an object
     */
    public function store_embedding($object_id, $object_type, $embedding, $metadata = array(), $language = 'ru') {
        global $wpdb;

        // Create content hash for change detection
        $content_hash = md5(json_encode($metadata));

        // Check if embedding already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE object_id = %d AND object_type = %s AND language = %s",
            $object_id, $object_type, $language
        ));

        $embedding_json = json_encode($embedding);
        $metadata_json = json_encode($metadata);

        if ($existing) {
            // Update existing
            return $wpdb->update(
                $this->table_name,
                array(
                    'embedding' => $embedding_json,
                    'metadata' => $metadata_json,
                    'content_hash' => $content_hash,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new
            return $wpdb->insert(
                $this->table_name,
                array(
                    'object_id' => $object_id,
                    'object_type' => $object_type,
                    'embedding' => $embedding_json,
                    'metadata' => $metadata_json,
                    'content_hash' => $content_hash,
                    'language' => $language,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Search for similar vectors using cosine similarity
     */
    public function search($query_embedding, $limit = 5, $language = null, $object_type = null) {
        global $wpdb;

        // Get all vectors
        $where_clauses = array('1=1');
        $where_values = array();

        if ($language) {
            $where_clauses[] = 'language = %s';
            $where_values[] = $language;
        }

        if ($object_type) {
            $where_clauses[] = 'object_type = %s';
            $where_values[] = $object_type;
        }

        $where_sql = implode(' AND ', $where_clauses);

        if (!empty($where_values)) {
            $sql = $wpdb->prepare(
                "SELECT id, object_id, object_type, embedding, metadata, language FROM {$this->table_name} WHERE $where_sql",
                ...$where_values
            );
        } else {
            $sql = "SELECT id, object_id, object_type, embedding, metadata, language FROM {$this->table_name} WHERE $where_sql";
        }

        $results = $wpdb->get_results($sql);

        if (empty($results)) {
            return array();
        }

        // Calculate similarities
        $scored_results = array();
        foreach ($results as $row) {
            $stored_embedding = json_decode($row->embedding, true);
            $similarity = $this->cosine_similarity($query_embedding, $stored_embedding);

            $scored_results[] = array(
                'id' => $row->id,
                'object_id' => $row->object_id,
                'object_type' => $row->object_type,
                'metadata' => json_decode($row->metadata, true),
                'language' => $row->language,
                'similarity' => $similarity
            );
        }

        // Sort by similarity (descending)
        usort($scored_results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return top results
        return array_slice($scored_results, 0, $limit);
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosine_similarity($vec1, $vec2) {
        if (count($vec1) !== count($vec2)) {
            return 0;
        }

        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dot_product / ($norm1 * $norm2);
    }

    /**
     * Delete embedding for an object
     */
    public function delete_embedding($object_id, $object_type, $language = null) {
        global $wpdb;

        $where = array(
            'object_id' => $object_id,
            'object_type' => $object_type
        );
        $where_format = array('%d', '%s');

        if ($language) {
            $where['language'] = $language;
            $where_format[] = '%s';
        }

        return $wpdb->delete($this->table_name, $where, $where_format);
    }

    /**
     * Check if object needs reindexing
     */
    public function needs_reindex($object_id, $object_type, $content_hash, $language = 'ru') {
        global $wpdb;

        $existing_hash = $wpdb->get_var($wpdb->prepare(
            "SELECT content_hash FROM {$this->table_name} WHERE object_id = %d AND object_type = %s AND language = %s",
            $object_id, $object_type, $language
        ));

        return $existing_hash !== $content_hash;
    }

    /**
     * Get statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"),
            'products' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE object_type = 'product'"),
            'posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE object_type = 'post'"),
            'by_language' => array()
        );

        $languages = $wpdb->get_results(
            "SELECT language, COUNT(*) as count FROM {$this->table_name} GROUP BY language"
        );

        foreach ($languages as $lang) {
            $stats['by_language'][$lang->language] = $lang->count;
        }

        return $stats;
    }

    /**
     * Clear all embeddings
     */
    public function clear_all() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Get embedding by object
     */
    public function get_embedding($object_id, $object_type, $language = 'ru') {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE object_id = %d AND object_type = %s AND language = %s",
            $object_id, $object_type, $language
        ));

        if ($result) {
            $result->embedding = json_decode($result->embedding, true);
            $result->metadata = json_decode($result->metadata, true);
        }

        return $result;
    }
}
