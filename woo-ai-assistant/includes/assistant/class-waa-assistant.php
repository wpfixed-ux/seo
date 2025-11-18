<?php
/**
 * AI Sales Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Assistant {

    private static $instance = null;
    private $vector_db;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->vector_db = WAA_Vector_DB::get_instance();
    }

    /**
     * Process user query and generate response
     */
    public function query($user_message, $session_id = '', $language = 'ru') {
        $ai_provider = WAA_Core::get_ai_provider();

        // Get embedding for user query
        $query_embedding = $ai_provider->get_embedding($user_message);

        if (!$query_embedding['success']) {
            return array(
                'success' => false,
                'error' => $query_embedding['error']
            );
        }

        // Search for relevant context
        $context_limit = get_option('waa_context_limit', 5);
        $results = $this->vector_db->search(
            $query_embedding['embedding'],
            $context_limit,
            $language
        );

        // Build context for AI
        $context = $this->build_context($results, $language);

        // Get conversation history
        $history = $this->get_conversation_history($session_id);

        // Build messages
        $messages = $this->build_messages($user_message, $context, $history, $language);

        // Generate response
        $response = $ai_provider->chat($messages);

        if (!$response['success']) {
            return array(
                'success' => false,
                'error' => $response['error']
            );
        }

        // Save to history
        $this->save_to_history($session_id, $user_message, $response['message'], $results, $language);

        // Extract product links from context
        $products = $this->extract_products($results);

        return array(
            'success' => true,
            'message' => $response['message'],
            'products' => $products,
            'usage' => isset($response['usage']) ? $response['usage'] : null
        );
    }

    /**
     * Build context string from search results
     */
    private function build_context($results, $language) {
        if (empty($results)) {
            return $language === 'uk'
                ? 'Релевантних товарів не знайдено.'
                : 'Релевантные товары не найдены.';
        }

        $context_parts = array();

        foreach ($results as $index => $result) {
            $meta = $result['metadata'];
            $num = $index + 1;

            if ($result['object_type'] === 'product') {
                $part = "[$num] Товар: {$meta['title']}\n";
                $part .= "Цена: " . wc_price($meta['price']) . "\n";

                if (!empty($meta['sale_price'])) {
                    $part .= "Скидка! Было: " . wc_price($meta['regular_price']) . "\n";
                }

                $part .= "Наличие: {$meta['stock_text']}\n";

                if (!empty($meta['categories'])) {
                    $part .= "Категория: " . implode(', ', $meta['categories']) . "\n";
                }

                $part .= "Ссылка: {$meta['url']}";
            } else {
                $part = "[$num] Статья: {$meta['title']}\n";

                if (!empty($meta['excerpt'])) {
                    $part .= "Описание: {$meta['excerpt']}\n";
                }

                $part .= "Ссылка: {$meta['url']}";
            }

            $context_parts[] = $part;
        }

        return implode("\n\n", $context_parts);
    }

    /**
     * Build messages array for AI
     */
    private function build_messages($user_message, $context, $history, $language) {
        $system_prompt = get_option('waa_system_prompt');

        // Add language instruction
        if ($language === 'uk') {
            $system_prompt .= "\n\nВАЖЛИВО: Відповідай українською мовою.";
        } else {
            $system_prompt .= "\n\nВАЖНО: Отвечай на русском языке.";
        }

        // Add context instruction
        $system_prompt .= "\n\nИспользуй следующую информацию о товарах для ответа на вопросы покупателя. Всегда указывай ссылки на товары.\n\nДоступные товары и информация:\n" . $context;

        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            )
        );

        // Add conversation history
        foreach ($history as $entry) {
            $messages[] = array(
                'role' => 'user',
                'content' => $entry->user_message
            );
            $messages[] = array(
                'role' => 'assistant',
                'content' => $entry->assistant_message
            );
        }

        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => $user_message
        );

        return $messages;
    }

    /**
     * Get conversation history for session
     */
    private function get_conversation_history($session_id, $limit = 5) {
        if (empty($session_id)) {
            return array();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'waa_chat_history';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT user_message, assistant_message FROM $table WHERE session_id = %s ORDER BY created_at DESC LIMIT %d",
            $session_id,
            $limit
        ));
    }

    /**
     * Save conversation to history
     */
    private function save_to_history($session_id, $user_message, $assistant_message, $results, $language) {
        if (empty($session_id)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'waa_chat_history';

        $context_ids = array();
        foreach ($results as $result) {
            $context_ids[] = $result['object_type'] . ':' . $result['object_id'];
        }

        $wpdb->insert(
            $table,
            array(
                'session_id' => $session_id,
                'user_message' => $user_message,
                'assistant_message' => $assistant_message,
                'context_ids' => json_encode($context_ids),
                'language' => $language,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Extract product information from results
     */
    private function extract_products($results) {
        $products = array();

        foreach ($results as $result) {
            if ($result['object_type'] !== 'product') {
                continue;
            }

            $meta = $result['metadata'];
            $products[] = array(
                'id' => $result['object_id'],
                'title' => $meta['title'],
                'price' => $meta['price'],
                'url' => $meta['url'],
                'image' => isset($meta['image']) ? $meta['image'] : '',
                'stock_status' => $meta['stock_status'],
                'similarity' => round($result['similarity'] * 100, 1)
            );
        }

        return $products;
    }

    /**
     * Detect language from text
     */
    public function detect_language($text) {
        // Simple detection based on Ukrainian-specific characters
        $ukrainian_chars = array('і', 'ї', 'є', 'ґ', 'І', 'Ї', 'Є', 'Ґ');

        foreach ($ukrainian_chars as $char) {
            if (mb_strpos($text, $char) !== false) {
                return 'uk';
            }
        }

        return 'ru';
    }

    /**
     * Get welcome message based on language
     */
    public function get_welcome_message($language = 'ru') {
        if ($language === 'uk') {
            return get_option('waa_welcome_message_uk',
                'Вітаю! Я AI-консультант магазину. Допоможу підібрати товар, розкажу про наявність та ціни. Чим можу допомогти?'
            );
        }

        return get_option('waa_welcome_message_ru',
            'Здравствуйте! Я AI-консультант магазина. Помогу подобрать товар, расскажу о наличии и ценах. Чем могу помочь?'
        );
    }

    /**
     * Clean old chat history
     */
    public function cleanup_old_history($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'waa_chat_history';

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
