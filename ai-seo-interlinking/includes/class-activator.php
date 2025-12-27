<?php
/**
 * Plugin Activator Class
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::schedule_cron_jobs();

        // Set activation timestamp
        update_option('ail_activation_time', time());
        update_option('ail_version', AIL_VERSION);

        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix;

        // Keywords table
        $sql_keywords = "CREATE TABLE IF NOT EXISTS {$table_prefix}ai_interlinking_keywords (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            language varchar(10) DEFAULT 'default',
            priority int(11) DEFAULT 1,
            post_id bigint(20) unsigned DEFAULT NULL,
            keyword_type varchar(50) DEFAULT 'primary',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_keyword (keyword),
            KEY idx_language (language),
            KEY idx_post_id (post_id)
        ) $charset_collate;";

        // Links table
        $sql_links = "CREATE TABLE IF NOT EXISTS {$table_prefix}ai_interlinking_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) unsigned NOT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            anchor_text varchar(255) NOT NULL,
            anchor_type varchar(50) DEFAULT 'exact_match',
            language varchar(10) DEFAULT 'default',
            strategy varchar(50) DEFAULT 'pyramid',
            position int(11) DEFAULT NULL,
            relevance_score decimal(5,2) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source (source_post_id),
            KEY idx_target (target_post_id),
            KEY idx_language (language),
            UNIQUE KEY unique_link (source_post_id, target_post_id, anchor_text)
        ) $charset_collate;";

        // Logs table
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$table_prefix}ai_interlinking_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            details longtext,
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0.000000,
            status varchar(20) DEFAULT 'success',
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_operation (operation_type),
            KEY idx_status (status)
        ) $charset_collate;";

        // Settings table
        $sql_settings = "CREATE TABLE IF NOT EXISTS {$table_prefix}ai_interlinking_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            language varchar(10) DEFAULT 'default',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (setting_key, language)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql_keywords);
        dbDelta($sql_links);
        dbDelta($sql_logs);
        dbDelta($sql_settings);
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_settings = [
            // API Settings
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'max_tokens' => 500,
            'api_timeout' => 30,

            // Linking Strategy
            'linking_strategy' => 'pyramid',
            'max_outbound_links' => 5,
            'max_inbound_links' => 10,
            'min_content_length' => 300,
            'excluded_pages' => [],
            'excluded_categories' => [],

            // Anchor Settings
            'anchor_diversity' => [
                'exact_match' => 30,
                'partial_match' => 40,
                'lsi_match' => 30,
            ],

            // Scheduler Settings
            'scheduler_enabled' => false,
            'scheduler_frequency' => 'daily',
            'scheduler_time' => '02:00',
            'batch_size' => 50,
            'batch_delay' => 2,
            'process_new_only' => false,

            // Processing Settings
            'auto_process_on_save' => false,
            'use_cache' => true,
            'cache_duration' => 86400, // 24 hours

            // Logging Settings
            'enable_logging' => true,
            'log_retention_days' => 30,
            'log_api_requests' => true,

            // Multilang Settings
            'multilang_enabled' => false,
            'multilang_plugin' => 'auto', // auto, polylang, wpml
            'cross_language_linking' => false,

            // AI Prompt Template
            'ai_prompt_template' => 'Given the following content in {language}:
Title: {post_title}
Category: {category}
Content: {post_content}

Generate relevant keywords for internal linking:
1. Primary keywords (3-5 exact match)
2. LSI keywords (5-10 variations)
3. Long-tail keywords (3-5 phrases)

Format: JSON array with structure: [{"keyword": "...", "type": "primary|lsi|long-tail"}]',
        ];

        add_option('ail_settings', $default_settings);
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        if (!wp_next_scheduled('ail_batch_processing_cron')) {
            wp_schedule_event(time(), 'daily', 'ail_batch_processing_cron');
        }

        if (!wp_next_scheduled('ail_log_cleanup_cron')) {
            wp_schedule_event(time(), 'weekly', 'ail_log_cleanup_cron');
        }
    }
}
