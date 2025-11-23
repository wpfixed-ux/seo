<?php
/**
 * Settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        // API Settings
        register_setting('waa_settings', 'waa_ai_provider');
        register_setting('waa_settings', 'waa_openai_api_key');
        register_setting('waa_settings', 'waa_claude_api_key');
        register_setting('waa_settings', 'waa_kimi_api_key');
        register_setting('waa_settings', 'waa_embedding_model');
        register_setting('waa_settings', 'waa_chat_model');
        register_setting('waa_settings', 'waa_max_tokens');
        register_setting('waa_settings', 'waa_temperature');
        register_setting('waa_settings', 'waa_context_limit');

        // Language Settings
        register_setting('waa_settings', 'waa_languages');
        register_setting('waa_settings', 'waa_primary_language');
        register_setting('waa_settings', 'waa_welcome_message_ru');
        register_setting('waa_settings', 'waa_welcome_message_uk');
        register_setting('waa_settings', 'waa_system_prompt');

        // Display Settings
        register_setting('waa_settings', 'waa_floating_button_position');
        register_setting('waa_settings', 'waa_auto_floating');
        register_setting('waa_settings', 'waa_chat_theme');
        register_setting('waa_settings', 'waa_consultant_name');
        register_setting('waa_settings', 'waa_consultant_photo');

        // Indexing Settings
        register_setting('waa_settings', 'waa_index_posts');
        register_setting('waa_settings', 'waa_post_types');
        register_setting('waa_settings', 'waa_auto_reindex');
        register_setting('waa_settings', 'waa_reindex_time');

        // Reschedule cron when time changes
        add_action('update_option_waa_reindex_time', array($this, 'reschedule_cron'));
        add_action('update_option_waa_auto_reindex', array($this, 'reschedule_cron'));
    }

    /**
     * Reschedule cron when settings change
     */
    public function reschedule_cron() {
        // Clear existing schedule
        $timestamp = wp_next_scheduled('waa_scheduled_reindex');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'waa_scheduled_reindex');
        }

        // Schedule new event
        if (get_option('waa_auto_reindex', true)) {
            $time = get_option('waa_reindex_time', '06:00');
            $timezone = wp_timezone();

            $now = new DateTime('now', $timezone);
            $scheduled = new DateTime($time, $timezone);

            if ($scheduled <= $now) {
                $scheduled->modify('+1 day');
            }

            wp_schedule_event($scheduled->getTimestamp(), 'daily', 'waa_scheduled_reindex');
        }
    }

    public function render_page() {
        ?>
        <div class="wrap waa-settings-wrap">
            <h1><?php _e('Настройки AI Ассистента', 'woo-ai-assistant'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('waa_settings'); ?>

                <div class="waa-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#api" class="nav-tab nav-tab-active"><?php _e('API', 'woo-ai-assistant'); ?></a>
                        <a href="#languages" class="nav-tab"><?php _e('Языки', 'woo-ai-assistant'); ?></a>
                        <a href="#assistant" class="nav-tab"><?php _e('Ассистент', 'woo-ai-assistant'); ?></a>
                        <a href="#display" class="nav-tab"><?php _e('Отображение', 'woo-ai-assistant'); ?></a>
                        <a href="#indexing" class="nav-tab"><?php _e('Индексация', 'woo-ai-assistant'); ?></a>
                    </nav>

                    <!-- API Tab -->
                    <div id="api" class="waa-tab-content active">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('AI Провайдер', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <select name="waa_ai_provider">
                                        <option value="openai" <?php selected(get_option('waa_ai_provider'), 'openai'); ?>>OpenAI</option>
                                        <option value="claude" <?php selected(get_option('waa_ai_provider'), 'claude'); ?>>Claude (Anthropic)</option>
                                        <option value="kimi" <?php selected(get_option('waa_ai_provider'), 'kimi'); ?>>Kimi (Moonshot)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('OpenAI API Key', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="password" name="waa_openai_api_key"
                                           value="<?php echo esc_attr(get_option('waa_openai_api_key')); ?>"
                                           class="regular-text">
                                    <p class="description"><?php _e('Обязателен для эмбеддингов', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Claude API Key', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="password" name="waa_claude_api_key"
                                           value="<?php echo esc_attr(get_option('waa_claude_api_key')); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Kimi API Key', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="password" name="waa_kimi_api_key"
                                           value="<?php echo esc_attr(get_option('waa_kimi_api_key')); ?>"
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Модель эмбеддингов', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <select name="waa_embedding_model">
                                        <option value="text-embedding-3-small" <?php selected(get_option('waa_embedding_model'), 'text-embedding-3-small'); ?>>text-embedding-3-small</option>
                                        <option value="text-embedding-3-large" <?php selected(get_option('waa_embedding_model'), 'text-embedding-3-large'); ?>>text-embedding-3-large</option>
                                        <option value="text-embedding-ada-002" <?php selected(get_option('waa_embedding_model'), 'text-embedding-ada-002'); ?>>text-embedding-ada-002</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Модель чата', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <?php $chat_model = get_option('waa_chat_model', 'gpt-4o-mini'); ?>
                                    <select name="waa_chat_model">
                                        <option value="gpt-4o-mini" <?php selected($chat_model, 'gpt-4o-mini'); ?>>gpt-4o-mini (быстрая, дешевая)</option>
                                        <option value="gpt-4o" <?php selected($chat_model, 'gpt-4o'); ?>>gpt-4o (умная, дорогая)</option>
                                        <option value="gpt-4-turbo" <?php selected($chat_model, 'gpt-4-turbo'); ?>>gpt-4-turbo</option>
                                        <option value="gpt-3.5-turbo" <?php selected($chat_model, 'gpt-3.5-turbo'); ?>>gpt-3.5-turbo (устаревшая)</option>
                                    </select>
                                    <p class="description"><?php _e('Модель для генерации ответов', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Макс. токенов ответа', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="number" name="waa_max_tokens"
                                           value="<?php echo esc_attr(get_option('waa_max_tokens', 1000)); ?>"
                                           min="100" max="4000">
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Температура', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="number" name="waa_temperature"
                                           value="<?php echo esc_attr(get_option('waa_temperature', 0.7)); ?>"
                                           min="0" max="2" step="0.1">
                                    <p class="description"><?php _e('0 = точные ответы, 1 = креативные', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Лимит контекста', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="number" name="waa_context_limit"
                                           value="<?php echo esc_attr(get_option('waa_context_limit', 5)); ?>"
                                           min="1" max="20">
                                    <p class="description"><?php _e('Количество товаров в контексте', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Проверка подключения', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <button type="button" id="waa-test-connection" class="button button-secondary">
                                        <?php _e('Test Connection', 'woo-ai-assistant'); ?>
                                    </button>
                                    <span id="waa-test-result" style="margin-left: 10px;"></span>
                                    <p class="description"><?php _e('Проверить подключение к выбранному AI провайдеру', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('API Логи', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <button type="button" id="waa-load-logs" class="button button-secondary">
                                        <?php _e('Показать логи', 'woo-ai-assistant'); ?>
                                    </button>
                                    <button type="button" id="waa-clear-logs" class="button button-secondary" style="margin-left: 5px;">
                                        <?php _e('Очистить логи', 'woo-ai-assistant'); ?>
                                    </button>
                                    <div id="waa-api-logs" style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; display: none; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Languages Tab -->
                    <div id="languages" class="waa-tab-content">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Активные языки', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <?php $languages = get_option('waa_languages', array('ru')); ?>
                                    <label>
                                        <input type="checkbox" name="waa_languages[]" value="ru"
                                               <?php checked(in_array('ru', $languages)); ?>>
                                        Русский
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="waa_languages[]" value="uk"
                                               <?php checked(in_array('uk', $languages)); ?>>
                                        Українська
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Основной язык', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <select name="waa_primary_language">
                                        <option value="ru" <?php selected(get_option('waa_primary_language'), 'ru'); ?>>Русский</option>
                                        <option value="uk" <?php selected(get_option('waa_primary_language'), 'uk'); ?>>Українська</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Приветствие (RU)', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <textarea name="waa_welcome_message_ru" rows="3" class="large-text"><?php
                                        echo esc_textarea(get_option('waa_welcome_message_ru',
                                            'Здравствуйте! Я AI-консультант магазина. Помогу подобрать товар, расскажу о наличии и ценах. Чем могу помочь?'));
                                    ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Приветствие (UK)', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <textarea name="waa_welcome_message_uk" rows="3" class="large-text"><?php
                                        echo esc_textarea(get_option('waa_welcome_message_uk',
                                            'Вітаю! Я AI-консультант магазину. Допоможу підібрати товар, розкажу про наявність та ціни. Чим можу допомогти?'));
                                    ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Assistant Tab -->
                    <div id="assistant" class="waa-tab-content">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Системный промпт', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <textarea name="waa_system_prompt" rows="8" class="large-text"><?php
                                        echo esc_textarea(get_option('waa_system_prompt',
                                            'Ты - AI-консультант интернет-магазина. Твоя задача - помогать покупателям выбирать товары, отвечать на вопросы о наличии, ценах и характеристиках. Всегда давай ссылки на товары. Отвечай кратко и по делу. Если товара нет в наличии - предложи альтернативы.'));
                                    ?></textarea>
                                    <p class="description"><?php _e('Инструкции для AI-ассистента', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Display Tab -->
                    <div id="display" class="waa-tab-content">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Имя консультанта', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="text" name="waa_consultant_name"
                                           value="<?php echo esc_attr(get_option('waa_consultant_name', 'AI Консультант')); ?>"
                                           class="regular-text">
                                    <p class="description"><?php _e('Отображается в заголовке чата', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Фото консультанта', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <?php $consultant_photo = get_option('waa_consultant_photo', ''); ?>
                                    <input type="text" name="waa_consultant_photo" id="waa_consultant_photo"
                                           value="<?php echo esc_attr($consultant_photo); ?>"
                                           class="regular-text">
                                    <button type="button" class="button" id="waa_upload_photo_button">
                                        <?php _e('Выбрать фото', 'woo-ai-assistant'); ?>
                                    </button>
                                    <?php if ($consultant_photo): ?>
                                        <button type="button" class="button" id="waa_remove_photo_button">
                                            <?php _e('Удалить', 'woo-ai-assistant'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <p class="description"><?php _e('Отображается в заголовке чата рядом с именем', 'woo-ai-assistant'); ?></p>
                                    <?php if ($consultant_photo): ?>
                                        <div id="waa_photo_preview" style="margin-top: 10px;">
                                            <img src="<?php echo esc_url($consultant_photo); ?>" style="max-width: 100px; height: auto; border-radius: 50%;">
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Позиция кнопки', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <select name="waa_floating_button_position">
                                        <option value="bottom-right" <?php selected(get_option('waa_floating_button_position'), 'bottom-right'); ?>><?php _e('Снизу справа', 'woo-ai-assistant'); ?></option>
                                        <option value="bottom-left" <?php selected(get_option('waa_floating_button_position'), 'bottom-left'); ?>><?php _e('Снизу слева', 'woo-ai-assistant'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Авто-показ кнопки', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="waa_auto_floating" value="1"
                                               <?php checked(get_option('waa_auto_floating'), '1'); ?>>
                                        <?php _e('Показывать плавающую кнопку на всех страницах', 'woo-ai-assistant'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Тема чата', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <select name="waa_chat_theme">
                                        <option value="light" <?php selected(get_option('waa_chat_theme'), 'light'); ?>><?php _e('Светлая', 'woo-ai-assistant'); ?></option>
                                        <option value="dark" <?php selected(get_option('waa_chat_theme'), 'dark'); ?>><?php _e('Темная', 'woo-ai-assistant'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Indexing Tab -->
                    <div id="indexing" class="waa-tab-content">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Индексировать записи', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="waa_index_posts" value="1"
                                               <?php checked(get_option('waa_index_posts'), '1'); ?>>
                                        <?php _e('Включить индексацию статей и страниц', 'woo-ai-assistant'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Типы записей', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <?php $post_types = get_option('waa_post_types', array('post', 'page')); ?>
                                    <label>
                                        <input type="checkbox" name="waa_post_types[]" value="post"
                                               <?php checked(in_array('post', $post_types)); ?>>
                                        <?php _e('Записи', 'woo-ai-assistant'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="waa_post_types[]" value="page"
                                               <?php checked(in_array('page', $post_types)); ?>>
                                        <?php _e('Страницы', 'woo-ai-assistant'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Авто-переиндексация', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="waa_auto_reindex" value="1"
                                               <?php checked(get_option('waa_auto_reindex', true), '1'); ?>>
                                        <?php _e('Автоматически обновлять индекс ежедневно', 'woo-ai-assistant'); ?>
                                    </label>
                                    <p class="description"><?php _e('Переиндексирует только измененные товары (цена, наличие, описание)', 'woo-ai-assistant'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Время переиндексации', 'woo-ai-assistant'); ?></th>
                                <td>
                                    <input type="time" name="waa_reindex_time"
                                           value="<?php echo esc_attr(get_option('waa_reindex_time', '06:00')); ?>">
                                    <p class="description"><?php _e('Время по часовому поясу сайта', 'woo-ai-assistant'); ?></p>
                                    <?php
                                    $next_run = wp_next_scheduled('waa_scheduled_reindex');
                                    if ($next_run) {
                                        $timezone = wp_timezone();
                                        $next_date = new DateTime('@' . $next_run);
                                        $next_date->setTimezone($timezone);
                                        echo '<p class="description"><strong>' . __('Следующий запуск:', 'woo-ai-assistant') . '</strong> ' . $next_date->format('d.m.Y H:i') . '</p>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
