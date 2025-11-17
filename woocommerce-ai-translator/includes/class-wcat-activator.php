<?php
/**
 * Fired during plugin activation
 *
 * @package WC_AI_Translator
 */

class WCAT_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check for required plugins
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WooCommerce AI Translator requires WooCommerce to be installed and active.', 'wc-ai-translator'));
        }

        if (!is_plugin_active('polylang/polylang.php') && !is_plugin_active('polylang-pro/polylang.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WooCommerce AI Translator requires Polylang or Polylang Pro to be installed and active.', 'wc-ai-translator'));
        }

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Translation queue table
        $table_queue = $wpdb->prefix . 'wcat_translation_queue';
        $sql_queue = "CREATE TABLE IF NOT EXISTS $table_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            content_id bigint(20) UNSIGNED NOT NULL,
            content_type varchar(50) NOT NULL,
            source_lang varchar(10) NOT NULL,
            target_lang varchar(10) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 5,
            attempts int(11) NOT NULL DEFAULT 0,
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY content_id (content_id),
            KEY content_type (content_type),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";

        // Translation logs table
        $table_logs = $wpdb->prefix . 'wcat_translation_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id bigint(20) UNSIGNED,
            content_id bigint(20) UNSIGNED NOT NULL,
            content_type varchar(50) NOT NULL,
            source_lang varchar(10) NOT NULL,
            target_lang varchar(10) NOT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            tokens_used int(11),
            cost decimal(10,4),
            user_id bigint(20) UNSIGNED,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY queue_id (queue_id),
            KEY content_id (content_id),
            KEY content_type (content_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_queue);
        dbDelta($sql_logs);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = array(
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o',
            'batch_size' => 10,
            'translation_quality' => 'high',
            'auto_publish' => false,
            'translate_slugs' => false,
            'translate_images' => true,
            'translate_seo' => true,
            'content_types' => array('post', 'page', 'product'),
            'taxonomies' => array('category', 'post_tag', 'product_cat', 'product_tag'),
            'max_retries' => 3,
            'preserve_html' => true,
            'translation_context' => '',
        );

        if (!get_option('wcat_settings')) {
            add_option('wcat_settings', $default_options);
        }

        // Add version option
        add_option('wcat_version', WCAT_VERSION);
    }
}
