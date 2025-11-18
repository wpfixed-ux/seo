<?php
/**
 * Telegram Bot for product consultation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Telegram_Bot {

    private $token;
    private $api_url = 'https://api.telegram.org/bot';
    private $knowledge_base;
    private $openai;

    public function __construct() {
        $this->token = get_option('wcpmp_telegram_bot_token');
        $this->knowledge_base = new WCPMP_Knowledge_Base();
        $this->openai = new WCPMP_OpenAI();
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule(
            'wcpmp-telegram-webhook/?$',
            'index.php?wcpmp_telegram_webhook=1',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'wcpmp_telegram_webhook';
            return $vars;
        });

        add_action('template_redirect', array($this, 'handle_webhook'));
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook() {
        if (!get_query_var('wcpmp_telegram_webhook')) {
            return;
        }

        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        if (!$update) {
            wp_die('Invalid request', 'Error', array('response' => 400));
        }

        $this->process_update($update);

        wp_die('OK', 'Success', array('response' => 200));
    }

    /**
     * Process Telegram update
     */
    public function process_update($update) {
        if (isset($update['message'])) {
            $this->handle_message($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handle_callback($update['callback_query']);
        }
    }

    /**
     * Handle incoming message
     */
    private function handle_message($message) {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user_name = $message['from']['first_name'] ?? 'User';

        // Get or create chat session
        $chat = $this->get_or_create_chat($chat_id, $user_name);

        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->handle_command($chat, $text);
            return;
        }

        // Process message with AI
        $response = $this->generate_response($chat, $text);

        // Send response
        $this->send_message($chat_id, $response);
    }

    /**
     * Handle commands
     */
    private function handle_command($chat, $command) {
        $parts = explode(' ', $command);
        $cmd = strtolower($parts[0]);

        switch ($cmd) {
            case '/start':
                $welcome = $chat->language === 'uk'
                    ? "Привіт! Я бот-консультант магазину. Чим можу допомогти?\n\nВи можете:\n- Запитати про товари\n- Дізнатися про наявність\n- Отримати посилання на товар\n\nПросто напишіть ваше питання!"
                    : "Привет! Я бот-консультант магазина. Чем могу помочь?\n\nВы можете:\n- Спросить о товарах\n- Узнать о наличии\n- Получить ссылку на товар\n\nПросто напишите ваш вопрос!";
                $this->send_message($chat->chat_id, $welcome);
                break;

            case '/language':
                $this->send_language_selection($chat->chat_id);
                break;

            case '/search':
                $query = trim(substr($command, strlen('/search')));
                if ($query) {
                    $results = $this->search_products($query);
                    $this->send_product_results($chat->chat_id, $results, $chat->language);
                } else {
                    $msg = $chat->language === 'uk' ? 'Введіть назву товару для пошуку' : 'Введите название товара для поиска';
                    $this->send_message($chat->chat_id, $msg);
                }
                break;

            case '/help':
                $help = $chat->language === 'uk'
                    ? "Доступні команди:\n/start - Початок роботи\n/search [запит] - Пошук товару\n/language - Змінити мову\n/help - Допомога"
                    : "Доступные команды:\n/start - Начало работы\n/search [запрос] - Поиск товара\n/language - Сменить язык\n/help - Помощь";
                $this->send_message($chat->chat_id, $help);
                break;

            default:
                $msg = $chat->language === 'uk' ? 'Невідома команда. Введіть /help для допомоги.' : 'Неизвестная команда. Введите /help для справки.';
                $this->send_message($chat->chat_id, $msg);
        }
    }

    /**
     * Generate AI response
     */
    private function generate_response($chat, $message) {
        // Get relevant knowledge base content
        $context = $this->knowledge_base->search($message, $chat->language);

        // Search for relevant products
        $products = $this->search_products($message);
        if (!empty($products)) {
            $context .= "\n\nRelevant products:\n";
            foreach (array_slice($products, 0, 5) as $product) {
                $name = $chat->language === 'uk' ? $product->name_uk : ($product->name_ru ?: $product->name_uk);
                $context .= "- $name (SKU: {$product->sku}, Price: {$product->price} UAH, Stock: {$product->stock_quantity})\n";
            }
        }

        // Get conversation history
        $history = $this->get_conversation_history($chat->id);

        // Add current message
        $history[] = array('role' => 'user', 'content' => $message);

        // Generate response
        $response = $this->openai->chat_completion($history, $context, $chat->language);

        if (is_wp_error($response)) {
            return $chat->language === 'uk'
                ? 'Вибачте, сталася помилка. Спробуйте ще раз.'
                : 'Извините, произошла ошибка. Попробуйте еще раз.';
        }

        // Save conversation
        $this->save_conversation($chat->id, $message, $response);

        return $response;
    }

    /**
     * Search products
     */
    private function search_products($query) {
        global $wpdb;

        $search = '%' . $wpdb->esc_like($query) . '%';

        return $wpdb->get_results($wpdb->prepare("
            SELECT id, sku, name_uk, name_ru, price, sale_price, stock_quantity
            FROM {$wpdb->prefix}wcpmp_products
            WHERE name_uk LIKE %s OR name_ru LIKE %s OR sku LIKE %s
            ORDER BY stock_quantity DESC
            LIMIT 10
        ", $search, $search, $search));
    }

    /**
     * Send product results
     */
    private function send_product_results($chat_id, $products, $language) {
        if (empty($products)) {
            $msg = $language === 'uk' ? 'Товари не знайдено' : 'Товары не найдены';
            $this->send_message($chat_id, $msg);
            return;
        }

        $text = $language === 'uk' ? "Знайдені товари:\n\n" : "Найденные товары:\n\n";

        foreach ($products as $product) {
            $name = $language === 'uk' ? $product->name_uk : ($product->name_ru ?: $product->name_uk);
            $status = $product->stock_quantity > 0
                ? ($language === 'uk' ? 'В наявності' : 'В наличии')
                : ($language === 'uk' ? 'Немає в наявності' : 'Нет в наличии');

            $price = $product->sale_price && $product->sale_price < $product->price
                ? "~~{$product->price}~~ {$product->sale_price} грн"
                : "{$product->price} грн";

            $text .= "**{$name}**\n";
            $text .= "Артикул: {$product->sku}\n";
            $text .= "Ціна: $price\n";
            $text .= "Статус: $status\n\n";
        }

        $this->send_message($chat_id, $text, 'Markdown');
    }

    /**
     * Send language selection
     */
    private function send_language_selection($chat_id) {
        $keyboard = array(
            'inline_keyboard' => array(
                array(
                    array('text' => 'Українська', 'callback_data' => 'lang_uk'),
                    array('text' => 'Русский', 'callback_data' => 'lang_ru')
                )
            )
        );

        $this->send_message($chat_id, 'Оберіть мову / Выберите язык:', null, $keyboard);
    }

    /**
     * Handle callback query
     */
    private function handle_callback($callback) {
        $chat_id = $callback['message']['chat']['id'];
        $data = $callback['data'];

        if (strpos($data, 'lang_') === 0) {
            $language = substr($data, 5);
            $this->update_chat_language($chat_id, $language);

            $msg = $language === 'uk' ? 'Мову змінено на українську' : 'Язык изменен на русский';
            $this->send_message($chat_id, $msg);

            // Answer callback
            $this->answer_callback($callback['id']);
        }
    }

    /**
     * Get or create chat session
     */
    private function get_or_create_chat($chat_id, $user_name) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcpmp_telegram_chats';

        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE chat_id = %s",
            $chat_id
        ));

        if (!$chat) {
            $wpdb->insert($table, array(
                'chat_id' => $chat_id,
                'user_name' => $user_name,
                'language' => get_option('wcpmp_default_language', 'uk'),
                'last_message' => current_time('mysql')
            ));

            $chat = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE chat_id = %s",
                $chat_id
            ));
        } else {
            $wpdb->update($table, array(
                'last_message' => current_time('mysql')
            ), array('id' => $chat->id));
        }

        return $chat;
    }

    /**
     * Update chat language
     */
    private function update_chat_language($chat_id, $language) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'wcpmp_telegram_chats',
            array('language' => $language),
            array('chat_id' => $chat_id)
        );
    }

    /**
     * Get conversation history
     */
    private function get_conversation_history($chat_internal_id) {
        global $wpdb;

        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT context FROM {$wpdb->prefix}wcpmp_telegram_chats WHERE id = %d",
            $chat_internal_id
        ));

        if ($chat && $chat->context) {
            $history = json_decode($chat->context, true);
            // Keep only last 10 messages
            return array_slice($history, -10);
        }

        return array();
    }

    /**
     * Save conversation
     */
    private function save_conversation($chat_internal_id, $user_message, $bot_response) {
        global $wpdb;

        $history = $this->get_conversation_history($chat_internal_id);

        $history[] = array('role' => 'user', 'content' => $user_message);
        $history[] = array('role' => 'assistant', 'content' => $bot_response);

        // Keep only last 20 messages
        $history = array_slice($history, -20);

        $wpdb->update(
            $wpdb->prefix . 'wcpmp_telegram_chats',
            array('context' => json_encode($history)),
            array('id' => $chat_internal_id)
        );
    }

    /**
     * Send message via Telegram API
     */
    public function send_message($chat_id, $text, $parse_mode = null, $reply_markup = null) {
        if (empty($this->token)) {
            return false;
        }

        $data = array(
            'chat_id' => $chat_id,
            'text' => $text
        );

        if ($parse_mode) {
            $data['parse_mode'] = $parse_mode;
        }

        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup);
        }

        return $this->api_request('sendMessage', $data);
    }

    /**
     * Answer callback query
     */
    private function answer_callback($callback_id) {
        return $this->api_request('answerCallbackQuery', array(
            'callback_query_id' => $callback_id
        ));
    }

    /**
     * Make Telegram API request
     */
    private function api_request($method, $data) {
        $url = $this->api_url . $this->token . '/' . $method;

        $response = wp_remote_post($url, array(
            'body' => $data,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Set webhook
     */
    public function set_webhook($url = null) {
        if (!$url) {
            $url = home_url('/wcpmp-telegram-webhook/');
        }

        return $this->api_request('setWebhook', array('url' => $url));
    }

    /**
     * Delete webhook
     */
    public function delete_webhook() {
        return $this->api_request('deleteWebhook', array());
    }

    /**
     * Get webhook info
     */
    public function get_webhook_info() {
        return $this->api_request('getWebhookInfo', array());
    }
}
