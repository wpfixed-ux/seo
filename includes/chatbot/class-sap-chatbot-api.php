<?php
/**
 * REST API endpoints for Craft Catalog Chatbot
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/chatbot
 */

class SAP_Chatbot_API {

    /**
     * Chatbot instance
     *
     * @var SAP_Chatbot
     */
    private $chatbot;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_ajax_sap_chatbot_message', array($this, 'ajax_process_message'));
        add_action('wp_ajax_nopriv_sap_chatbot_message', array($this, 'ajax_process_message'));
        add_action('wp_ajax_sap_import_products', array($this, 'ajax_import_products'));
        add_action('wp_ajax_sap_export_catalog_pdf', array($this, 'ajax_export_pdf'));
        add_action('wp_ajax_nopriv_sap_export_catalog_pdf', array($this, 'ajax_export_pdf'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'sap/v1';

        // Chat endpoints
        register_rest_route($namespace, '/chat/message', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_message'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'session_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route($namespace, '/chat/session', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_session'),
            'permission_callback' => '__return_true',
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($namespace, '/chat/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_history'),
            'permission_callback' => '__return_true',
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'limit' => array(
                    'default' => 50,
                    'type' => 'integer',
                ),
            ),
        ));

        // Catalog endpoints
        register_rest_route($namespace, '/catalog/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_categories'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($namespace, '/catalog/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_products'),
            'permission_callback' => '__return_true',
            'args' => array(
                'category' => array('type' => 'string'),
                'search' => array('type' => 'string'),
                'producer_id' => array('type' => 'integer'),
                'page' => array('type' => 'integer', 'default' => 1),
                'per_page' => array('type' => 'integer', 'default' => 20),
            ),
        ));

        register_rest_route($namespace, '/catalog/products/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($namespace, '/catalog/producers', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_producers'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($namespace, '/catalog/producers/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_producer'),
            'permission_callback' => '__return_true',
        ));

        // Producer management (requires auth)
        register_rest_route($namespace, '/producer/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_my_products'),
            'permission_callback' => array($this, 'check_producer_permission'),
        ));

        register_rest_route($namespace, '/producer/products', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_product'),
            'permission_callback' => array($this, 'check_producer_permission'),
        ));

        register_rest_route($namespace, '/producer/products/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_product'),
            'permission_callback' => array($this, 'check_producer_permission'),
        ));

        register_rest_route($namespace, '/producer/products/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_product'),
            'permission_callback' => array($this, 'check_producer_permission'),
        ));

        register_rest_route($namespace, '/producer/profile', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_producer_profile'),
            'permission_callback' => array($this, 'check_producer_permission'),
        ));

        register_rest_route($namespace, '/producer/profile', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_producer_profile'),
            'permission_callback' => array($this, 'check_producer_permission'),
        ));

        // Telegram webhook
        register_rest_route($namespace, '/telegram/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'telegram_webhook'),
            'permission_callback' => array($this, 'verify_telegram_webhook'),
        ));
    }

    /**
     * Process chat message (REST API)
     */
    public function process_message($request) {
        $message = $request->get_param('message');
        $session_id = $request->get_param('session_id');

        $this->chatbot = new SAP_Chatbot();
        $response = $this->chatbot->process_message($message, $session_id, 'web');

        return rest_ensure_response(array(
            'success' => true,
            'data' => $response,
        ));
    }

    /**
     * Process chat message (AJAX)
     */
    public function ajax_process_message() {
        check_ajax_referer('sap_chatbot_nonce', 'nonce');

        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

        if (empty($message) || empty($session_id)) {
            wp_send_json_error('Missing required parameters');
        }

        $this->chatbot = new SAP_Chatbot();
        $response = $this->chatbot->process_message($message, $session_id, 'web');

        wp_send_json_success($response);
    }

    /**
     * Get session info
     */
    public function get_session($request) {
        global $wpdb;
        $session_id = $request->get_param('session_id');

        $table = $wpdb->prefix . 'sap_chat_sessions';
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s",
            $session_id
        ), ARRAY_A);

        if (!$session) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }

        return rest_ensure_response($session);
    }

    /**
     * Get chat history
     */
    public function get_history($request) {
        global $wpdb;
        $session_id = $request->get_param('session_id');
        $limit = intval($request->get_param('limit'));

        $sessions_table = $wpdb->prefix . 'sap_chat_sessions';
        $messages_table = $wpdb->prefix . 'sap_chat_messages';

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $sessions_table WHERE session_id = %s",
            $session_id
        ));

        if (!$session) {
            return rest_ensure_response(array('messages' => array()));
        }

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content, created_at FROM $messages_table
             WHERE session_id = %d ORDER BY created_at ASC LIMIT %d",
            $session->id,
            $limit
        ), ARRAY_A);

        return rest_ensure_response(array('messages' => $messages));
    }

    /**
     * Get categories
     */
    public function get_categories($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_product_categories';

        $categories = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'active' ORDER BY parent_id, sort_order",
            ARRAY_A
        );

        // Build tree structure
        $tree = array();
        $indexed = array();

        foreach ($categories as $cat) {
            $cat['children'] = array();
            $indexed[$cat['id']] = $cat;
        }

        foreach ($indexed as $id => $cat) {
            if ($cat['parent_id'] == 0) {
                $tree[] = &$indexed[$id];
            } else {
                $indexed[$cat['parent_id']]['children'][] = &$indexed[$id];
            }
        }

        return rest_ensure_response($tree);
    }

    /**
     * Get products
     */
    public function get_products($request) {
        global $wpdb;

        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $producers_table = $wpdb->prefix . 'sap_producers';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        $where = array("p.status = 'active'");
        $params = array();

        // Filter by category
        if ($category = $request->get_param('category')) {
            $where[] = "c.slug = %s";
            $params[] = $category;
        }

        // Search
        if ($search = $request->get_param('search')) {
            $where[] = "MATCH(p.name, p.description, p.tags) AGAINST(%s IN BOOLEAN MODE)";
            $params[] = $search;
        }

        // Filter by producer
        if ($producer_id = $request->get_param('producer_id')) {
            $where[] = "p.producer_id = %d";
            $params[] = $producer_id;
        }

        $where_clause = implode(' AND ', $where);

        // Pagination
        $page = max(1, intval($request->get_param('page')));
        $per_page = min(100, max(1, intval($request->get_param('per_page'))));
        $offset = ($page - 1) * $per_page;

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $products_table p
                      LEFT JOIN $categories_table c ON p.category_id = c.id
                      WHERE $where_clause";
        $total = $wpdb->get_var(empty($params) ? $count_sql : $wpdb->prepare($count_sql, $params));

        // Get products
        $sql = "SELECT p.*, pr.name as producer_name, pr.city, pr.contact_phone, pr.contact_telegram,
                       c.name as category_name, c.slug as category_slug
                FROM $products_table p
                LEFT JOIN $producers_table pr ON p.producer_id = pr.id
                LEFT JOIN $categories_table c ON p.category_id = c.id
                WHERE $where_clause
                ORDER BY p.featured DESC, p.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $per_page;
        $params[] = $offset;

        $products = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // Decode JSON fields
        foreach ($products as &$product) {
            $product['images'] = json_decode($product['images'], true) ?: array();
            $product['attributes'] = json_decode($product['attributes'], true) ?: array();
        }

        return rest_ensure_response(array(
            'products' => $products,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ));
    }

    /**
     * Get single product
     */
    public function get_product($request) {
        global $wpdb;

        $id = intval($request->get_param('id'));
        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $producers_table = $wpdb->prefix . 'sap_producers';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, pr.name as producer_name, pr.city, pr.contact_phone, pr.contact_telegram, pr.contact_email,
                    pr.description as producer_description, pr.delivery_info,
                    c.name as category_name, c.slug as category_slug
             FROM $products_table p
             LEFT JOIN $producers_table pr ON p.producer_id = pr.id
             LEFT JOIN $categories_table c ON p.category_id = c.id
             WHERE p.id = %d AND p.status != 'deleted'",
            $id
        ), ARRAY_A);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found', array('status' => 404));
        }

        // Increment views
        $wpdb->query($wpdb->prepare(
            "UPDATE $products_table SET views_count = views_count + 1 WHERE id = %d",
            $id
        ));

        $product['images'] = json_decode($product['images'], true) ?: array();
        $product['attributes'] = json_decode($product['attributes'], true) ?: array();

        return rest_ensure_response($product);
    }

    /**
     * Get producers
     */
    public function get_producers($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_producers';

        $producers = $wpdb->get_results(
            "SELECT id, name, city, description, logo_url FROM $table
             WHERE status = 'active' ORDER BY name",
            ARRAY_A
        );

        return rest_ensure_response($producers);
    }

    /**
     * Get single producer
     */
    public function get_producer($request) {
        global $wpdb;

        $id = intval($request->get_param('id'));
        $producers_table = $wpdb->prefix . 'sap_producers';
        $products_table = $wpdb->prefix . 'sap_catalog_products';

        $producer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, description, city, region, contact_phone, contact_telegram, contact_email,
                    delivery_info, working_hours, logo_url, website
             FROM $producers_table WHERE id = %d AND status = 'active'",
            $id
        ), ARRAY_A);

        if (!$producer) {
            return new WP_Error('not_found', 'Producer not found', array('status' => 404));
        }

        // Get product count
        $producer['products_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $products_table WHERE producer_id = %d AND status = 'active'",
            $id
        ));

        return rest_ensure_response($producer);
    }

    /**
     * Check producer permission
     */
    public function check_producer_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'sap_producers';

        $producer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND status = 'active'",
            $user_id
        ));

        return $producer_id > 0;
    }

    /**
     * Get current producer's products
     */
    public function get_my_products($request) {
        global $wpdb;
        $producer_id = $this->get_current_producer_id();

        $table = $wpdb->prefix . 'sap_catalog_products';
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE producer_id = %d AND status != 'deleted' ORDER BY created_at DESC",
            $producer_id
        ), ARRAY_A);

        foreach ($products as &$product) {
            $product['images'] = json_decode($product['images'], true) ?: array();
            $product['attributes'] = json_decode($product['attributes'], true) ?: array();
        }

        return rest_ensure_response($products);
    }

    /**
     * Create product
     */
    public function create_product($request) {
        global $wpdb;
        $producer_id = $this->get_current_producer_id();

        $data = $request->get_json_params();

        $result = $wpdb->insert($wpdb->prefix . 'sap_catalog_products', array(
            'producer_id' => $producer_id,
            'category_id' => intval($data['category_id']),
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'short_description' => sanitize_text_field($data['short_description'] ?? ''),
            'price' => floatval($data['price'] ?? 0),
            'price_unit' => sanitize_text_field($data['price_unit'] ?? 'шт'),
            'min_order_qty' => floatval($data['min_order_qty'] ?? 1),
            'in_stock' => isset($data['in_stock']) ? (bool)$data['in_stock'] : true,
            'stock_quantity' => isset($data['stock_quantity']) ? intval($data['stock_quantity']) : null,
            'sku' => sanitize_text_field($data['sku'] ?? ''),
            'images' => json_encode($data['images'] ?? array()),
            'attributes' => json_encode($data['attributes'] ?? array()),
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ));

        if (!$result) {
            return new WP_Error('insert_failed', 'Failed to create product', array('status' => 500));
        }

        // Update category product count
        $this->update_category_count($data['category_id']);

        return rest_ensure_response(array(
            'success' => true,
            'id' => $wpdb->insert_id,
        ));
    }

    /**
     * Update product
     */
    public function update_product($request) {
        global $wpdb;
        $producer_id = $this->get_current_producer_id();
        $product_id = intval($request->get_param('id'));

        // Verify ownership
        $table = $wpdb->prefix . 'sap_catalog_products';
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT producer_id FROM $table WHERE id = %d",
            $product_id
        ));

        if ($owner != $producer_id) {
            return new WP_Error('forbidden', 'Not your product', array('status' => 403));
        }

        $data = $request->get_json_params();
        $update = array('updated_at' => current_time('mysql'));

        $allowed_fields = array(
            'category_id', 'name', 'description', 'short_description',
            'price', 'price_unit', 'min_order_qty', 'in_stock',
            'stock_quantity', 'sku', 'images', 'attributes', 'tags', 'status'
        );

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, array('images', 'attributes'))) {
                    $update[$field] = json_encode($data[$field]);
                } elseif ($field === 'in_stock') {
                    $update[$field] = (bool)$data[$field];
                } elseif (in_array($field, array('category_id', 'stock_quantity'))) {
                    $update[$field] = intval($data[$field]);
                } elseif (in_array($field, array('price', 'min_order_qty'))) {
                    $update[$field] = floatval($data[$field]);
                } else {
                    $update[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        $wpdb->update($table, $update, array('id' => $product_id));

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Delete product
     */
    public function delete_product($request) {
        global $wpdb;
        $producer_id = $this->get_current_producer_id();
        $product_id = intval($request->get_param('id'));

        $table = $wpdb->prefix . 'sap_catalog_products';

        // Verify ownership
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT producer_id FROM $table WHERE id = %d",
            $product_id
        ));

        if ($owner != $producer_id) {
            return new WP_Error('forbidden', 'Not your product', array('status' => 403));
        }

        // Soft delete
        $wpdb->update($table, array('status' => 'deleted'), array('id' => $product_id));

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Get producer profile
     */
    public function get_producer_profile($request) {
        global $wpdb;
        $producer_id = $this->get_current_producer_id();

        $table = $wpdb->prefix . 'sap_producers';
        $producer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $producer_id
        ), ARRAY_A);

        unset($producer['verification_code']);

        return rest_ensure_response($producer);
    }

    /**
     * Update producer profile
     */
    public function update_producer_profile($request) {
        global $wpdb;
        $producer_id = $this->get_current_producer_id();

        $data = $request->get_json_params();
        $update = array('updated_at' => current_time('mysql'));

        $allowed_fields = array(
            'name', 'description', 'contact_phone', 'contact_email',
            'contact_telegram', 'contact_viber', 'location', 'city',
            'region', 'delivery_info', 'working_hours', 'logo_url', 'website'
        );

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update[$field] = sanitize_text_field($data[$field]);
            }
        }

        $table = $wpdb->prefix . 'sap_producers';
        $wpdb->update($table, $update, array('id' => $producer_id));

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Handle Excel import
     */
    public function ajax_import_products() {
        check_ajax_referer('sap_import_nonce', 'nonce');

        if (!$this->check_producer_permission(null)) {
            wp_send_json_error('Unauthorized');
        }

        if (!isset($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, array('xlsx', 'xls'))) {
            wp_send_json_error('Invalid file format. Please use .xlsx or .xls');
        }

        // Process Excel file
        require_once SAP_PLUGIN_PATH . 'includes/chatbot/class-sap-excel-importer.php';

        $importer = new SAP_Excel_Importer();
        $result = $importer->import($file['tmp_name'], $this->get_current_producer_id());

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle PDF export
     */
    public function ajax_export_pdf() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'sap_export_pdf')) {
            wp_die('Invalid nonce');
        }

        require_once SAP_PLUGIN_PATH . 'includes/chatbot/class-sap-pdf-exporter.php';

        $exporter = new SAP_PDF_Exporter();
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : null;

        $exporter->export($category);
        exit;
    }

    /**
     * Handle Telegram webhook
     */
    public function telegram_webhook($request) {
        $update = $request->get_json_params();

        if (!isset($update['message'])) {
            return rest_ensure_response(array('ok' => true));
        }

        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if (empty($text)) {
            return rest_ensure_response(array('ok' => true));
        }

        // Process message through chatbot
        $this->chatbot = new SAP_Chatbot();
        $response = $this->chatbot->process_message(
            $text,
            'telegram_' . $chat_id,
            'telegram',
            array('telegram_chat_id' => $chat_id)
        );

        // Send response to Telegram
        $this->send_telegram_message($chat_id, $response['text']);

        // Send action buttons if available
        if (!empty($response['actions'])) {
            $this->send_telegram_keyboard($chat_id, $response['actions']);
        }

        return rest_ensure_response(array('ok' => true));
    }

    /**
     * Verify Telegram webhook
     */
    public function verify_telegram_webhook($request) {
        $settings = get_option('sap_chatbot_settings', array());
        $secret = $settings['webhook_secret'] ?? '';

        // Check secret token from Telegram
        $token = $request->get_header('X-Telegram-Bot-Api-Secret-Token');

        return $token === $secret;
    }

    /**
     * Send message to Telegram
     */
    private function send_telegram_message($chat_id, $text) {
        $settings = get_option('sap_chatbot_settings', array());
        $token = $settings['telegram_bot_token'] ?? '';

        if (empty($token)) {
            return;
        }

        wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", array(
            'body' => array(
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ),
        ));
    }

    /**
     * Send keyboard to Telegram
     */
    private function send_telegram_keyboard($chat_id, $actions) {
        $settings = get_option('sap_chatbot_settings', array());
        $token = $settings['telegram_bot_token'] ?? '';

        if (empty($token)) {
            return;
        }

        $buttons = array();
        foreach ($actions as $action) {
            if ($action['type'] === 'button') {
                $buttons[] = array(array('text' => $action['text']));
            }
        }

        if (empty($buttons)) {
            return;
        }

        wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", array(
            'body' => array(
                'chat_id' => $chat_id,
                'text' => 'Выберите действие:',
                'reply_markup' => json_encode(array(
                    'keyboard' => $buttons,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                )),
            ),
        ));
    }

    /**
     * Get current producer ID
     */
    private function get_current_producer_id() {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'sap_producers';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
    }

    /**
     * Update category product count
     */
    private function update_category_count($category_id) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $products_table WHERE category_id = %d AND status = 'active'",
            $category_id
        ));

        $wpdb->update($categories_table, array('products_count' => $count), array('id' => $category_id));
    }
}
