<?php
/**
 * AJAX Handler
 *
 * @package WC_AI_Translator
 */

class WCAT_Ajax_Handler {

    /**
     * Plugin name
     */
    private $plugin_name;

    /**
     * Plugin version
     */
    private $version;

    /**
     * Constructor
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Verify AJAX nonce
     */
    private function verify_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wcat_admin_nonce')) {
            wp_send_json_error(array('message' => __('Invalid security token.', 'wc-ai-translator')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wc-ai-translator')));
        }
    }

    /**
     * Scan content
     */
    public function scan_content() {
        $this->verify_nonce();

        $scanner = new WCAT_Content_Scanner();

        $content_types = isset($_POST['content_types']) ? array_map('sanitize_text_field', $_POST['content_types']) : array('post', 'page', 'product');
        $taxonomies = isset($_POST['taxonomies']) ? array_map('sanitize_text_field', $_POST['taxonomies']) : array('category', 'post_tag', 'product_cat', 'product_tag');

        $results = $scanner->scan_content(array(
            'content_types' => $content_types,
            'taxonomies' => $taxonomies,
        ));

        wp_send_json_success($results);
    }

    /**
     * Batch translate
     */
    public function batch_translate() {
        $this->verify_nonce();

        if (!isset($_POST['items']) || !isset($_POST['target_languages'])) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'wc-ai-translator')));
        }

        $items = json_decode(stripslashes($_POST['items']), true);
        $target_languages = array_map('sanitize_text_field', $_POST['target_languages']);

        $batch_processor = new WCAT_Batch_Processor();
        $result = $batch_processor->add_batch_to_queue($items, $target_languages);

        wp_send_json_success($result);
    }

    /**
     * Translate single item
     */
    public function translate_single() {
        $this->verify_nonce();

        if (!isset($_POST['content_id']) || !isset($_POST['target_lang'])) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'wc-ai-translator')));
        }

        $content_id = absint($_POST['content_id']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : 'post';

        $batch_processor = new WCAT_Batch_Processor();
        $result = $batch_processor->translate_immediately($content_id, $content_type, $target_lang);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Preview translation
     */
    public function preview_translation() {
        $this->verify_nonce();

        if (!isset($_POST['text']) || !isset($_POST['source_lang']) || !isset($_POST['target_lang'])) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'wc-ai-translator')));
        }

        $text = wp_kses_post($_POST['text']);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        $content_translator = new WCAT_Content_Translator();
        $result = $content_translator->preview_translation($text, $source_lang, $target_lang);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get logs
     */
    public function get_logs() {
        $this->verify_nonce();

        $log = new WCAT_Translation_Log();

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
        $offset = ($page - 1) * $per_page;

        $logs = $log->get_logs(array(
            'limit' => $per_page,
            'offset' => $offset,
        ));

        $total = $log->get_log_count();
        $statistics = $log->get_statistics();

        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total,
            'statistics' => $statistics,
        ));
    }

    /**
     * Get queue status
     */
    public function get_queue_status() {
        $this->verify_nonce();

        $queue_manager = new WCAT_Queue_Manager();
        $progress = $queue_manager->get_progress();

        wp_send_json_success($progress);
    }

    /**
     * Cancel translation
     */
    public function cancel_translation() {
        $this->verify_nonce();

        if (!isset($_POST['queue_id'])) {
            wp_send_json_error(array('message' => __('Missing queue ID.', 'wc-ai-translator')));
        }

        $queue_id = absint($_POST['queue_id']);
        $queue = new WCAT_Translation_Queue();

        if ($queue->delete_item($queue_id)) {
            wp_send_json_success(array('message' => __('Translation cancelled.', 'wc-ai-translator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to cancel translation.', 'wc-ai-translator')));
        }
    }

    /**
     * Clear logs
     */
    public function clear_logs() {
        $this->verify_nonce();

        $log = new WCAT_Translation_Log();

        if ($log->clear_all_logs()) {
            wp_send_json_success(array('message' => __('Logs cleared successfully.', 'wc-ai-translator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear logs.', 'wc-ai-translator')));
        }
    }
}
