<?php
/**
 * Main Chatbot class for Craft Catalog
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/chatbot
 */

class SAP_Chatbot {

    /**
     * Claude AI instance
     *
     * @var SAP_Claude_AI
     */
    private $claude_ai;

    /**
     * Current session
     *
     * @var array
     */
    private $session;

    /**
     * Chatbot settings
     *
     * @var array
     */
    private $settings;

    /**
     * Available intents
     *
     * @var array
     */
    private $intents = array(
        'greeting',
        'search_products',
        'view_categories',
        'view_product',
        'contact_producer',
        'register_producer',
        'add_product',
        'update_product',
        'update_price',
        'update_stock',
        'import_excel',
        'export_pdf',
        'my_products',
        'my_profile',
        'help',
        'unknown'
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('sap_chatbot_settings', array());

        // Load Claude AI if available
        if (class_exists('SAP_Claude_AI')) {
            $this->claude_ai = new SAP_Claude_AI();
        }
    }

    /**
     * Process incoming message
     *
     * @param string $message User message
     * @param string $session_id Session identifier
     * @param string $platform Platform (web/telegram)
     * @param array $metadata Additional metadata
     * @return array Response with text and actions
     */
    public function process_message($message, $session_id, $platform = 'web', $metadata = array()) {
        // Get or create session
        $this->session = $this->get_or_create_session($session_id, $platform, $metadata);

        // Save user message
        $this->save_message($message, 'user');

        // Determine intent and entities
        $analysis = $this->analyze_message($message);

        // Update session context
        $this->update_session_context($analysis);

        // Generate response based on intent
        $response = $this->generate_response($analysis);

        // Save assistant response
        $this->save_message($response['text'], 'assistant', $analysis['intent']);

        // Update session
        $this->update_session();

        return $response;
    }

    /**
     * Get or create chat session
     *
     * @param string $session_id Session identifier
     * @param string $platform Platform
     * @param array $metadata Additional metadata
     * @return array Session data
     */
    private function get_or_create_session($session_id, $platform, $metadata) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_chat_sessions';

        // Try to find existing session
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s AND platform = %s",
            $session_id,
            $platform
        ), ARRAY_A);

        if ($session) {
            // Check if session expired
            $timeout = isset($this->settings['session_timeout_minutes'])
                ? intval($this->settings['session_timeout_minutes'])
                : 30;

            $last_message = strtotime($session['last_message_at']);
            if ((time() - $last_message) > ($timeout * 60)) {
                // Reset context for expired session
                $session['context'] = json_encode(array());
                $session['current_intent'] = null;
            }

            $session['context'] = json_decode($session['context'], true) ?: array();
            return $session;
        }

        // Create new session
        $now = current_time('mysql');
        $user_id = get_current_user_id();

        // Check if user is a producer
        $producer_id = null;
        if ($user_id) {
            $producer_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sap_producers WHERE user_id = %d AND status = 'active'",
                $user_id
            ));
        }

        // Check telegram chat id
        $telegram_chat_id = isset($metadata['telegram_chat_id']) ? $metadata['telegram_chat_id'] : null;
        if ($telegram_chat_id && !$producer_id) {
            $producer_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sap_producers WHERE telegram_id = %s AND status = 'active'",
                $telegram_chat_id
            ));
        }

        $user_type = $producer_id ? 'producer' : ($user_id ? 'buyer' : 'guest');

        $wpdb->insert($table, array(
            'session_id' => $session_id,
            'user_id' => $user_id ?: null,
            'producer_id' => $producer_id,
            'telegram_chat_id' => $telegram_chat_id,
            'platform' => $platform,
            'user_type' => $user_type,
            'current_intent' => null,
            'context' => json_encode(array()),
            'last_message_at' => $now,
            'messages_count' => 0,
            'created_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ));

        return array(
            'id' => $wpdb->insert_id,
            'session_id' => $session_id,
            'user_id' => $user_id ?: null,
            'producer_id' => $producer_id,
            'telegram_chat_id' => $telegram_chat_id,
            'platform' => $platform,
            'user_type' => $user_type,
            'current_intent' => null,
            'context' => array(),
            'last_message_at' => $now,
            'messages_count' => 0,
        );
    }

    /**
     * Analyze user message to determine intent and extract entities
     *
     * @param string $message User message
     * @return array Analysis result with intent and entities
     */
    private function analyze_message($message) {
        $message_lower = mb_strtolower($message, 'UTF-8');

        // Check for ongoing wizard/conversation flow
        $context = $this->session['context'];
        if (!empty($context['wizard'])) {
            return array(
                'intent' => $context['wizard'],
                'entities' => array('input' => $message),
                'confidence' => 1.0,
            );
        }

        // Simple keyword-based intent detection (fallback)
        $intent = $this->detect_intent_keywords($message_lower);
        $entities = $this->extract_entities($message);

        // Use Claude AI for complex messages
        if ($intent === 'unknown' && $this->claude_ai) {
            $ai_analysis = $this->analyze_with_claude($message);
            if ($ai_analysis) {
                $intent = $ai_analysis['intent'];
                $entities = array_merge($entities, $ai_analysis['entities']);
            }
        }

        return array(
            'intent' => $intent,
            'entities' => $entities,
            'confidence' => ($intent !== 'unknown') ? 0.8 : 0.3,
        );
    }

    /**
     * Detect intent using keywords
     *
     * @param string $message Lowercase message
     * @return string Intent name
     */
    private function detect_intent_keywords($message) {
        // Greeting patterns
        if (preg_match('/^(привет|здравствуй|добрый|хай|hello|hi|hey)/u', $message)) {
            return 'greeting';
        }

        // Search patterns
        if (preg_match('/(найти|искать|ищу|поиск|где купить|хочу|нужен|нужна|нужно)/u', $message)) {
            return 'search_products';
        }

        // Category patterns
        if (preg_match('/(категории|разделы|что есть|каталог|список товаров)/u', $message)) {
            return 'view_categories';
        }

        // Registration patterns
        if (preg_match('/(регистрац|зарегистрир|стать продавц|добавить магазин|я производитель)/u', $message)) {
            return 'register_producer';
        }

        // Product management patterns (for producers)
        if ($this->session['user_type'] === 'producer') {
            if (preg_match('/(добавить товар|новый товар|загрузить товар)/u', $message)) {
                return 'add_product';
            }
            if (preg_match('/(изменить цену|обновить цену|новая цена)/u', $message)) {
                return 'update_price';
            }
            if (preg_match('/(наличие|в наличии|нет в наличии|закончился)/u', $message)) {
                return 'update_stock';
            }
            if (preg_match('/(мои товары|мой каталог|мои продукты)/u', $message)) {
                return 'my_products';
            }
            if (preg_match('/(мой профиль|моя информация|обо мне)/u', $message)) {
                return 'my_profile';
            }
            if (preg_match('/(импорт|загрузить excel|загрузить файл)/u', $message)) {
                return 'import_excel';
            }
        }

        // Export PDF
        if (preg_match('/(скачать|экспорт|pdf|каталог в pdf)/u', $message)) {
            return 'export_pdf';
        }

        // Contact producer
        if (preg_match('/(связаться|контакт|позвонить|написать продавцу)/u', $message)) {
            return 'contact_producer';
        }

        // Help
        if (preg_match('/(помощь|помоги|help|что умеешь|команды)/u', $message)) {
            return 'help';
        }

        return 'unknown';
    }

    /**
     * Extract entities from message
     *
     * @param string $message User message
     * @return array Extracted entities
     */
    private function extract_entities($message) {
        $entities = array();

        // Extract product names (in quotes or after keywords)
        if (preg_match('/"([^"]+)"/u', $message, $matches)) {
            $entities['product_name'] = $matches[1];
        }

        // Extract categories
        $categories = array(
            'сыр' => 'cheese',
            'молоко' => 'milk',
            'творог' => 'cottage-cheese',
            'мёд' => 'honey',
            'мед' => 'honey',
            'колбас' => 'sausages',
            'мясо' => 'meat',
            'хлеб' => 'bakery',
            'конфет' => 'candies',
            'шоколад' => 'chocolate',
        );

        foreach ($categories as $keyword => $slug) {
            if (mb_stripos($message, $keyword) !== false) {
                $entities['category'] = $slug;
                break;
            }
        }

        // Extract price
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:грн|грив|uah|₴)/ui', $message, $matches)) {
            $entities['price'] = floatval(str_replace(',', '.', $matches[1]));
        }

        // Extract quantity
        if (preg_match('/(\d+)\s*(?:шт|кг|г|л|мл)/ui', $message, $matches)) {
            $entities['quantity'] = intval($matches[1]);
        }

        // Extract phone
        if (preg_match('/(?:\+38)?[\s\-]?0\d{2}[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/u', $message, $matches)) {
            $entities['phone'] = preg_replace('/[^\d+]/', '', $matches[0]);
        }

        return $entities;
    }

    /**
     * Analyze message using Claude AI
     *
     * @param string $message User message
     * @return array|null Analysis result
     */
    private function analyze_with_claude($message) {
        if (!$this->claude_ai) {
            return null;
        }

        $system_prompt = "Ты анализатор намерений для чат-бота каталога крафтовых товаров.
        Определи намерение пользователя и извлеки сущности из сообщения.

        Возможные намерения:
        - search_products: поиск товаров
        - view_categories: просмотр категорий
        - register_producer: регистрация производителя
        - contact_producer: связаться с продавцом
        - export_pdf: скачать каталог
        - help: помощь
        - unknown: неизвестно

        Ответь в формате JSON:
        {\"intent\": \"название_намерения\", \"entities\": {\"product\": \"...\", \"category\": \"...\"}}";

        $response = $this->claude_ai->send_message($message, $system_prompt, array(
            'max_tokens' => 200,
            'temperature' => 0.3,
        ));

        if ($response && isset($response['content'])) {
            $json = json_decode($response['content'], true);
            if ($json && isset($json['intent'])) {
                return $json;
            }
        }

        return null;
    }

    /**
     * Update session context based on analysis
     *
     * @param array $analysis Message analysis
     */
    private function update_session_context($analysis) {
        $context = $this->session['context'];

        // Store last intent
        $context['last_intent'] = $analysis['intent'];

        // Merge entities
        if (!isset($context['entities'])) {
            $context['entities'] = array();
        }
        $context['entities'] = array_merge($context['entities'], $analysis['entities']);

        // Store search history
        if ($analysis['intent'] === 'search_products' && isset($analysis['entities']['category'])) {
            if (!isset($context['search_history'])) {
                $context['search_history'] = array();
            }
            $context['search_history'][] = $analysis['entities']['category'];
        }

        $this->session['context'] = $context;
        $this->session['current_intent'] = $analysis['intent'];
    }

    /**
     * Generate response based on intent
     *
     * @param array $analysis Message analysis
     * @return array Response with text and optional actions
     */
    private function generate_response($analysis) {
        $intent = $analysis['intent'];
        $entities = $analysis['entities'];

        switch ($intent) {
            case 'greeting':
                return $this->response_greeting();

            case 'search_products':
                return $this->response_search_products($entities);

            case 'view_categories':
                return $this->response_view_categories();

            case 'register_producer':
                return $this->response_register_producer($entities);

            case 'add_product':
                return $this->response_add_product($entities);

            case 'update_price':
                return $this->response_update_price($entities);

            case 'update_stock':
                return $this->response_update_stock($entities);

            case 'my_products':
                return $this->response_my_products();

            case 'import_excel':
                return $this->response_import_excel();

            case 'export_pdf':
                return $this->response_export_pdf($entities);

            case 'contact_producer':
                return $this->response_contact_producer($entities);

            case 'help':
                return $this->response_help();

            default:
                return $this->response_unknown();
        }
    }

    /**
     * Greeting response
     */
    private function response_greeting() {
        $welcome = isset($this->settings['welcome_message'])
            ? $this->settings['welcome_message']
            : 'Привет! Я помогу найти крафтовые товары. Что вас интересует?';

        $text = $welcome . "\n\n";

        if ($this->session['user_type'] === 'producer') {
            $text .= "Как производитель, вы можете:\n";
            $text .= "- Добавить товар\n";
            $text .= "- Обновить цены\n";
            $text .= "- Посмотреть мои товары\n";
            $text .= "- Импортировать из Excel\n";
        } else {
            $text .= "Вы можете:\n";
            $text .= "- Искать товары (например: \"найти сыр\")\n";
            $text .= "- Посмотреть категории\n";
            $text .= "- Скачать каталог в PDF\n";
            $text .= "- Зарегистрироваться как производитель\n";
        }

        return array(
            'text' => $text,
            'actions' => array(
                array('type' => 'button', 'text' => 'Категории', 'action' => 'view_categories'),
                array('type' => 'button', 'text' => 'Помощь', 'action' => 'help'),
            ),
        );
    }

    /**
     * Search products response
     */
    private function response_search_products($entities) {
        global $wpdb;

        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $producers_table = $wpdb->prefix . 'sap_producers';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        $where = array("p.status = 'active'");
        $params = array();

        // Search by category
        if (isset($entities['category'])) {
            $where[] = "c.slug = %s";
            $params[] = $entities['category'];
        }

        // Search by product name
        if (isset($entities['product_name'])) {
            $where[] = "MATCH(p.name, p.description, p.tags) AGAINST(%s IN BOOLEAN MODE)";
            $params[] = $entities['product_name'];
        }

        // If no specific search, use the original message
        if (empty($params) && isset($entities['input'])) {
            $search_term = $entities['input'];
            $where[] = "(p.name LIKE %s OR p.description LIKE %s OR p.tags LIKE %s)";
            $like = '%' . $wpdb->esc_like($search_term) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT p.*, pr.name as producer_name, pr.city, pr.contact_phone, pr.contact_telegram,
                       c.name as category_name
                FROM $products_table p
                LEFT JOIN $producers_table pr ON p.producer_id = pr.id
                LEFT JOIN $categories_table c ON p.category_id = c.id
                WHERE $where_clause
                ORDER BY p.featured DESC, p.views_count DESC
                LIMIT 10";

        $products = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        if (empty($products)) {
            return array(
                'text' => "К сожалению, ничего не найдено. Попробуйте изменить запрос или посмотрите категории.",
                'actions' => array(
                    array('type' => 'button', 'text' => 'Категории', 'action' => 'view_categories'),
                ),
            );
        }

        $text = "Найдено " . count($products) . " товаров:\n\n";

        foreach ($products as $product) {
            $price_str = $product['price']
                ? number_format($product['price'], 0, '.', ' ') . ' грн/' . $product['price_unit']
                : 'Цена по запросу';

            $stock_str = $product['in_stock'] ? '✅' : '❌ Нет в наличии';

            $text .= "**{$product['name']}** {$stock_str}\n";
            $text .= "{$price_str}\n";
            $text .= "📍 {$product['producer_name']}, {$product['city']}\n";

            if ($product['contact_telegram']) {
                $text .= "📱 @{$product['contact_telegram']}\n";
            } elseif ($product['contact_phone']) {
                $text .= "📞 {$product['contact_phone']}\n";
            }

            $text .= "\n";
        }

        return array(
            'text' => $text,
            'products' => $products,
            'actions' => array(
                array('type' => 'button', 'text' => 'Скачать PDF', 'action' => 'export_pdf'),
            ),
        );
    }

    /**
     * View categories response
     */
    private function response_view_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_product_categories';

        $categories = $wpdb->get_results(
            "SELECT * FROM $table WHERE parent_id = 0 AND status = 'active' ORDER BY sort_order",
            ARRAY_A
        );

        $text = "Категории товаров:\n\n";

        $actions = array();
        foreach ($categories as $cat) {
            $count = $cat['products_count'] > 0 ? " ({$cat['products_count']})" : '';
            $text .= "{$cat['icon']} {$cat['name']}{$count}\n";

            $actions[] = array(
                'type' => 'button',
                'text' => $cat['name'],
                'action' => 'search_category',
                'data' => $cat['slug'],
            );
        }

        $text .= "\nВыберите категорию или напишите что ищете.";

        return array(
            'text' => $text,
            'actions' => $actions,
        );
    }

    /**
     * Register producer response
     */
    private function response_register_producer($entities) {
        // Check if already a producer
        if ($this->session['user_type'] === 'producer') {
            return array(
                'text' => "Вы уже зарегистрированы как производитель. Используйте команды для управления товарами.",
                'actions' => array(
                    array('type' => 'button', 'text' => 'Мои товары', 'action' => 'my_products'),
                    array('type' => 'button', 'text' => 'Добавить товар', 'action' => 'add_product'),
                ),
            );
        }

        // Start registration wizard
        $context = $this->session['context'];
        $context['wizard'] = 'register_producer';
        $context['wizard_step'] = 'name';
        $context['wizard_data'] = array();
        $this->session['context'] = $context;

        return array(
            'text' => "Отлично! Давайте зарегистрируем вас как производителя крафтовых товаров.\n\n" .
                     "Шаг 1 из 5: Как называется ваше производство или магазин?",
            'actions' => array(
                array('type' => 'button', 'text' => 'Отмена', 'action' => 'cancel_wizard'),
            ),
        );
    }

    /**
     * Add product response
     */
    private function response_add_product($entities) {
        if ($this->session['user_type'] !== 'producer') {
            return array(
                'text' => "Для добавления товаров нужно сначала зарегистрироваться как производитель.",
                'actions' => array(
                    array('type' => 'button', 'text' => 'Регистрация', 'action' => 'register_producer'),
                ),
            );
        }

        // Start add product wizard
        $context = $this->session['context'];
        $context['wizard'] = 'add_product';
        $context['wizard_step'] = 'name';
        $context['wizard_data'] = array();
        $this->session['context'] = $context;

        return array(
            'text' => "Добавляем новый товар.\n\nШаг 1: Введите название товара:",
            'actions' => array(
                array('type' => 'button', 'text' => 'Отмена', 'action' => 'cancel_wizard'),
            ),
        );
    }

    /**
     * Update price response
     */
    private function response_update_price($entities) {
        if ($this->session['user_type'] !== 'producer') {
            return array('text' => 'Эта функция доступна только производителям.');
        }

        // Show producer's products for price update
        global $wpdb;
        $table = $wpdb->prefix . 'sap_catalog_products';

        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, price, price_unit FROM $table WHERE producer_id = %d AND status != 'deleted' ORDER BY name",
            $this->session['producer_id']
        ), ARRAY_A);

        if (empty($products)) {
            return array('text' => 'У вас пока нет товаров. Добавьте первый товар.');
        }

        $text = "Выберите товар для изменения цены:\n\n";
        $actions = array();

        foreach ($products as $product) {
            $price_str = $product['price']
                ? number_format($product['price'], 0) . ' грн/' . $product['price_unit']
                : 'Не указана';
            $text .= "• {$product['name']} - {$price_str}\n";

            $actions[] = array(
                'type' => 'button',
                'text' => $product['name'],
                'action' => 'select_product_price',
                'data' => $product['id'],
            );
        }

        return array(
            'text' => $text,
            'actions' => $actions,
        );
    }

    /**
     * Update stock response
     */
    private function response_update_stock($entities) {
        if ($this->session['user_type'] !== 'producer') {
            return array('text' => 'Эта функция доступна только производителям.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sap_catalog_products';

        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, in_stock FROM $table WHERE producer_id = %d AND status != 'deleted' ORDER BY name",
            $this->session['producer_id']
        ), ARRAY_A);

        if (empty($products)) {
            return array('text' => 'У вас пока нет товаров.');
        }

        $text = "Товары и наличие:\n\n";
        $actions = array();

        foreach ($products as $product) {
            $stock_icon = $product['in_stock'] ? '✅' : '❌';
            $text .= "{$stock_icon} {$product['name']}\n";

            $actions[] = array(
                'type' => 'button',
                'text' => ($product['in_stock'] ? '❌ ' : '✅ ') . $product['name'],
                'action' => 'toggle_stock',
                'data' => $product['id'],
            );
        }

        $text .= "\nНажмите на товар чтобы изменить наличие.";

        return array(
            'text' => $text,
            'actions' => $actions,
        );
    }

    /**
     * My products response
     */
    private function response_my_products() {
        if ($this->session['user_type'] !== 'producer') {
            return array('text' => 'Эта функция доступна только производителям.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sap_catalog_products';

        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE producer_id = %d AND status != 'deleted' ORDER BY created_at DESC",
            $this->session['producer_id']
        ), ARRAY_A);

        if (empty($products)) {
            return array(
                'text' => "У вас пока нет товаров в каталоге.\n\nДобавьте свой первый товар!",
                'actions' => array(
                    array('type' => 'button', 'text' => 'Добавить товар', 'action' => 'add_product'),
                    array('type' => 'button', 'text' => 'Импорт из Excel', 'action' => 'import_excel'),
                ),
            );
        }

        $text = "Ваши товары ({$total}):\n\n";

        foreach ($products as $product) {
            $price_str = $product['price']
                ? number_format($product['price'], 0) . ' грн'
                : '—';
            $stock_icon = $product['in_stock'] ? '✅' : '❌';

            $text .= "{$stock_icon} **{$product['name']}** - {$price_str}\n";
        }

        return array(
            'text' => $text,
            'actions' => array(
                array('type' => 'button', 'text' => 'Добавить товар', 'action' => 'add_product'),
                array('type' => 'button', 'text' => 'Обновить цены', 'action' => 'update_price'),
                array('type' => 'button', 'text' => 'Наличие', 'action' => 'update_stock'),
            ),
        );
    }

    /**
     * Import Excel response
     */
    private function response_import_excel() {
        if ($this->session['user_type'] !== 'producer') {
            return array('text' => 'Эта функция доступна только производителям.');
        }

        $upload_url = admin_url('admin-ajax.php?action=sap_import_products');

        $text = "Импорт товаров из Excel:\n\n";
        $text .= "1. Скачайте шаблон Excel\n";
        $text .= "2. Заполните данные о товарах\n";
        $text .= "3. Загрузите заполненный файл\n\n";
        $text .= "Формат файла: .xlsx или .xls\n";
        $text .= "Максимальный размер: 5 МБ\n";

        return array(
            'text' => $text,
            'actions' => array(
                array('type' => 'link', 'text' => 'Скачать шаблон', 'url' => '/wp-content/plugins/seo-analytics-pro/templates/product-import-template.xlsx'),
                array('type' => 'upload', 'text' => 'Загрузить файл', 'accept' => '.xlsx,.xls'),
            ),
        );
    }

    /**
     * Export PDF response
     */
    private function response_export_pdf($entities) {
        $category = isset($entities['category']) ? $entities['category'] : null;

        // Generate PDF URL
        $pdf_url = add_query_arg(array(
            'action' => 'sap_export_catalog_pdf',
            'category' => $category,
            'nonce' => wp_create_nonce('sap_export_pdf'),
        ), admin_url('admin-ajax.php'));

        $text = "Каталог в формате PDF готов к скачиванию.";

        if ($category) {
            $text .= "\nКатегория: " . $category;
        }

        return array(
            'text' => $text,
            'actions' => array(
                array('type' => 'link', 'text' => 'Скачать PDF', 'url' => $pdf_url),
            ),
        );
    }

    /**
     * Contact producer response
     */
    private function response_contact_producer($entities) {
        // Check if we have product context
        $context = $this->session['context'];

        if (isset($context['last_product_id'])) {
            global $wpdb;
            $products_table = $wpdb->prefix . 'sap_catalog_products';
            $producers_table = $wpdb->prefix . 'sap_producers';

            $producer = $wpdb->get_row($wpdb->prepare(
                "SELECT pr.* FROM $producers_table pr
                 INNER JOIN $products_table p ON p.producer_id = pr.id
                 WHERE p.id = %d",
                $context['last_product_id']
            ), ARRAY_A);

            if ($producer) {
                $text = "Контакты производителя **{$producer['name']}**:\n\n";

                if ($producer['contact_phone']) {
                    $text .= "📞 {$producer['contact_phone']}\n";
                }
                if ($producer['contact_telegram']) {
                    $text .= "📱 Telegram: @{$producer['contact_telegram']}\n";
                }
                if ($producer['contact_email']) {
                    $text .= "📧 {$producer['contact_email']}\n";
                }
                if ($producer['city']) {
                    $text .= "📍 {$producer['city']}\n";
                }

                return array('text' => $text);
            }
        }

        return array(
            'text' => "Чтобы связаться с производителем, сначала найдите интересующий товар.",
            'actions' => array(
                array('type' => 'button', 'text' => 'Категории', 'action' => 'view_categories'),
            ),
        );
    }

    /**
     * Help response
     */
    private function response_help() {
        $text = "Я чат-бот каталога крафтовых товаров.\n\n";
        $text .= "**Для покупателей:**\n";
        $text .= "• Напишите что ищете (например: \"сыр\" или \"найти мёд\")\n";
        $text .= "• Посмотрите категории\n";
        $text .= "• Скачайте каталог в PDF\n\n";

        $text .= "**Для производителей:**\n";
        $text .= "• Зарегистрируйтесь\n";
        $text .= "• Добавляйте товары\n";
        $text .= "• Обновляйте цены и наличие\n";
        $text .= "• Импортируйте товары из Excel\n\n";

        $text .= "Напишите \"категории\" чтобы начать поиск.";

        return array(
            'text' => $text,
            'actions' => array(
                array('type' => 'button', 'text' => 'Категории', 'action' => 'view_categories'),
                array('type' => 'button', 'text' => 'Регистрация', 'action' => 'register_producer'),
            ),
        );
    }

    /**
     * Unknown intent response
     */
    private function response_unknown() {
        return array(
            'text' => "Не совсем понял. Попробуйте написать что вы ищете или используйте команды.\n\n" .
                     "Например: \"найти сыр\" или \"категории\"",
            'actions' => array(
                array('type' => 'button', 'text' => 'Категории', 'action' => 'view_categories'),
                array('type' => 'button', 'text' => 'Помощь', 'action' => 'help'),
            ),
        );
    }

    /**
     * Save message to database
     *
     * @param string $content Message content
     * @param string $role Message role (user/assistant/system)
     * @param string $intent Detected intent
     */
    private function save_message($content, $role, $intent = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_chat_messages';

        $wpdb->insert($table, array(
            'session_id' => $this->session['id'],
            'role' => $role,
            'content' => $content,
            'intent' => $intent,
            'entities' => json_encode($this->session['context']['entities'] ?? array()),
            'created_at' => current_time('mysql'),
        ));
    }

    /**
     * Update session in database
     */
    private function update_session() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_chat_sessions';

        $wpdb->update(
            $table,
            array(
                'current_intent' => $this->session['current_intent'],
                'context' => json_encode($this->session['context']),
                'last_message_at' => current_time('mysql'),
                'messages_count' => $this->session['messages_count'] + 1,
            ),
            array('id' => $this->session['id'])
        );
    }

    /**
     * Get session history
     *
     * @param int $limit Number of messages to retrieve
     * @return array Messages
     */
    public function get_session_history($limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_chat_messages';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %d ORDER BY created_at DESC LIMIT %d",
            $this->session['id'],
            $limit
        ), ARRAY_A);
    }

    /**
     * Clear session context
     */
    public function clear_session() {
        $this->session['context'] = array();
        $this->session['current_intent'] = null;
        $this->update_session();
    }
}
