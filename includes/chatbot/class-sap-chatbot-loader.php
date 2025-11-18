<?php
/**
 * Chatbot Loader - Initializes all chatbot components
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/chatbot
 */

class SAP_Chatbot_Loader {

    /**
     * Initialize chatbot
     */
    public static function init() {
        // Load dependencies
        self::load_dependencies();

        // Initialize API
        new SAP_Chatbot_API();

        // Register frontend scripts
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_scripts'));

        // Register shortcode
        add_shortcode('sap_chatbot', array(__CLASS__, 'chatbot_shortcode'));

        // Add chatbot to footer
        add_action('wp_footer', array(__CLASS__, 'add_chatbot_to_footer'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        }
    }

    /**
     * Load dependencies
     */
    private static function load_dependencies() {
        $path = plugin_dir_path(__FILE__);

        require_once $path . 'class-sap-chatbot.php';
        require_once $path . 'class-sap-chatbot-api.php';
        require_once $path . 'class-sap-wizard-handler.php';
        require_once $path . 'class-sap-excel-importer.php';
        require_once $path . 'class-sap-pdf-exporter.php';
    }

    /**
     * Enqueue frontend scripts
     */
    public static function enqueue_frontend_scripts() {
        $settings = get_option('sap_chatbot_settings', array());

        // Only load if enabled
        if (empty($settings['enabled'])) {
            return;
        }

        wp_enqueue_script(
            'sap-chatbot-widget',
            plugin_dir_url(dirname(__FILE__)) . '../assets/js/public/chatbot-widget.js',
            array(),
            SEO_ANALYTICS_PRO_VERSION,
            true
        );

        // Pass configuration to JavaScript
        wp_localize_script('sap-chatbot-widget', 'sapChatbotConfig', array(
            'apiUrl' => rest_url('sap/v1'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sap_chatbot_nonce'),
            'primaryColor' => '#4a90d9',
            'title' => 'Каталог крафтовых товаров',
            'welcomeMessage' => $settings['welcome_message'] ?? 'Привет! Чем могу помочь?',
            'placeholder' => 'Введите сообщение...',
            'position' => 'right',
        ));
    }

    /**
     * Chatbot shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function chatbot_shortcode($atts) {
        $atts = shortcode_atts(array(
            'mode' => 'embedded', // 'embedded' or 'fullpage'
            'height' => '500px',
        ), $atts);

        // Load scripts
        wp_enqueue_script('sap-chatbot-widget');

        $id = 'sap-chatbot-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="sap-chatbot-embedded" style="height: <?php echo esc_attr($atts['height']); ?>;">
            <div id="sap-chatbot-messages-<?php echo esc_attr($id); ?>" class="sap-embedded-messages"></div>
            <div class="sap-embedded-input">
                <input type="text" id="sap-input-<?php echo esc_attr($id); ?>" placeholder="Введите сообщение..." />
                <button id="sap-send-<?php echo esc_attr($id); ?>">Отправить</button>
            </div>
        </div>
        <style>
            .sap-chatbot-embedded {
                border: 1px solid #ddd;
                border-radius: 8px;
                display: flex;
                flex-direction: column;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .sap-embedded-messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
            }
            .sap-embedded-input {
                padding: 12px;
                border-top: 1px solid #ddd;
                display: flex;
                gap: 8px;
            }
            .sap-embedded-input input {
                flex: 1;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 20px;
            }
            .sap-embedded-input button {
                padding: 10px 16px;
                background: #4a90d9;
                color: white;
                border: none;
                border-radius: 20px;
                cursor: pointer;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Add chatbot to footer (if set to auto-display)
     */
    public static function add_chatbot_to_footer() {
        $settings = get_option('sap_chatbot_settings', array());

        if (empty($settings['enabled'])) {
            return;
        }

        // The widget is loaded via JavaScript
        // This hook can be used for additional footer content if needed
    }

    /**
     * Add admin menu pages
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'sap-dashboard',
            'Каталог и чат-бот',
            'Каталог',
            'manage_sap_catalog',
            'sap-catalog',
            array(__CLASS__, 'render_catalog_page')
        );

        add_submenu_page(
            'sap-dashboard',
            'Производители',
            'Производители',
            'manage_sap_producers',
            'sap-producers',
            array(__CLASS__, 'render_producers_page')
        );

        add_submenu_page(
            'sap-dashboard',
            'Настройки чат-бота',
            'Чат-бот',
            'manage_sap_catalog',
            'sap-chatbot-settings',
            array(__CLASS__, 'render_chatbot_settings')
        );
    }

    /**
     * Render catalog admin page
     */
    public static function render_catalog_page() {
        global $wpdb;

        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $producers_table = $wpdb->prefix . 'sap_producers';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        // Get stats
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE status = 'active'");
        $total_producers = $wpdb->get_var("SELECT COUNT(*) FROM $producers_table WHERE status = 'active'");
        $total_categories = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table WHERE status = 'active'");

        // Get recent products
        $recent_products = $wpdb->get_results(
            "SELECT p.*, pr.name as producer_name
             FROM $products_table p
             LEFT JOIN $producers_table pr ON p.producer_id = pr.id
             WHERE p.status != 'deleted'
             ORDER BY p.created_at DESC
             LIMIT 20",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Каталог товаров</h1>

            <div class="sap-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="sap-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666;">Товаров</h3>
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html($total_products); ?></div>
                </div>
                <div class="sap-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666;">Производителей</h3>
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html($total_producers); ?></div>
                </div>
                <div class="sap-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666;">Категорий</h3>
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html($total_categories); ?></div>
                </div>
            </div>

            <h2>Последние товары</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Производитель</th>
                        <th>Цена</th>
                        <th>Наличие</th>
                        <th>Статус</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product['id']); ?></td>
                        <td><?php echo esc_html($product['name']); ?></td>
                        <td><?php echo esc_html($product['producer_name']); ?></td>
                        <td><?php echo $product['price'] ? number_format($product['price'], 0) . ' грн' : '—'; ?></td>
                        <td><?php echo $product['in_stock'] ? '✅' : '❌'; ?></td>
                        <td><?php echo esc_html($product['status']); ?></td>
                        <td><?php echo esc_html(date('d.m.Y', strtotime($product['created_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render producers admin page
     */
    public static function render_producers_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_producers';

        // Handle actions
        if (isset($_POST['action']) && isset($_POST['producer_id'])) {
            $action = sanitize_text_field($_POST['action']);
            $producer_id = intval($_POST['producer_id']);

            if ($action === 'approve') {
                $wpdb->update($table, array(
                    'status' => 'active',
                    'verified_at' => current_time('mysql'),
                ), array('id' => $producer_id));
            } elseif ($action === 'suspend') {
                $wpdb->update($table, array('status' => 'suspended'), array('id' => $producer_id));
            }
        }

        // Get producers
        $producers = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);

        ?>
        <div class="wrap">
            <h1>Производители</h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Город</th>
                        <th>Телефон</th>
                        <th>Telegram</th>
                        <th>Статус</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($producers as $producer): ?>
                    <tr>
                        <td><?php echo esc_html($producer['id']); ?></td>
                        <td><?php echo esc_html($producer['name']); ?></td>
                        <td><?php echo esc_html($producer['city']); ?></td>
                        <td><?php echo esc_html($producer['contact_phone']); ?></td>
                        <td><?php echo $producer['contact_telegram'] ? '@' . esc_html($producer['contact_telegram']) : '—'; ?></td>
                        <td>
                            <?php
                            $status_labels = array(
                                'pending' => '<span style="color: orange;">На модерации</span>',
                                'active' => '<span style="color: green;">Активен</span>',
                                'suspended' => '<span style="color: red;">Заблокирован</span>',
                            );
                            echo $status_labels[$producer['status']] ?? $producer['status'];
                            ?>
                        </td>
                        <td><?php echo esc_html(date('d.m.Y H:i', strtotime($producer['created_at']))); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="producer_id" value="<?php echo esc_attr($producer['id']); ?>">
                                <?php if ($producer['status'] === 'pending'): ?>
                                    <button type="submit" name="action" value="approve" class="button button-small button-primary">Одобрить</button>
                                <?php elseif ($producer['status'] === 'active'): ?>
                                    <button type="submit" name="action" value="suspend" class="button button-small">Заблокировать</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="approve" class="button button-small">Разблокировать</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render chatbot settings page
     */
    public static function render_chatbot_settings() {
        // Handle save
        if (isset($_POST['save_chatbot_settings']) && check_admin_referer('sap_chatbot_settings')) {
            $settings = array(
                'enabled' => isset($_POST['enabled']),
                'telegram_bot_token' => sanitize_text_field($_POST['telegram_bot_token'] ?? ''),
                'telegram_bot_username' => sanitize_text_field($_POST['telegram_bot_username'] ?? ''),
                'welcome_message' => sanitize_textarea_field($_POST['welcome_message'] ?? ''),
                'producer_approval_required' => isset($_POST['producer_approval_required']),
                'max_products_per_producer' => intval($_POST['max_products_per_producer'] ?? 100),
                'session_timeout_minutes' => intval($_POST['session_timeout_minutes'] ?? 30),
            );

            // Preserve webhook secret
            $old_settings = get_option('sap_chatbot_settings', array());
            $settings['webhook_secret'] = $old_settings['webhook_secret'] ?? wp_generate_password(32, false);

            update_option('sap_chatbot_settings', $settings);
            echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
        }

        $settings = get_option('sap_chatbot_settings', array());
        $webhook_url = rest_url('sap/v1/telegram/webhook');

        ?>
        <div class="wrap">
            <h1>Настройки чат-бота</h1>

            <form method="post">
                <?php wp_nonce_field('sap_chatbot_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th>Включить чат-бот</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" <?php checked(!empty($settings['enabled'])); ?>>
                                Показывать виджет чата на сайте
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Приветственное сообщение</th>
                        <td>
                            <textarea name="welcome_message" rows="3" class="large-text"><?php echo esc_textarea($settings['welcome_message'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Telegram Bot Token</th>
                        <td>
                            <input type="text" name="telegram_bot_token" value="<?php echo esc_attr($settings['telegram_bot_token'] ?? ''); ?>" class="regular-text">
                            <p class="description">Получите токен у @BotFather в Telegram</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Telegram Bot Username</th>
                        <td>
                            <input type="text" name="telegram_bot_username" value="<?php echo esc_attr($settings['telegram_bot_username'] ?? ''); ?>" class="regular-text">
                            <p class="description">Без символа @</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Webhook URL</th>
                        <td>
                            <code><?php echo esc_url($webhook_url); ?></code>
                            <p class="description">Установите этот URL в настройках Telegram бота</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Модерация производителей</th>
                        <td>
                            <label>
                                <input type="checkbox" name="producer_approval_required" <?php checked(!empty($settings['producer_approval_required'])); ?>>
                                Требовать одобрение администратора
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Максимум товаров</th>
                        <td>
                            <input type="number" name="max_products_per_producer" value="<?php echo esc_attr($settings['max_products_per_producer'] ?? 100); ?>" min="1" max="1000">
                            <p class="description">Максимальное количество товаров на производителя</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Таймаут сессии</th>
                        <td>
                            <input type="number" name="session_timeout_minutes" value="<?php echo esc_attr($settings['session_timeout_minutes'] ?? 30); ?>" min="5" max="1440">
                            минут
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="save_chatbot_settings" class="button-primary" value="Сохранить настройки">
                </p>
            </form>
        </div>
        <?php
    }
}
