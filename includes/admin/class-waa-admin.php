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

        add_submenu_page(
            'waa-dashboard',
            __('Логи диалогов', 'woo-ai-assistant'),
            __('Логи диалогов', 'woo-ai-assistant'),
            'manage_options',
            'waa-logs',
            array($this, 'render_logs')
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

    public function render_logs() {
        global $wpdb;

        // Handle actions
        if (isset($_POST['mark_reviewed']) && wp_verify_nonce($_POST['_wpnonce'], 'waa_mark_reviewed')) {
            $id = intval($_POST['message_id']);
            $wpdb->update(
                $wpdb->prefix . 'waa_chat_history',
                array('reviewed' => 1),
                array('id' => $id)
            );
        }

        if (isset($_GET['export']) && $_GET['export'] === 'training') {
            $this->export_training_data();
            return;
        }

        // Filters
        $filter_rating = isset($_GET['rating']) ? $_GET['rating'] : '';
        $filter_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $filter_reviewed = isset($_GET['reviewed']) ? $_GET['reviewed'] : '';

        // Build query
        $where = array('1=1');
        $where_values = array();

        if ($filter_rating !== '') {
            $where[] = 'rating = %d';
            $where_values[] = intval($filter_rating);
        }

        if ($filter_category) {
            $where[] = 'feedback_category = %s';
            $where_values[] = $filter_category;
        }

        if ($filter_reviewed !== '') {
            $where[] = 'reviewed = %d';
            $where_values[] = intval($filter_reviewed);
        }

        $where_sql = implode(' AND ', $where);

        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Get total
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}waa_chat_history WHERE $where_sql";
        if (!empty($where_values)) {
            $total = $wpdb->get_var($wpdb->prepare($total_query, ...$where_values));
        } else {
            $total = $wpdb->get_var($total_query);
        }

        // Get logs
        $query = "SELECT * FROM {$wpdb->prefix}waa_chat_history WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $logs = $wpdb->get_results($wpdb->prepare($query, ...$query_values));

        // Get categories for filter
        $categories = $wpdb->get_col(
            "SELECT DISTINCT feedback_category FROM {$wpdb->prefix}waa_chat_history WHERE feedback_category IS NOT NULL AND feedback_category != ''"
        );

        ?>
        <div class="wrap waa-admin-wrap">
            <h1><?php _e('Логи диалогов', 'woo-ai-assistant'); ?></h1>

            <!-- Filters -->
            <div class="waa-logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="waa-logs">

                    <select name="rating">
                        <option value=""><?php _e('Все оценки', 'woo-ai-assistant'); ?></option>
                        <option value="1" <?php selected($filter_rating, '1'); ?>><?php _e('Положительные', 'woo-ai-assistant'); ?></option>
                        <option value="-1" <?php selected($filter_rating, '-1'); ?>><?php _e('Отрицательные', 'woo-ai-assistant'); ?></option>
                    </select>

                    <select name="category">
                        <option value=""><?php _e('Все категории', 'woo-ai-assistant'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter_category, $cat); ?>><?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="reviewed">
                        <option value=""><?php _e('Все статусы', 'woo-ai-assistant'); ?></option>
                        <option value="0" <?php selected($filter_reviewed, '0'); ?>><?php _e('Не просмотрено', 'woo-ai-assistant'); ?></option>
                        <option value="1" <?php selected($filter_reviewed, '1'); ?>><?php _e('Просмотрено', 'woo-ai-assistant'); ?></option>
                    </select>

                    <button type="submit" class="button"><?php _e('Фильтр', 'woo-ai-assistant'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=waa-logs'); ?>" class="button"><?php _e('Сброс', 'woo-ai-assistant'); ?></a>
                    <a href="<?php echo admin_url('admin.php?page=waa-logs&export=training'); ?>" class="button"><?php _e('Экспорт для обучения', 'woo-ai-assistant'); ?></a>
                </form>
            </div>

            <!-- Stats -->
            <div class="waa-logs-stats">
                <?php
                $negative_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}waa_chat_history WHERE rating = -1 AND reviewed = 0");
                if ($negative_count > 0):
                ?>
                <div class="notice notice-warning">
                    <p><?php printf(__('У вас %d непросмотренных отрицательных отзывов', 'woo-ai-assistant'), $negative_count); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Logs Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="5%"><?php _e('ID', 'woo-ai-assistant'); ?></th>
                        <th width="15%"><?php _e('Дата', 'woo-ai-assistant'); ?></th>
                        <th width="25%"><?php _e('Вопрос', 'woo-ai-assistant'); ?></th>
                        <th width="30%"><?php _e('Ответ', 'woo-ai-assistant'); ?></th>
                        <th width="10%"><?php _e('Оценка', 'woo-ai-assistant'); ?></th>
                        <th width="15%"><?php _e('Действия', 'woo-ai-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6"><?php _e('Логи не найдены', 'woo-ai-assistant'); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr class="<?php echo $log->rating == -1 && !$log->reviewed ? 'waa-log-negative' : ''; ?>">
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($log->created_at))); ?></td>
                        <td>
                            <div class="waa-log-message"><?php echo esc_html(mb_substr($log->user_message, 0, 100)); ?></div>
                        </td>
                        <td>
                            <div class="waa-log-message"><?php echo esc_html(mb_substr($log->assistant_message, 0, 150)); ?></div>
                            <?php if ($log->feedback_text): ?>
                            <div class="waa-log-feedback">
                                <strong><?php _e('Отзыв:', 'woo-ai-assistant'); ?></strong>
                                <?php echo esc_html($log->feedback_text); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($log->feedback_category): ?>
                            <div class="waa-log-category">
                                <span class="waa-category-badge"><?php echo esc_html($log->feedback_category); ?></span>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->rating == 1): ?>
                                <span class="waa-rating-positive">👍</span>
                            <?php elseif ($log->rating == -1): ?>
                                <span class="waa-rating-negative">👎</span>
                            <?php else: ?>
                                <span class="waa-rating-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$log->reviewed && $log->rating == -1): ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('waa_mark_reviewed'); ?>
                                <input type="hidden" name="message_id" value="<?php echo esc_attr($log->id); ?>">
                                <button type="submit" name="mark_reviewed" class="button button-small"><?php _e('Просмотрено', 'woo-ai-assistant'); ?></button>
                            </form>
                            <?php elseif ($log->reviewed): ?>
                                <span class="waa-reviewed-badge">✓</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $total_pages = ceil($total / $per_page);
            if ($total_pages > 1):
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                ));
                echo '</div></div>';
            endif;
            ?>
        </div>

        <style>
            .waa-logs-filters { margin: 15px 0; }
            .waa-logs-filters select { margin-right: 5px; }
            .waa-log-message { max-height: 60px; overflow: hidden; font-size: 12px; }
            .waa-log-feedback { margin-top: 8px; padding: 8px; background: #fff8e5; border-radius: 4px; font-size: 11px; }
            .waa-log-category { margin-top: 5px; }
            .waa-category-badge { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
            .waa-log-negative { background: #fcf0f1 !important; }
            .waa-rating-positive { color: #00a32a; }
            .waa-rating-negative { color: #d63638; }
            .waa-reviewed-badge { color: #00a32a; }
        </style>
        <?php
    }

    private function export_training_data() {
        global $wpdb;

        // Get logs with feedback
        $logs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}waa_chat_history WHERE rating IS NOT NULL ORDER BY created_at DESC"
        );

        $export_data = array();
        foreach ($logs as $log) {
            $export_data[] = array(
                'user_message' => $log->user_message,
                'assistant_message' => $log->assistant_message,
                'rating' => $log->rating,
                'feedback_text' => $log->feedback_text,
                'feedback_category' => $log->feedback_category,
                'context_ids' => $log->context_ids,
                'language' => $log->language,
                'created_at' => $log->created_at,
            );
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="waa-training-data-' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
