<?php
/**
 * Fired during plugin activation
 *
 * @package AIMarketingAssistant
 */

class AIMA_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::schedule_cron_events();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Customer history table
        $table_customers = $wpdb->prefix . 'aima_customers';
        $sql_customers = "CREATE TABLE IF NOT EXISTS $table_customers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(100) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            total_orders int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            last_order_date datetime DEFAULT NULL,
            first_order_date datetime DEFAULT NULL,
            source varchar(50) DEFAULT 'woocommerce',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY customer_id (customer_id),
            KEY last_order_date (last_order_date)
        ) $charset_collate;";

        // Purchase history table
        $table_purchases = $wpdb->prefix . 'aima_purchase_history';
        $sql_purchases = "CREATE TABLE IF NOT EXISTS $table_purchases (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            product_name varchar(255) NOT NULL,
            category varchar(255) DEFAULT NULL,
            quantity int(11) DEFAULT 1,
            price decimal(10,2) DEFAULT 0.00,
            total decimal(10,2) DEFAULT 0.00,
            order_date datetime NOT NULL,
            source varchar(50) DEFAULT 'woocommerce',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY product_id (product_id),
            KEY order_date (order_date),
            KEY category (category)
        ) $charset_collate;";

        // Segments table
        $table_segments = $wpdb->prefix . 'aima_segments';
        $sql_segments = "CREATE TABLE IF NOT EXISTS $table_segments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            conditions text NOT NULL,
            customer_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        // Segment members table
        $table_segment_members = $wpdb->prefix . 'aima_segment_members';
        $sql_segment_members = "CREATE TABLE IF NOT EXISTS $table_segment_members (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            segment_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY segment_customer (segment_id, customer_id),
            KEY segment_id (segment_id),
            KEY customer_id (customer_id)
        ) $charset_collate;";

        // Offers/Campaigns table
        $table_offers = $wpdb->prefix . 'aima_offers';
        $sql_offers = "CREATE TABLE IF NOT EXISTS $table_offers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) DEFAULT 'email',
            subject varchar(255) DEFAULT NULL,
            banner_url varchar(500) DEFAULT NULL,
            content text DEFAULT NULL,
            products text DEFAULT NULL,
            coupon_code varchar(100) DEFAULT NULL,
            discount_value decimal(10,2) DEFAULT 0.00,
            discount_type varchar(20) DEFAULT 'percent',
            segment_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            sent_count int(11) DEFAULT 0,
            opened_count int(11) DEFAULT 0,
            clicked_count int(11) DEFAULT 0,
            converted_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY segment_id (segment_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        // Campaign recipients table
        $table_recipients = $wpdb->prefix . 'aima_campaign_recipients';
        $sql_recipients = "CREATE TABLE IF NOT EXISTS $table_recipients (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            email varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            converted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY offer_id (offer_id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";

        // Telegram subscribers table
        $table_telegram = $wpdb->prefix . 'aima_telegram_subscribers';
        $sql_telegram = "CREATE TABLE IF NOT EXISTS $table_telegram (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED DEFAULT NULL,
            telegram_user_id varchar(100) NOT NULL,
            telegram_username varchar(100) DEFAULT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY telegram_user_id (telegram_user_id),
            KEY customer_id (customer_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Import logs table
        $table_imports = $wpdb->prefix . 'aima_import_logs';
        $sql_imports = "CREATE TABLE IF NOT EXISTS $table_imports (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            total_rows int(11) DEFAULT 0,
            processed_rows int(11) DEFAULT 0,
            successful_rows int(11) DEFAULT 0,
            failed_rows int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'processing',
            error_log text DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_customers);
        dbDelta($sql_purchases);
        dbDelta($sql_segments);
        dbDelta($sql_segment_members);
        dbDelta($sql_offers);
        dbDelta($sql_recipients);
        dbDelta($sql_telegram);
        dbDelta($sql_imports);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = array(
            'aima_ai_provider' => 'anthropic',
            'aima_ai_model' => 'claude-3-5-sonnet-20241022',
            'aima_ai_api_key' => '',
            'aima_telegram_bot_token' => '',
            'aima_telegram_channel_id' => '',
            'aima_email_from_name' => get_bloginfo('name'),
            'aima_email_from_email' => get_bloginfo('admin_email'),
            'aima_campaign_frequency' => 'monthly',
            'aima_min_purchase_count' => 1,
            'aima_analytics_days' => 90,
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Schedule cron events
     */
    private static function schedule_cron_events() {
        // Schedule daily analytics update
        if (!wp_next_scheduled('aima_daily_analytics_update')) {
            wp_schedule_event(time(), 'daily', 'aima_daily_analytics_update');
        }

        // Schedule hourly campaign check
        if (!wp_next_scheduled('aima_hourly_campaign_check')) {
            wp_schedule_event(time(), 'hourly', 'aima_hourly_campaign_check');
        }

        // Schedule segment update
        if (!wp_next_scheduled('aima_update_segments')) {
            wp_schedule_event(time(), 'twicedaily', 'aima_update_segments');
        }
    }
}
