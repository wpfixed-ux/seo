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
            gender varchar(10) DEFAULT NULL,
            birthday date DEFAULT NULL,
            total_orders int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            last_order_date datetime DEFAULT NULL,
            first_order_date datetime DEFAULT NULL,
            last_campaign_date datetime DEFAULT NULL,
            source varchar(50) DEFAULT 'woocommerce',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY customer_id (customer_id),
            KEY last_order_date (last_order_date),
            KEY birthday (birthday),
            KEY gender (gender)
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

        // Automated triggers table
        $table_triggers = $wpdb->prefix . 'aima_triggers';
        $sql_triggers = "CREATE TABLE IF NOT EXISTS $table_triggers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            trigger_type varchar(50) NOT NULL,
            conditions text DEFAULT NULL,
            offer_template text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            last_run datetime DEFAULT NULL,
            total_sent int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY trigger_type (trigger_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Personalized offers table
        $table_personalized = $wpdb->prefix . 'aima_personalized_offers';
        $sql_personalized = "CREATE TABLE IF NOT EXISTS $table_personalized (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            headline varchar(255) DEFAULT NULL,
            subheadline varchar(255) DEFAULT NULL,
            body text DEFAULT NULL,
            products text DEFAULT NULL,
            banner_url varchar(500) DEFAULT NULL,
            personalization_data text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY offer_id (offer_id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";

        // Email sending queue table
        $table_email_queue = $wpdb->prefix . 'aima_email_queue';
        $sql_email_queue = "CREATE TABLE IF NOT EXISTS $table_email_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            email varchar(100) NOT NULL,
            priority int(11) DEFAULT 5,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            status varchar(20) DEFAULT 'queued',
            scheduled_for datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_for (scheduled_for),
            KEY priority (priority)
        ) $charset_collate;";

        // Order deduplication tracking table
        $table_order_hashes = $wpdb->prefix . 'aima_order_hashes';
        $sql_order_hashes = "CREATE TABLE IF NOT EXISTS $table_order_hashes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_hash varchar(64) NOT NULL,
            customer_email varchar(100) NOT NULL,
            order_date datetime NOT NULL,
            source varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY order_hash (order_hash),
            KEY customer_email (customer_email),
            KEY order_date (order_date)
        ) $charset_collate;";

        // Campaign analytics table - aggregate stats per campaign
        $table_campaign_analytics = $wpdb->prefix . 'aima_campaign_analytics';
        $sql_campaign_analytics = "CREATE TABLE IF NOT EXISTS $table_campaign_analytics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id bigint(20) UNSIGNED NOT NULL,
            sent_count int(11) DEFAULT 0,
            delivered_count int(11) DEFAULT 0,
            opened_count int(11) DEFAULT 0,
            unique_opens int(11) DEFAULT 0,
            clicked_count int(11) DEFAULT 0,
            unique_clicks int(11) DEFAULT 0,
            converted_count int(11) DEFAULT 0,
            total_revenue decimal(10,2) DEFAULT 0.00,
            open_rate decimal(5,2) DEFAULT 0.00,
            click_rate decimal(5,2) DEFAULT 0.00,
            conversion_rate decimal(5,2) DEFAULT 0.00,
            roi decimal(10,2) DEFAULT 0.00,
            revenue_per_email decimal(10,2) DEFAULT 0.00,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY offer_id (offer_id)
        ) $charset_collate;";

        // Tracking links table - each link in campaign gets unique tracking code
        $table_tracking_links = $wpdb->prefix . 'aima_tracking_links';
        $sql_tracking_links = "CREATE TABLE IF NOT EXISTS $table_tracking_links (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id bigint(20) UNSIGNED NOT NULL,
            tracking_code varchar(32) NOT NULL,
            original_url text NOT NULL,
            link_text varchar(255) DEFAULT NULL,
            click_count int(11) DEFAULT 0,
            unique_clicks int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tracking_code (tracking_code),
            KEY offer_id (offer_id)
        ) $charset_collate;";

        // Click tracking table - individual click events
        $table_click_tracking = $wpdb->prefix . 'aima_click_tracking';
        $sql_click_tracking = "CREATE TABLE IF NOT EXISTS $table_click_tracking (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tracking_code varchar(32) NOT NULL,
            customer_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referer text DEFAULT NULL,
            clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tracking_code (tracking_code),
            KEY customer_id (customer_id),
            KEY clicked_at (clicked_at)
        ) $charset_collate;";

        // Conversion tracking table - purchases attributed to campaigns
        $table_conversion_tracking = $wpdb->prefix . 'aima_conversion_tracking';
        $sql_conversion_tracking = "CREATE TABLE IF NOT EXISTS $table_conversion_tracking (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED NOT NULL,
            order_total decimal(10,2) DEFAULT 0.00,
            commission decimal(10,2) DEFAULT 0.00,
            attribution_type varchar(20) DEFAULT 'last_click',
            tracking_code varchar(32) DEFAULT NULL,
            converted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY offer_id (offer_id),
            KEY customer_id (customer_id),
            KEY order_id (order_id),
            KEY converted_at (converted_at)
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
        dbDelta($sql_triggers);
        dbDelta($sql_personalized);
        dbDelta($sql_email_queue);
        dbDelta($sql_order_hashes);
        dbDelta($sql_campaign_analytics);
        dbDelta($sql_tracking_links);
        dbDelta($sql_click_tracking);
        dbDelta($sql_conversion_tracking);
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
            'aima_emails_per_hour' => 20,
            'aima_personalize_offers' => 1,
            'aima_enable_triggers' => 1,
            'aima_data_retention_days' => 365,
            'aima_enable_deduplication' => 1,
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        // Create default triggers
        self::create_default_triggers();
    }

    /**
     * Create default automated triggers
     */
    private static function create_default_triggers() {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_triggers';

        // Check if triggers already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($existing > 0) {
            return;
        }

        // Birthday trigger
        $wpdb->insert($table, array(
            'name' => 'День рождения',
            'trigger_type' => 'birthday',
            'conditions' => json_encode(array('days_before' => 0)),
            'offer_template' => json_encode(array(
                'discount' => 15,
                'message_template' => 'С Днем Рождения! Персональная скидка {discount}% на любимые товары!'
            )),
            'is_active' => 1
        ));

        // Inactivity trigger
        $wpdb->insert($table, array(
            'name' => 'Неактивность 30 дней',
            'trigger_type' => 'inactivity',
            'conditions' => json_encode(array('days_inactive' => 30)),
            'offer_template' => json_encode(array(
                'discount' => 10,
                'message_template' => 'Мы скучали! Специально для вас скидка {discount}%'
            )),
            'is_active' => 1
        ));

        // Women's Day (March 8)
        $wpdb->insert($table, array(
            'name' => '8 Марта',
            'trigger_type' => 'holiday',
            'conditions' => json_encode(array(
                'date' => '03-08',
                'gender' => 'female',
                'days_before' => 3
            )),
            'offer_template' => json_encode(array(
                'discount' => 20,
                'message_template' => 'С праздником 8 Марта! Специальное предложение для наших прекрасных клиенток!'
            )),
            'is_active' => 1
        ));

        // New Year
        $wpdb->insert($table, array(
            'name' => 'Новый Год',
            'trigger_type' => 'holiday',
            'conditions' => json_encode(array(
                'date' => '12-25',
                'days_before' => 7
            )),
            'offer_template' => json_encode(array(
                'discount' => 25,
                'message_template' => 'Новогодняя распродажа! Скидки до {discount}% на весь ассортимент!'
            )),
            'is_active' => 1
        ));
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

        // Schedule email queue processing (every 3 minutes for throttling)
        if (!wp_next_scheduled('aima_process_email_queue')) {
            wp_schedule_event(time(), 'aima_three_minutes', 'aima_process_email_queue');
        }

        // Schedule trigger check (daily)
        if (!wp_next_scheduled('aima_check_triggers')) {
            wp_schedule_event(time(), 'daily', 'aima_check_triggers');
        }

        // Schedule data cleanup (weekly)
        if (!wp_next_scheduled('aima_cleanup_old_data')) {
            wp_schedule_event(time(), 'weekly', 'aima_cleanup_old_data');
        }

        // Add custom cron schedule for 3 minutes
        add_filter('cron_schedules', function($schedules) {
            $schedules['aima_three_minutes'] = array(
                'interval' => 180,
                'display' => __('Every 3 Minutes', 'ai-marketing-assistant')
            );
            return $schedules;
        });
    }
}
