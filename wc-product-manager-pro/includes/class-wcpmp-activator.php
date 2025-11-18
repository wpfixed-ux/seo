<?php
/**
 * Plugin activator
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Activator {

    public static function activate() {
        self::create_tables();
        self::create_options();
        self::schedule_crons();
        self::create_capabilities();
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Products table (main product repository)
        $table_products = $wpdb->prefix . 'wcpmp_products';
        $sql_products = "CREATE TABLE $table_products (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wc_product_id bigint(20) unsigned DEFAULT NULL,
            sku varchar(100) NOT NULL,
            name_uk varchar(500) NOT NULL,
            name_ru varchar(500) DEFAULT NULL,
            description_uk longtext,
            description_ru longtext,
            seo_description_uk text,
            seo_description_ru text,
            price decimal(10,2) NOT NULL DEFAULT 0,
            sale_price decimal(10,2) DEFAULT NULL,
            stock_quantity int(11) NOT NULL DEFAULT 0,
            stock_status varchar(50) DEFAULT 'instock',
            category_id bigint(20) unsigned DEFAULT NULL,
            images longtext,
            attributes longtext,
            sync_enabled tinyint(1) DEFAULT 1,
            last_synced datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sku (sku),
            KEY wc_product_id (wc_product_id),
            KEY category_id (category_id),
            KEY stock_status (stock_status)
        ) $charset_collate;";
        dbDelta($sql_products);

        // Stores configuration
        $table_stores = $wpdb->prefix . 'wcpmp_stores';
        $sql_stores = "CREATE TABLE $table_stores (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            api_url varchar(500) NOT NULL,
            api_key varchar(500) NOT NULL,
            api_secret varchar(500) DEFAULT NULL,
            settings longtext,
            is_active tinyint(1) DEFAULT 1,
            last_sync datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_stores);

        // Product-Store mapping (which products are in which stores)
        $table_product_stores = $wpdb->prefix . 'wcpmp_product_stores';
        $sql_product_stores = "CREATE TABLE $table_product_stores (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            store_id bigint(20) unsigned NOT NULL,
            external_id varchar(100) DEFAULT NULL,
            price decimal(10,2) DEFAULT NULL,
            sale_price decimal(10,2) DEFAULT NULL,
            stock_quantity int(11) DEFAULT 0,
            is_published tinyint(1) DEFAULT 0,
            sync_enabled tinyint(1) DEFAULT 1,
            last_synced datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_store (product_id, store_id),
            KEY store_id (store_id),
            KEY external_id (external_id)
        ) $charset_collate;";
        dbDelta($sql_product_stores);

        // Categories with sync settings
        $table_categories = $wpdb->prefix . 'wcpmp_categories';
        $sql_categories = "CREATE TABLE $table_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wc_term_id bigint(20) unsigned DEFAULT NULL,
            name_uk varchar(255) NOT NULL,
            name_ru varchar(255) DEFAULT NULL,
            parent_id bigint(20) unsigned DEFAULT NULL,
            sync_mode varchar(50) DEFAULT 'async',
            sync_enabled tinyint(1) DEFAULT 1,
            prom_category_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wc_term_id (wc_term_id),
            KEY parent_id (parent_id),
            KEY sync_mode (sync_mode)
        ) $charset_collate;";
        dbDelta($sql_categories);

        // Orders from all stores
        $table_orders = $wpdb->prefix . 'wcpmp_orders';
        $sql_orders = "CREATE TABLE $table_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            store_id bigint(20) unsigned NOT NULL,
            external_order_id varchar(100) NOT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            status varchar(50) NOT NULL,
            total decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'UAH',
            customer_name varchar(255),
            customer_email varchar(255),
            customer_phone varchar(50),
            shipping_address text,
            order_date datetime NOT NULL,
            items longtext,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY store_order (store_id, external_order_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY order_date (order_date)
        ) $charset_collate;";
        dbDelta($sql_orders);

        // Order items for inventory tracking
        $table_order_items = $wpdb->prefix . 'wcpmp_order_items';
        $sql_order_items = "CREATE TABLE $table_order_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            sku varchar(100) NOT NULL,
            quantity int(11) NOT NULL,
            price decimal(10,2) NOT NULL,
            total decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY sku (sku)
        ) $charset_collate;";
        dbDelta($sql_order_items);

        // CRM Customers
        $table_customers = $wpdb->prefix . 'wcpmp_customers';
        $sql_customers = "CREATE TABLE $table_customers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100),
            last_name varchar(100),
            phone varchar(50),
            total_orders int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0,
            average_order decimal(10,2) DEFAULT 0,
            last_order_date datetime DEFAULT NULL,
            segments longtext,
            preferences longtext,
            language varchar(10) DEFAULT 'uk',
            subscribed tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY total_spent (total_spent),
            KEY last_order_date (last_order_date)
        ) $charset_collate;";
        dbDelta($sql_customers);

        // CRM Segments
        $table_segments = $wpdb->prefix . 'wcpmp_segments';
        $sql_segments = "CREATE TABLE $table_segments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            conditions longtext NOT NULL,
            customer_count int(11) DEFAULT 0,
            is_dynamic tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_segments);

        // Email Campaigns
        $table_campaigns = $wpdb->prefix . 'wcpmp_campaigns';
        $sql_campaigns = "CREATE TABLE $table_campaigns (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject_uk varchar(500) NOT NULL,
            subject_ru varchar(500),
            content_uk longtext NOT NULL,
            content_ru longtext,
            segment_id bigint(20) unsigned DEFAULT NULL,
            coupon_code varchar(100) DEFAULT NULL,
            discount_type varchar(50) DEFAULT NULL,
            discount_value decimal(10,2) DEFAULT NULL,
            status varchar(50) DEFAULT 'draft',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            sent_count int(11) DEFAULT 0,
            open_count int(11) DEFAULT 0,
            click_count int(11) DEFAULT 0,
            ai_generated tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY segment_id (segment_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        dbDelta($sql_campaigns);

        // Telegram bot conversations
        $table_telegram = $wpdb->prefix . 'wcpmp_telegram_chats';
        $sql_telegram = "CREATE TABLE $table_telegram (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            chat_id varchar(100) NOT NULL,
            user_name varchar(255),
            store_id bigint(20) unsigned DEFAULT NULL,
            language varchar(10) DEFAULT 'uk',
            context longtext,
            last_message datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY chat_id (chat_id),
            KEY store_id (store_id)
        ) $charset_collate;";
        dbDelta($sql_telegram);

        // Knowledge base for bot
        $table_knowledge = $wpdb->prefix . 'wcpmp_knowledge_base';
        $sql_knowledge = "CREATE TABLE $table_knowledge (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(500) NOT NULL,
            content longtext NOT NULL,
            category varchar(100),
            tags varchar(500),
            embedding longtext,
            language varchar(10) DEFAULT 'uk',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY language (language)
        ) $charset_collate;";
        dbDelta($sql_knowledge);

        // Sync logs
        $table_sync_logs = $wpdb->prefix . 'wcpmp_sync_logs';
        $sql_sync_logs = "CREATE TABLE $table_sync_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            store_id bigint(20) unsigned NOT NULL,
            sync_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            items_processed int(11) DEFAULT 0,
            items_failed int(11) DEFAULT 0,
            error_log longtext,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY sync_type (sync_type),
            KEY started_at (started_at)
        ) $charset_collate;";
        dbDelta($sql_sync_logs);

        // API logs
        $table_api_logs = $wpdb->prefix . 'wcpmp_api_logs';
        $sql_api_logs = "CREATE TABLE $table_api_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service varchar(100) NOT NULL,
            endpoint varchar(500) NOT NULL,
            method varchar(10) NOT NULL,
            request_data longtext,
            response_data longtext,
            status_code int(11),
            execution_time float,
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service (service),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_api_logs);

        update_option('wcpmp_db_version', WCPMP_VERSION);
    }

    private static function create_options() {
        $defaults = array(
            'wcpmp_openai_api_key' => '',
            'wcpmp_openai_model' => 'gpt-4o',
            'wcpmp_gemini_api_key' => '',
            'wcpmp_telegram_bot_token' => '',
            'wcpmp_default_language' => 'uk',
            'wcpmp_sync_interval' => 'hourly',
            'wcpmp_global_sync_enabled' => 1,
            'wcpmp_orders_fetch_interval' => 'hourly',
            'wcpmp_email_from_name' => get_bloginfo('name'),
            'wcpmp_email_from_address' => get_option('admin_email'),
            'wcpmp_seo_description_length' => 160,
            'wcpmp_ai_temperature' => 0.7,
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    private static function schedule_crons() {
        if (!wp_next_scheduled('wcpmp_sync_inventory_cron')) {
            wp_schedule_event(time(), 'hourly', 'wcpmp_sync_inventory_cron');
        }
        if (!wp_next_scheduled('wcpmp_fetch_orders_cron')) {
            wp_schedule_event(time(), 'hourly', 'wcpmp_fetch_orders_cron');
        }
        if (!wp_next_scheduled('wcpmp_process_campaigns_cron')) {
            wp_schedule_event(time(), 'every_fifteen_minutes', 'wcpmp_process_campaigns_cron');
        }
    }

    private static function create_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_wcpmp');
            $admin->add_cap('manage_wcpmp_products');
            $admin->add_cap('manage_wcpmp_stores');
            $admin->add_cap('manage_wcpmp_crm');
            $admin->add_cap('manage_wcpmp_settings');
        }
    }
}
