<?php
/**
 * REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_REST_API {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'add_cors_headers'));
    }

    /**
     * Add CORS headers for REST API
     */
    public function add_cors_headers() {
        // Remove default WordPress REST API CORS
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

        // Add custom CORS headers
        add_filter('rest_pre_serve_request', function($value) {
            $origin = get_http_origin();

            // Allow same-origin requests
            if ($origin) {
                header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            } else {
                header('Access-Control-Allow-Origin: ' . home_url());
            }

            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: X-WP-Nonce, Content-Type, Authorization');
            header('Access-Control-Max-Age: 600');

            // Handle preflight OPTIONS request
            if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
                status_header(200);
                exit();
            }

            return $value;
        });
    }

    public function register_routes() {
        $namespace = 'waa/v1';

        // Chat endpoint
        register_rest_route($namespace, '/chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_chat'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'session_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'language' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'auto',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Search endpoint
        register_rest_route($namespace, '/search', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 5,
                ),
                'language' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'ru',
                ),
            ),
        ));

        // Welcome message endpoint
        register_rest_route($namespace, '/welcome', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_welcome'),
            'permission_callback' => '__return_true',
            'args' => array(
                'language' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'ru',
                ),
            ),
        ));

        // Stats endpoint (admin only)
        register_rest_route($namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));

        // Feedback endpoint
        register_rest_route($namespace, '/feedback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_feedback'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'rating' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => -1,
                    'maximum' => 1,
                ),
                'feedback_text' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Track click endpoint
        register_rest_route($namespace, '/track', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_track'),
            'permission_callback' => '__return_true',
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'product_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'event_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('click', 'add_to_cart'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Test connection endpoint (admin only)
        register_rest_route($namespace, '/test-connection', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_test_connection'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));

        // Get API logs endpoint (admin only)
        register_rest_route($namespace, '/api-logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_api_logs'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));

        // Clear API logs endpoint (admin only)
        register_rest_route($namespace, '/api-logs', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'clear_api_logs'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Handle chat request
     */
    public function handle_chat($request) {
        error_log('WAA REST: Chat endpoint called');

        try {
            // Rate limiting
            $ip = $this->get_client_ip();
            $rate_key = 'waa_rate_' . md5($ip);
            $rate_count = get_transient($rate_key);

            if ($rate_count && $rate_count > 30) {
                error_log('WAA REST: Rate limit exceeded for ' . $ip);
                return new WP_Error(
                    'rate_limit',
                    __('Too many requests. Please wait a moment.', 'woo-ai-assistant'),
                    array('status' => 429)
                );
            }

            set_transient($rate_key, ($rate_count ? $rate_count + 1 : 1), MINUTE_IN_SECONDS);

            $message = $request->get_param('message');
            $session_id = $request->get_param('session_id');
            $language = $request->get_param('language');

            error_log('WAA REST: Message received: ' . substr($message, 0, 100));

            // Generate session ID if not provided
            if (empty($session_id)) {
                $session_id = wp_generate_uuid4();
            }

            // Auto-detect language
            $assistant = WAA_Assistant::get_instance();
            if ($language === 'auto') {
                $language = $assistant->detect_language($message);
            }

            error_log('WAA REST: Processing query with language: ' . $language);

            // Process query
            $result = $assistant->query($message, $session_id, $language);

            if (!$result['success']) {
                // Log error for debugging
                error_log('WAA REST Error: ' . $result['error']);

                return new WP_Error(
                    'assistant_error',
                    $result['error'],
                    array('status' => 500)
                );
            }

            error_log('WAA REST: Query successful');

            return rest_ensure_response(array(
                'success' => true,
                'message' => $result['message'],
                'message_id' => $result['message_id'],
                'products' => $result['products'],
                'session_id' => $session_id,
                'language' => $language,
            ));
        } catch (Exception $e) {
            error_log('WAA REST Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error(
                'internal_error',
                'Internal server error: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle semantic search request
     */
    public function handle_search($request) {
        $query = $request->get_param('query');
        $limit = $request->get_param('limit');
        $language = $request->get_param('language');

        $ai_provider = WAA_Core::get_ai_provider();
        $vector_db = WAA_Vector_DB::get_instance();

        // Get embedding for query
        $embedding_result = $ai_provider->get_embedding($query);

        if (!$embedding_result['success']) {
            return new WP_Error(
                'embedding_error',
                $embedding_result['error'],
                array('status' => 500)
            );
        }

        // Search vector database
        $results = $vector_db->search(
            $embedding_result['embedding'],
            $limit,
            $language,
            'product'
        );

        // Format results
        $products = array();
        foreach ($results as $result) {
            $meta = $result['metadata'];
            $products[] = array(
                'id' => $result['object_id'],
                'title' => $meta['title'],
                'price' => $meta['price'],
                'price_html' => wc_price($meta['price']),
                'url' => $meta['url'],
                'image' => isset($meta['image']) ? $meta['image'] : '',
                'stock_status' => $meta['stock_status'],
                'stock_text' => $meta['stock_text'],
                'similarity' => round($result['similarity'] * 100, 1),
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'products' => $products,
            'total' => count($products),
        ));
    }

    /**
     * Get welcome message
     */
    public function get_welcome($request) {
        $language = $request->get_param('language');
        $assistant = WAA_Assistant::get_instance();

        return rest_ensure_response(array(
            'success' => true,
            'message' => $assistant->get_welcome_message($language),
        ));
    }

    /**
     * Get statistics
     */
    public function get_stats($request) {
        $vector_db = WAA_Vector_DB::get_instance();
        $stats = $vector_db->get_stats();

        global $wpdb;
        $chat_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}waa_chat_history"
        );

        return rest_ensure_response(array(
            'success' => true,
            'vectors' => $stats,
            'chats' => $chat_count,
        ));
    }

    /**
     * Handle feedback submission
     */
    public function handle_feedback($request) {
        global $wpdb;

        $message_id = $request->get_param('message_id');
        $rating = $request->get_param('rating');
        $feedback_text = $request->get_param('feedback_text');
        $category = $request->get_param('category');

        // Verify message exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}waa_chat_history WHERE id = %d",
            $message_id
        ));

        if (!$exists) {
            return new WP_Error(
                'invalid_message',
                __('Message not found', 'woo-ai-assistant'),
                array('status' => 404)
            );
        }

        // Update feedback
        $result = $wpdb->update(
            $wpdb->prefix . 'waa_chat_history',
            array(
                'rating' => $rating,
                'feedback_text' => $feedback_text,
                'feedback_category' => $category,
            ),
            array('id' => $message_id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error(
                'db_error',
                __('Failed to save feedback', 'woo-ai-assistant'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Feedback saved', 'woo-ai-assistant'),
        ));
    }

    /**
     * Handle click tracking
     */
    public function handle_track($request) {
        global $wpdb;

        $session_id = $request->get_param('session_id');
        $product_id = $request->get_param('product_id');
        $event_type = $request->get_param('event_type');

        // Insert tracking record
        $result = $wpdb->insert(
            $wpdb->prefix . 'waa_click_stats',
            array(
                'session_id' => $session_id,
                'product_id' => $product_id,
                'event_type' => $event_type,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error(
                'db_error',
                __('Failed to track event', 'woo-ai-assistant'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
        ));
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Handle test connection request
     */
    public function handle_test_connection($request) {
        $provider = get_option('waa_ai_provider', 'openai');

        // Log the test attempt
        $this->log_api_call('test_connection', array(
            'provider' => $provider,
            'timestamp' => current_time('mysql'),
        ));

        try {
            // Get the appropriate API key
            switch ($provider) {
                case 'openai':
                    $api_key = get_option('waa_openai_api_key');
                    if (empty($api_key)) {
                        return rest_ensure_response(array(
                            'success' => false,
                            'message' => 'OpenAI API key is not configured',
                        ));
                    }

                    // Test OpenAI connection
                    $response = wp_remote_get('https://api.openai.com/v1/models', array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $api_key,
                        ),
                        'timeout' => 15,
                    ));
                    break;

                case 'claude':
                    $api_key = get_option('waa_claude_api_key');
                    if (empty($api_key)) {
                        return rest_ensure_response(array(
                            'success' => false,
                            'message' => 'Claude API key is not configured',
                        ));
                    }

                    // Test Claude connection with a simple message
                    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
                        'headers' => array(
                            'x-api-key' => $api_key,
                            'anthropic-version' => '2023-06-01',
                            'Content-Type' => 'application/json',
                        ),
                        'body' => json_encode(array(
                            'model' => 'claude-3-haiku-20240307',
                            'max_tokens' => 10,
                            'messages' => array(
                                array('role' => 'user', 'content' => 'Hi'),
                            ),
                        )),
                        'timeout' => 15,
                    ));
                    break;

                case 'kimi':
                    $api_key = get_option('waa_kimi_api_key');
                    if (empty($api_key)) {
                        return rest_ensure_response(array(
                            'success' => false,
                            'message' => 'Kimi API key is not configured',
                        ));
                    }

                    // Test Kimi connection
                    $response = wp_remote_get('https://api.moonshot.cn/v1/models', array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $api_key,
                        ),
                        'timeout' => 15,
                    ));
                    break;

                default:
                    return rest_ensure_response(array(
                        'success' => false,
                        'message' => 'Unknown AI provider: ' . $provider,
                    ));
            }

            if (is_wp_error($response)) {
                $this->log_api_call('test_connection_error', array(
                    'provider' => $provider,
                    'error' => $response->get_error_message(),
                ));

                return rest_ensure_response(array(
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->get_error_message(),
                ));
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $this->log_api_call('test_connection_response', array(
                'provider' => $provider,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 500),
            ));

            if ($status_code === 200 || $status_code === 201) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Connection successful! Provider: ' . ucfirst($provider),
                    'status_code' => $status_code,
                ));
            } else {
                $error_data = json_decode($body, true);
                $error_message = isset($error_data['error']['message'])
                    ? $error_data['error']['message']
                    : 'HTTP ' . $status_code;

                return rest_ensure_response(array(
                    'success' => false,
                    'message' => 'Connection failed: ' . $error_message,
                    'status_code' => $status_code,
                ));
            }

        } catch (Exception $e) {
            $this->log_api_call('test_connection_exception', array(
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ));

            return rest_ensure_response(array(
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Get API logs
     */
    public function get_api_logs($request) {
        $logs = get_option('waa_api_logs', array());

        // Return last 100 logs in reverse order (newest first)
        $logs = array_slice(array_reverse($logs), 0, 100);

        return rest_ensure_response(array(
            'success' => true,
            'logs' => $logs,
            'count' => count($logs),
        ));
    }

    /**
     * Clear API logs
     */
    public function clear_api_logs($request) {
        update_option('waa_api_logs', array());

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Logs cleared successfully',
        ));
    }

    /**
     * Log API call
     */
    public function log_api_call($action, $data) {
        $logs = get_option('waa_api_logs', array());

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'data' => $data,
        );

        $logs[] = $log_entry;

        // Keep only last 500 logs
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }

        update_option('waa_api_logs', $logs);

        // Also log to error_log for debugging
        error_log('WAA API Log: ' . $action . ' - ' . json_encode($data));
    }
}
