<?php
/**
 * Plugin activator
 */

if (!defined('ABSPATH')) {
    exit;
}

class WAA_Activator {

    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::create_upload_dir();
        self::schedule_cron();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Vector embeddings table
        $table_vectors = $wpdb->prefix . 'waa_vectors';
        $sql_vectors = "CREATE TABLE $table_vectors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            object_id bigint(20) NOT NULL,
            object_type varchar(50) NOT NULL,
            content_hash varchar(64) NOT NULL,
            embedding longtext NOT NULL,
            metadata longtext,
            language varchar(10) DEFAULT 'ru',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY object_id (object_id),
            KEY object_type (object_type),
            KEY content_hash (content_hash),
            KEY language (language)
        ) $charset_collate;";

        // Chat history table
        $table_chats = $wpdb->prefix . 'waa_chat_history';
        $sql_chats = "CREATE TABLE $table_chats (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_message text NOT NULL,
            assistant_message text NOT NULL,
            context_ids text,
            language varchar(10) DEFAULT 'ru',
            tokens_input int(11) DEFAULT 0,
            tokens_output int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0,
            rating tinyint(1) DEFAULT NULL,
            feedback_text text,
            feedback_category varchar(50) DEFAULT NULL,
            reviewed tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at),
            KEY rating (rating),
            KEY reviewed (reviewed)
        ) $charset_collate;";

        // Index status table
        $table_index = $wpdb->prefix . 'waa_index_status';
        $sql_index = "CREATE TABLE $table_index (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            total_products int(11) DEFAULT 0,
            indexed_products int(11) DEFAULT 0,
            total_posts int(11) DEFAULT 0,
            indexed_posts int(11) DEFAULT 0,
            last_index_date datetime,
            status varchar(50) DEFAULT 'idle',
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Click statistics table
        $table_stats = $wpdb->prefix . 'waa_click_stats';
        $sql_stats = "CREATE TABLE $table_stats (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            product_id bigint(20) NOT NULL,
            event_type varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY product_id (product_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_vectors);
        dbDelta($sql_chats);
        dbDelta($sql_index);
        dbDelta($sql_stats);

        // Add missing columns to existing tables (migration)
        self::migrate_tables();

        // Insert initial index status
        $wpdb->replace($table_index, array(
            'id' => 1,
            'status' => 'idle'
        ));
    }

    /**
     * Migrate existing tables - add new columns
     */
    private static function migrate_tables() {
        global $wpdb;

        $chat_table = $wpdb->prefix . 'waa_chat_history';

        // Check if tokens_input column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $chat_table LIKE 'tokens_input'");

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $chat_table ADD COLUMN tokens_input int(11) DEFAULT 0 AFTER language");
            $wpdb->query("ALTER TABLE $chat_table ADD COLUMN tokens_output int(11) DEFAULT 0 AFTER tokens_input");
            $wpdb->query("ALTER TABLE $chat_table ADD COLUMN cost decimal(10,6) DEFAULT 0 AFTER tokens_output");
        }

        // Check if click_stats table exists
        $stats_table = $wpdb->prefix . 'waa_click_stats';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$stats_table'");

        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql_stats = "CREATE TABLE $stats_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id varchar(64) NOT NULL,
                product_id bigint(20) NOT NULL,
                event_type varchar(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY session_id (session_id),
                KEY product_id (product_id),
                KEY event_type (event_type),
                KEY created_at (created_at)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql_stats);
        }
    }

    private static function set_default_options() {
        $defaults = array(
            'waa_ai_provider' => 'openai',
            'waa_openai_api_key' => '',
            'waa_claude_api_key' => '',
            'waa_kimi_api_key' => '',
            'waa_embedding_model' => 'text-embedding-3-small',
            'waa_chat_model' => 'gpt-4o-mini',
            'waa_max_tokens' => 1000,
            'waa_temperature' => 0.7,
            'waa_context_limit' => 5,
            'waa_languages' => array('ru', 'uk'),
            'waa_primary_language' => 'ru',
            'waa_welcome_message_ru' => 'Здравствуйте! Я AI-консультант магазина. Помогу подобрать товар, расскажу о наличии и ценах. Чем могу помочь?',
            'waa_welcome_message_uk' => 'Вітаю! Я AI-консультант магазину. Допоможу підібрати товар, розкажу про наявність та ціни. Чим можу допомогти?',
            'waa_system_prompt' => 'Ты - AI-консультант интернет-магазина. Твоя задача - помогать покупателям выбирать товары, отвечать на вопросы о наличии, ценах и характеристиках. Всегда давай ссылки на товары. Отвечай кратко и по делу. Если товара нет в наличии - предложи альтернативы.',
            'waa_floating_button_position' => 'bottom-right',
            'waa_chat_theme' => 'light',
            'waa_index_posts' => true,
            'waa_post_types' => array('post', 'page'),
            'waa_auto_reindex' => true,
            'waa_reindex_time' => '06:00',
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    private static function schedule_cron() {
        // Clear existing schedules
        $timestamp = wp_next_scheduled('waa_scheduled_reindex');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'waa_scheduled_reindex');
        }

        $cleanup_timestamp = wp_next_scheduled('waa_scheduled_cleanup');
        if ($cleanup_timestamp) {
            wp_unschedule_event($cleanup_timestamp, 'waa_scheduled_cleanup');
        }

        // Schedule daily reindex event
        if (get_option('waa_auto_reindex', true)) {
            $time = get_option('waa_reindex_time', '06:00');
            $timezone = wp_timezone();

            // Calculate next run time
            $now = new DateTime('now', $timezone);
            $scheduled = new DateTime($time, $timezone);

            // If time already passed today, schedule for tomorrow
            if ($scheduled <= $now) {
                $scheduled->modify('+1 day');
            }

            wp_schedule_event($scheduled->getTimestamp(), 'daily', 'waa_scheduled_reindex');
        }

        // Schedule weekly cleanup (every Sunday at 3 AM)
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        $cleanup_time = new DateTime('next sunday 03:00', $timezone);

        if ($cleanup_time <= $now) {
            $cleanup_time->modify('+1 week');
        }

        wp_schedule_event($cleanup_time->getTimestamp(), 'weekly', 'waa_scheduled_cleanup');
    }

    private static function create_upload_dir() {
        $upload_dir = wp_upload_dir();
        $waa_dir = $upload_dir['basedir'] . '/waa-data';

        if (!file_exists($waa_dir)) {
            wp_mkdir_p($waa_dir);

            // Protect directory
            file_put_contents($waa_dir . '/.htaccess', 'deny from all');
            file_put_contents($waa_dir . '/index.php', '<?php // Silence is golden');
        }
    }
}
