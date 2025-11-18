<?php
/**
 * Admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }

    public function add_menu() {
        add_menu_page(
            __('AI Ассистент', 'woo-ai-assistant'),
            __('AI Ассистент', 'woo-ai-assistant'),
            'manage_options',
            'waa-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-format-chat',
            56
        );

        add_submenu_page(
            'waa-dashboard',
            __('Панель управления', 'woo-ai-assistant'),
            __('Панель управления', 'woo-ai-assistant'),
            'manage_options',
            'waa-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'waa-dashboard',
            __('Настройки', 'woo-ai-assistant'),
            __('Настройки', 'woo-ai-assistant'),
            'manage_options',
            'waa-settings',
            array($this, 'render_settings')
        );
    }

    public function render_dashboard() {
        global $wpdb;

        $vector_db = WAA_Vector_DB::get_instance();
        $stats = $vector_db->get_stats();

        $index_status = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}waa_index_status WHERE id = 1"
        );

        $chat_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}waa_chat_history"
        );

        ?>
        <div class="wrap waa-admin-wrap">
            <h1><?php _e('AI Sales Assistant - Панель управления', 'woo-ai-assistant'); ?></h1>

            <div class="waa-dashboard-grid">
                <!-- Stats Cards -->
                <div class="waa-card">
                    <h3><?php _e('Статистика базы знаний', 'woo-ai-assistant'); ?></h3>
                    <div class="waa-stats-grid">
                        <div class="waa-stat">
                            <span class="waa-stat-value"><?php echo esc_html($stats['products']); ?></span>
                            <span class="waa-stat-label"><?php _e('Товаров', 'woo-ai-assistant'); ?></span>
                        </div>
                        <div class="waa-stat">
                            <span class="waa-stat-value"><?php echo esc_html($stats['posts']); ?></span>
                            <span class="waa-stat-label"><?php _e('Статей', 'woo-ai-assistant'); ?></span>
                        </div>
                        <div class="waa-stat">
                            <span class="waa-stat-value"><?php echo esc_html($stats['total']); ?></span>
                            <span class="waa-stat-label"><?php _e('Всего записей', 'woo-ai-assistant'); ?></span>
                        </div>
                        <div class="waa-stat">
                            <span class="waa-stat-value"><?php echo esc_html($chat_count); ?></span>
                            <span class="waa-stat-label"><?php _e('Диалогов', 'woo-ai-assistant'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Indexing Card -->
                <div class="waa-card">
                    <h3><?php _e('Индексация контента', 'woo-ai-assistant'); ?></h3>

                    <?php if ($index_status): ?>
                    <div class="waa-index-info">
                        <p>
                            <strong><?php _e('Статус:', 'woo-ai-assistant'); ?></strong>
                            <span class="waa-status-<?php echo esc_attr($index_status->status); ?>">
                                <?php
                                $status_labels = array(
                                    'idle' => __('Ожидание', 'woo-ai-assistant'),
                                    'indexing' => __('Индексация', 'woo-ai-assistant'),
                                    'complete' => __('Завершено', 'woo-ai-assistant'),
                                );
                                echo esc_html($status_labels[$index_status->status] ?? $index_status->status);
                                ?>
                            </span>
                        </p>
                        <?php if ($index_status->last_index_date): ?>
                        <p>
                            <strong><?php _e('Последняя индексация:', 'woo-ai-assistant'); ?></strong>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($index_status->last_index_date))); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="waa-progress-wrapper" style="display: none;">
                        <div class="waa-progress-bar">
                            <div class="waa-progress-fill"></div>
                        </div>
                        <div class="waa-progress-text"></div>
                    </div>

                    <div class="waa-index-actions">
                        <button type="button" class="button button-primary" id="waa-start-index">
                            <?php _e('Начать индексацию', 'woo-ai-assistant'); ?>
                        </button>
                        <button type="button" class="button" id="waa-clear-index">
                            <?php _e('Очистить индекс', 'woo-ai-assistant'); ?>
                        </button>
                    </div>

                    <div class="waa-index-log" style="display: none;"></div>
                </div>

                <!-- Languages Card -->
                <div class="waa-card">
                    <h3><?php _e('Языки базы знаний', 'woo-ai-assistant'); ?></h3>
                    <div class="waa-languages-stats">
                        <?php foreach ($stats['by_language'] as $lang => $count): ?>
                        <div class="waa-lang-stat">
                            <span class="waa-lang-code"><?php echo esc_html(strtoupper($lang)); ?></span>
                            <span class="waa-lang-count"><?php echo esc_html($count); ?> <?php _e('записей', 'woo-ai-assistant'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Shortcodes Card -->
                <div class="waa-card">
                    <h3><?php _e('Шорткоды', 'woo-ai-assistant'); ?></h3>
                    <div class="waa-shortcodes-list">
                        <div class="waa-shortcode-item">
                            <code>[waa_search]</code>
                            <span><?php _e('Строка умного поиска', 'woo-ai-assistant'); ?></span>
                        </div>
                        <div class="waa-shortcode-item">
                            <code>[waa_chat]</code>
                            <span><?php _e('Встроенный чат', 'woo-ai-assistant'); ?></span>
                        </div>
                        <div class="waa-shortcode-item">
                            <code>[waa_floating]</code>
                            <span><?php _e('Плавающая кнопка чата', 'woo-ai-assistant'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- API Status Card -->
                <div class="waa-card">
                    <h3><?php _e('Статус API', 'woo-ai-assistant'); ?></h3>
                    <?php
                    $provider = get_option('waa_ai_provider', 'openai');
                    $providers = array(
                        'openai' => 'OpenAI',
                        'claude' => 'Claude',
                        'kimi' => 'Kimi',
                    );
                    ?>
                    <p>
                        <strong><?php _e('Активный провайдер:', 'woo-ai-assistant'); ?></strong>
                        <?php echo esc_html($providers[$provider] ?? $provider); ?>
                    </p>
                    <p>
                        <strong><?php _e('API ключ:', 'woo-ai-assistant'); ?></strong>
                        <?php
                        $key = get_option('waa_' . $provider . '_api_key', '');
                        echo $key ? '<span class="waa-status-complete">✓ ' . __('Настроен', 'woo-ai-assistant') . '</span>' : '<span class="waa-status-idle">✗ ' . __('Не настроен', 'woo-ai-assistant') . '</span>';
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings() {
        // Settings are handled by WAA_Settings class
        WAA_Settings::get_instance()->render_page();
    }
}
