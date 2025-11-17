<?php
/**
 * Batch Processor
 *
 * @package WC_AI_Translator
 */

class WCAT_Batch_Processor {

    /**
     * Queue instance
     */
    private $queue;

    /**
     * Content translator
     */
    private $content_translator;

    /**
     * WooCommerce translator
     */
    private $woocommerce_translator;

    /**
     * SEO translator
     */
    private $seo_translator;

    /**
     * Translation log
     */
    private $log;

    /**
     * Constructor
     */
    public function __construct() {
        $this->queue = new WCAT_Translation_Queue();
        $this->content_translator = new WCAT_Content_Translator();
        $this->woocommerce_translator = new WCAT_WooCommerce_Translator();
        $this->seo_translator = new WCAT_SEO_Translator();
        $this->log = new WCAT_Translation_Log();
    }

    /**
     * Process translation queue
     */
    public function process_queue() {
        $settings = get_option('wcat_settings');
        $batch_size = isset($settings['batch_size']) ? $settings['batch_size'] : 10;

        // Get pending items
        $items = $this->queue->get_pending_items($batch_size);

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $this->process_queue_item($item);

            // Small delay between items to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }
    }

    /**
     * Process single queue item
     *
     * @param array $item Queue item
     * @return bool Success
     */
    private function process_queue_item($item) {
        $settings = get_option('wcat_settings');
        $max_retries = isset($settings['max_retries']) ? $settings['max_retries'] : 3;

        // Check if max attempts reached
        if ($item['attempts'] >= $max_retries) {
            $this->queue->update_status($item['id'], 'failed', 'Maximum retry attempts reached');
            return false;
        }

        // Update status to processing
        $this->queue->update_status($item['id'], 'processing');

        // Determine content type and translate
        $result = $this->translate_content($item);

        if ($result['success']) {
            $this->queue->update_status($item['id'], 'completed');

            // Translate SEO meta if enabled
            if (isset($settings['translate_seo']) && $settings['translate_seo'] && isset($result['post_id'])) {
                $this->seo_translator->translate_seo_meta(
                    $item['content_id'],
                    $result['post_id'],
                    $item['target_lang']
                );
            }

            return true;
        } else {
            $error_message = isset($result['error']) ? $result['error'] : 'Unknown error';
            $this->queue->update_status($item['id'], 'pending', $error_message);
            return false;
        }
    }

    /**
     * Translate content based on type
     *
     * @param array $item Queue item
     * @return array Translation result
     */
    private function translate_content($item) {
        $content_type = $item['content_type'];
        $content_id = $item['content_id'];
        $target_lang = $item['target_lang'];

        // Handle posts and pages
        if (in_array($content_type, array('post', 'page', 'product'))) {
            if ($content_type === 'product') {
                return $this->woocommerce_translator->translate_product($content_id, $target_lang);
            } else {
                return $this->content_translator->translate_post($content_id, $target_lang);
            }
        }

        // Handle taxonomies
        if (strpos($content_type, 'product_') === 0 || in_array($content_type, array('category', 'post_tag'))) {
            if ($content_type === 'product_cat') {
                return $this->woocommerce_translator->translate_product_category($content_id, $target_lang);
            } elseif ($content_type === 'product_tag') {
                return $this->woocommerce_translator->translate_product_tag($content_id, $target_lang);
            } elseif (strpos($content_type, 'pa_') === 0) {
                // Product attribute
                return $this->woocommerce_translator->translate_product_attribute($content_id, $content_type, $target_lang);
            } else {
                return $this->content_translator->translate_term($content_id, $content_type, $target_lang);
            }
        }

        return array(
            'success' => false,
            'error' => sprintf(__('Unsupported content type: %s', 'wc-ai-translator'), $content_type)
        );
    }

    /**
     * Add batch translation to queue
     *
     * @param array $items Array of items to translate
     * @param array $target_languages Target languages
     * @return array Result with queue IDs
     */
    public function add_batch_to_queue($items, $target_languages) {
        $polylang = new WCAT_Polylang_Translator();
        $queue_items = array();

        foreach ($items as $item) {
            $source_lang = isset($item['language']) ? $item['language'] : $polylang->get_default_language();

            foreach ($target_languages as $target_lang) {
                if ($source_lang === $target_lang) {
                    continue;
                }

                $queue_items[] = array(
                    'content_id' => $item['id'],
                    'content_type' => $item['type'],
                    'source_lang' => $source_lang,
                    'target_lang' => $target_lang,
                    'status' => 'pending',
                    'priority' => isset($item['priority']) ? $item['priority'] : 5,
                );
            }
        }

        $queue_ids = $this->queue->batch_add_to_queue($queue_items);

        return array(
            'success' => true,
            'queue_ids' => $queue_ids,
            'total_items' => count($queue_ids),
            'message' => sprintf(
                __('%d items added to translation queue.', 'wc-ai-translator'),
                count($queue_ids)
            )
        );
    }

    /**
     * Process single translation immediately
     *
     * @param int $content_id Content ID
     * @param string $content_type Content type
     * @param string $target_lang Target language
     * @return array Translation result
     */
    public function translate_immediately($content_id, $content_type, $target_lang) {
        $polylang = new WCAT_Polylang_Translator();
        $source_lang = $polylang->get_default_language();

        if ($content_type === 'post' || $content_type === 'page') {
            $lang = $polylang->get_post_language($content_id);
            if ($lang) {
                $source_lang = $lang;
            }
        }

        $item = array(
            'id' => 0,
            'content_id' => $content_id,
            'content_type' => $content_type,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'attempts' => 0,
        );

        return $this->translate_content($item);
    }

    /**
     * Cancel all pending translations
     *
     * @return array Result
     */
    public function cancel_pending_translations() {
        $deleted = $this->queue->clear_completed();

        return array(
            'success' => true,
            'deleted' => $deleted,
            'message' => sprintf(__('%d items removed from queue.', 'wc-ai-translator'), $deleted)
        );
    }

    /**
     * Retry failed translations
     *
     * @return array Result
     */
    public function retry_failed_translations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcat_translation_queue';

        $updated = $wpdb->query(
            "UPDATE {$table_name}
            SET status = 'pending', attempts = 0, error_message = ''
            WHERE status = 'failed'"
        );

        return array(
            'success' => true,
            'updated' => $updated,
            'message' => sprintf(__('%d failed translations reset for retry.', 'wc-ai-translator'), $updated)
        );
    }

    /**
     * Get queue status
     *
     * @return array Queue statistics
     */
    public function get_queue_status() {
        return $this->queue->get_statistics();
    }
}
