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
            error_log('WAA Embedding Error: ' . $query_embedding['error']);
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
            error_log('WAA Chat Completion Error: ' . $response['error']);
            return array(
                'success' => false,
                'error' => $response['error']
            );
        }

        // Parse JSON response to extract message and relevant products
        $parsed = $this->parse_ai_response($response['message'], $results);
        $final_message = $parsed['message'];
        $relevant_indices = $parsed['relevant_products'];

        // Save to history and get message ID
        $message_id = $this->save_to_history($session_id, $user_message, $final_message, $results, $language);

        // Extract only relevant products
        $products = $this->extract_products($results, $relevant_indices);

        return array(
            'success' => true,
            'message' => $final_message,
            'message_id' => $message_id,
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

        // Add context instruction with relevance filtering
        $relevance_instruction = $language === 'uk'
            ? "КРИТИЧНО ВАЖЛИВО: Уважно аналізуй релевантність кожного товару до запиту покупця. " .
              "НЕ рекомендуй товари, які лише схожі за написанням, але не підходять за змістом. " .
              "Наприклад, якщо питають про ліки від бронхіту - не рекомендуй бронзові статуетки. " .
              "Якщо питають про здоров'я - рекомендуй лише медичні/оздоровчі товари. " .
              "Рекомендуй ТІЛЬКИ ті товари, які логічно відповідають на питання покупця."
            : "КРИТИЧЕСКИ ВАЖНО: Внимательно анализируй релевантность каждого товара к запросу покупателя. " .
              "НЕ рекомендуй товары, которые лишь похожи по написанию, но не подходят по смыслу. " .
              "Например, если спрашивают о лекарствах от бронхита - не рекомендуй бронзовые статуэтки. " .
              "Если спрашивают о здоровье - рекомендуй только медицинские/оздоровительные товары. " .
              "Рекомендуй ТОЛЬКО те товары, которые логически отвечают на вопрос покупателя.";

        $system_prompt .= "\n\n" . $relevance_instruction;

        // Add JSON format instruction for filtering
        $json_instruction = $language === 'uk'
            ? "\n\nФОРМАТ ВІДПОВІДІ: Відповідай у форматі JSON:\n" .
              "{\"message\": \"твоя відповідь покупцю\", \"relevant_products\": [1, 2, 3]}\n" .
              "де relevant_products - масив номерів товарів (в квадратних дужках біля товарів), які ДІЙСНО підходять до запиту.\n" .
              "Якщо жоден товар не підходить - поверни порожній масив []."
            : "\n\nФОРМАТ ОТВЕТА: Отвечай в формате JSON:\n" .
              "{\"message\": \"твой ответ покупателю\", \"relevant_products\": [1, 2, 3]}\n" .
              "где relevant_products - массив номеров товаров (в квадратных скобках у товаров), которые ДЕЙСТВИТЕЛЬНО подходят к запросу.\n" .
              "Если ни один товар не подходит - верни пустой массив [].";

        $system_prompt .= $json_instruction;
        $system_prompt .= "\n\nИспользуй следующую информацию о товарах для ответа на вопросы покупателя. Всегда указывай ссылки на товары. Если среди найденных товаров нет подходящих - честно скажи об этом.\n\nДоступные товары и информация:\n" . $context;

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
     * @return int|null Message ID
     */
    private function save_to_history($session_id, $user_message, $assistant_message, $results, $language) {
        if (empty($session_id)) {
            return null;
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

        return $wpdb->insert_id;
    }

    /**
     * Parse AI response to extract message and relevant products
     */
    private function parse_ai_response($response, $results) {
        // Try to parse JSON from response
        $json_data = null;

        // Try direct JSON parse
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['message'])) {
            $json_data = $decoded;
        }

        // Try to extract JSON from markdown code block
        if (!$json_data && preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded && isset($decoded['message'])) {
                $json_data = $decoded;
            }
        }

        // Try to find JSON object in response
        if (!$json_data && preg_match('/\{[^{}]*"message"[^{}]*\}/', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded && isset($decoded['message'])) {
                $json_data = $decoded;
            }
        }

        // Return parsed data or fallback
        if ($json_data) {
            $relevant = isset($json_data['relevant_products']) ? $json_data['relevant_products'] : array();
            // Convert to 0-based indices (AI returns 1-based)
            $relevant_indices = array_map(function($n) { return $n - 1; }, $relevant);

            return array(
                'message' => $json_data['message'],
                'relevant_products' => $relevant_indices
            );
        }

        // Fallback: return original response and all products
        return array(
            'message' => $response,
            'relevant_products' => array_keys($results)
        );
    }

    /**
     * Extract product information from results
     */
    private function extract_products($results, $relevant_indices = null) {
        $products = array();

        foreach ($results as $index => $result) {
            if ($result['object_type'] !== 'product') {
                continue;
            }

            // Skip if not in relevant indices (when filtering is enabled)
            if ($relevant_indices !== null && !in_array($index, $relevant_indices)) {
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
