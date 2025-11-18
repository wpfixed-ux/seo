<?php
/**
 * Fired during plugin activation
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes
 */

class SAP_Activator {

    /**
     * Activate the plugin.
     *
     * Creates database tables, sets default options, and schedules cron jobs.
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create projects table
        $table_projects = $wpdb->prefix . 'sap_projects';
        $sql_projects = "CREATE TABLE IF NOT EXISTS $table_projects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            target_website VARCHAR(500) NOT NULL,
            status ENUM('active', 'paused', 'completed', 'archived') DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            settings JSON,
            INDEX user_id_idx (user_id),
            INDEX status_idx (status)
        ) $charset_collate;";

        // Create competitors table
        $table_competitors = $wpdb->prefix . 'sap_competitors';
        $sql_competitors = "CREATE TABLE IF NOT EXISTS $table_competitors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            website_url VARCHAR(500) NOT NULL,
            domain_authority INT,
            page_authority INT,
            backlinks_count INT,
            last_analyzed DATETIME,
            status ENUM('pending', 'analyzing', 'completed', 'failed') DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            INDEX project_id_idx (project_id)
        ) $charset_collate;";

        // Create keywords table
        $table_keywords = $wpdb->prefix . 'sap_keywords';
        $sql_keywords = "CREATE TABLE IF NOT EXISTS $table_keywords (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            keyword VARCHAR(500) NOT NULL,
            source ENUM('manual', 'competitor', 'serp', 'imported') DEFAULT 'manual',
            search_volume INT,
            competition_score DECIMAL(5,2),
            difficulty_score DECIMAL(5,2),
            cpc DECIMAL(10,2),
            priority_score DECIMAL(5,2),
            status ENUM('pending', 'analyzed', 'selected', 'rejected') DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX project_id_idx (project_id),
            INDEX keyword_idx (keyword(191)),
            INDEX priority_idx (priority_score)
        ) $charset_collate;";

        // Create SERP results table
        $table_serp = $wpdb->prefix . 'sap_serp_results';
        $sql_serp = "CREATE TABLE IF NOT EXISTS $table_serp (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            keyword_id BIGINT UNSIGNED NOT NULL,
            position INT NOT NULL,
            url VARCHAR(1000) NOT NULL,
            title TEXT,
            description TEXT,
            domain VARCHAR(500),
            analyzed_at DATETIME NOT NULL,
            metrics JSON,
            INDEX keyword_id_idx (keyword_id),
            INDEX position_idx (position)
        ) $charset_collate;";

        // Create content specs table
        $table_content_specs = $wpdb->prefix . 'sap_content_specs';
        $sql_content_specs = "CREATE TABLE IF NOT EXISTS $table_content_specs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            keyword_id BIGINT UNSIGNED,
            content_type ENUM('article', 'product', 'category', 'page') NOT NULL,
            title TEXT NOT NULL,
            target_keywords JSON NOT NULL,
            lsi_keywords JSON,
            recommended_length INT,
            target_readability VARCHAR(50),
            structure JSON,
            competitors_analysis JSON,
            ai_prompt TEXT,
            technical_specs JSON,
            status ENUM('draft', 'ready', 'in_progress', 'completed') DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX project_id_idx (project_id),
            INDEX content_type_idx (content_type),
            INDEX status_idx (status)
        ) $charset_collate;";

        // Create analysis reports table
        $table_reports = $wpdb->prefix . 'sap_analysis_reports';
        $sql_reports = "CREATE TABLE IF NOT EXISTS $table_reports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            report_type ENUM('keyword', 'competitor', 'serp', 'strategy') NOT NULL,
            data JSON NOT NULL,
            summary TEXT,
            ai_insights TEXT,
            created_at DATETIME NOT NULL,
            INDEX project_id_idx (project_id),
            INDEX report_type_idx (report_type)
        ) $charset_collate;";

        // Create batch jobs table
        $table_batch = $wpdb->prefix . 'sap_batch_jobs';
        $sql_batch = "CREATE TABLE IF NOT EXISTS $table_batch (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            job_type VARCHAR(100) NOT NULL,
            total_items INT NOT NULL,
            processed_items INT DEFAULT 0,
            failed_items INT DEFAULT 0,
            status ENUM('queued', 'processing', 'paused', 'completed', 'failed') DEFAULT 'queued',
            priority INT DEFAULT 5,
            scheduled_at DATETIME,
            started_at DATETIME,
            completed_at DATETIME,
            error_log TEXT,
            settings JSON,
            INDEX project_id_idx (project_id),
            INDEX status_idx (status),
            INDEX scheduled_at_idx (scheduled_at)
        ) $charset_collate;";

        // Create API logs table
        $table_api_logs = $wpdb->prefix . 'sap_api_logs';
        $sql_api_logs = "CREATE TABLE IF NOT EXISTS $table_api_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_name VARCHAR(100) NOT NULL,
            endpoint VARCHAR(500),
            request_data JSON,
            response_data JSON,
            status_code INT,
            execution_time DECIMAL(10,4),
            tokens_used INT,
            cost DECIMAL(10,6),
            created_at DATETIME NOT NULL,
            INDEX service_name_idx (service_name),
            INDEX created_at_idx (created_at)
        ) $charset_collate;";

        // ===========================================
        // CRAFT CATALOG CHATBOT TABLES
        // ===========================================

        // Create producers table (craft producers/sellers)
        $table_producers = $wpdb->prefix . 'sap_producers';
        $sql_producers = "CREATE TABLE IF NOT EXISTS $table_producers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED,
            telegram_id VARCHAR(100),
            name VARCHAR(255) NOT NULL,
            description TEXT,
            contact_phone VARCHAR(50),
            contact_email VARCHAR(255),
            contact_telegram VARCHAR(100),
            contact_viber VARCHAR(50),
            location VARCHAR(255),
            city VARCHAR(100),
            region VARCHAR(100),
            delivery_info TEXT,
            working_hours VARCHAR(255),
            logo_url VARCHAR(500),
            website VARCHAR(500),
            status ENUM('pending', 'active', 'suspended', 'deleted') DEFAULT 'pending',
            verification_code VARCHAR(50),
            verified_at DATETIME,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            metadata JSON,
            INDEX user_id_idx (user_id),
            INDEX telegram_id_idx (telegram_id),
            INDEX status_idx (status),
            INDEX city_idx (city),
            FULLTEXT INDEX name_desc_ft (name, description)
        ) $charset_collate;";

        // Create product categories table
        $table_categories = $wpdb->prefix . 'sap_product_categories';
        $sql_categories = "CREATE TABLE IF NOT EXISTS $table_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            icon VARCHAR(100),
            sort_order INT DEFAULT 0,
            products_count INT DEFAULT 0,
            status ENUM('active', 'hidden') DEFAULT 'active',
            created_at DATETIME NOT NULL,
            INDEX parent_id_idx (parent_id),
            INDEX slug_idx (slug),
            INDEX sort_order_idx (sort_order)
        ) $charset_collate;";

        // Create catalog products table
        $table_catalog_products = $wpdb->prefix . 'sap_catalog_products';
        $sql_catalog_products = "CREATE TABLE IF NOT EXISTS $table_catalog_products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            producer_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(500) NOT NULL,
            description TEXT,
            short_description VARCHAR(500),
            price DECIMAL(10,2),
            price_unit VARCHAR(50) DEFAULT 'шт',
            min_order_qty DECIMAL(10,2) DEFAULT 1,
            in_stock BOOLEAN DEFAULT TRUE,
            stock_quantity INT,
            sku VARCHAR(100),
            images JSON,
            attributes JSON,
            tags VARCHAR(500),
            views_count INT DEFAULT 0,
            inquiries_count INT DEFAULT 0,
            status ENUM('active', 'out_of_stock', 'hidden', 'deleted') DEFAULT 'active',
            featured BOOLEAN DEFAULT FALSE,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX producer_id_idx (producer_id),
            INDEX category_id_idx (category_id),
            INDEX status_idx (status),
            INDEX price_idx (price),
            INDEX featured_idx (featured),
            FULLTEXT INDEX product_search_ft (name, description, tags)
        ) $charset_collate;";

        // Create chat sessions table
        $table_chat_sessions = $wpdb->prefix . 'sap_chat_sessions';
        $sql_chat_sessions = "CREATE TABLE IF NOT EXISTS $table_chat_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED,
            producer_id BIGINT UNSIGNED,
            telegram_chat_id VARCHAR(100),
            platform ENUM('web', 'telegram') DEFAULT 'web',
            user_type ENUM('buyer', 'producer', 'guest') DEFAULT 'guest',
            current_intent VARCHAR(100),
            context JSON,
            last_message_at DATETIME NOT NULL,
            messages_count INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            expires_at DATETIME,
            INDEX session_id_idx (session_id),
            INDEX user_id_idx (user_id),
            INDEX producer_id_idx (producer_id),
            INDEX telegram_chat_id_idx (telegram_chat_id),
            INDEX platform_idx (platform),
            INDEX last_message_at_idx (last_message_at)
        ) $charset_collate;";

        // Create chat messages table
        $table_chat_messages = $wpdb->prefix . 'sap_chat_messages';
        $sql_chat_messages = "CREATE TABLE IF NOT EXISTS $table_chat_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id BIGINT UNSIGNED NOT NULL,
            role ENUM('user', 'assistant', 'system') NOT NULL,
            content TEXT NOT NULL,
            intent VARCHAR(100),
            entities JSON,
            created_at DATETIME NOT NULL,
            INDEX session_id_idx (session_id),
            INDEX role_idx (role),
            INDEX created_at_idx (created_at)
        ) $charset_collate;";

        // Create product inquiries table (buyer requests)
        $table_inquiries = $wpdb->prefix . 'sap_product_inquiries';
        $sql_inquiries = "CREATE TABLE IF NOT EXISTS $table_inquiries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            producer_id BIGINT UNSIGNED NOT NULL,
            session_id BIGINT UNSIGNED,
            buyer_name VARCHAR(255),
            buyer_phone VARCHAR(50),
            buyer_telegram VARCHAR(100),
            message TEXT,
            quantity DECIMAL(10,2),
            status ENUM('new', 'viewed', 'contacted', 'completed', 'cancelled') DEFAULT 'new',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX product_id_idx (product_id),
            INDEX producer_id_idx (producer_id),
            INDEX status_idx (status),
            INDEX created_at_idx (created_at)
        ) $charset_collate;";

        // Execute all table creation queries
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_projects);
        dbDelta($sql_competitors);
        dbDelta($sql_keywords);
        dbDelta($sql_serp);
        dbDelta($sql_content_specs);
        dbDelta($sql_reports);
        dbDelta($sql_batch);
        dbDelta($sql_api_logs);

        // Execute catalog chatbot tables
        dbDelta($sql_producers);
        dbDelta($sql_categories);
        dbDelta($sql_catalog_products);
        dbDelta($sql_chat_sessions);
        dbDelta($sql_chat_messages);
        dbDelta($sql_inquiries);

        // Insert default product categories
        self::insert_default_categories();

        // Set default options
        add_option('sap_version', SEO_ANALYTICS_PRO_VERSION);
        add_option('sap_activation_date', current_time('mysql'));

        // Set default settings
        $default_settings = array(
            'claude_ai_api_key' => '',
            'serp_api_key' => '',
            'serp_api_provider' => 'serpapi',
            'keyword_api_provider' => '',
            'keyword_api_key' => '',
            'max_serp_results' => 20,
            'max_competitors' => 10,
            'enable_scheduled_tasks' => false,
            'enable_api' => false,
            'api_rate_limit' => 100,
        );
        add_option('sap_settings', $default_settings);

        // Set chatbot settings
        $chatbot_settings = array(
            'enabled' => true,
            'telegram_bot_token' => '',
            'telegram_bot_username' => '',
            'webhook_secret' => wp_generate_password(32, false),
            'welcome_message' => 'Привет! Я помогу найти крафтовые товары или зарегистрировать вас как производителя. Что вас интересует?',
            'producer_approval_required' => true,
            'max_products_per_producer' => 100,
            'session_timeout_minutes' => 30,
            'enable_pdf_export' => true,
            'enable_excel_import' => true,
        );
        add_option('sap_chatbot_settings', $chatbot_settings);

        // Schedule cron jobs (disabled by default)
        if (!wp_next_scheduled('sap_daily_tasks')) {
            wp_schedule_event(time(), 'daily', 'sap_daily_tasks');
        }

        if (!wp_next_scheduled('sap_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'sap_process_queue');
        }

        // Set user capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_sap_projects');
            $admin_role->add_cap('view_sap_analytics');
            $admin_role->add_cap('export_sap_data');
            // Catalog chatbot capabilities
            $admin_role->add_cap('manage_sap_catalog');
            $admin_role->add_cap('manage_sap_producers');
            $admin_role->add_cap('view_sap_chatbot');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Insert default product categories for craft catalog.
     *
     * @since    1.0.0
     */
    private static function insert_default_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_product_categories';

        // Check if categories already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }

        $now = current_time('mysql');

        $categories = array(
            // Main categories
            array('name' => 'Молочные продукты', 'slug' => 'dairy', 'icon' => '🧀', 'sort_order' => 1),
            array('name' => 'Мёд и продукты пчеловодства', 'slug' => 'honey', 'icon' => '🍯', 'sort_order' => 2),
            array('name' => 'Мясные изделия', 'slug' => 'meat', 'icon' => '🥩', 'sort_order' => 3),
            array('name' => 'Хлеб и выпечка', 'slug' => 'bakery', 'icon' => '🍞', 'sort_order' => 4),
            array('name' => 'Сладости и конфеты', 'slug' => 'sweets', 'icon' => '🍬', 'sort_order' => 5),
            array('name' => 'Напитки', 'slug' => 'drinks', 'icon' => '🍹', 'sort_order' => 6),
            array('name' => 'Консервация', 'slug' => 'canned', 'icon' => '🫙', 'sort_order' => 7),
            array('name' => 'Орехи и сухофрукты', 'slug' => 'nuts', 'icon' => '🥜', 'sort_order' => 8),
            array('name' => 'Масла', 'slug' => 'oils', 'icon' => '🫒', 'sort_order' => 9),
            array('name' => 'Специи и приправы', 'slug' => 'spices', 'icon' => '🌶️', 'sort_order' => 10),
            array('name' => 'Чай и травы', 'slug' => 'tea', 'icon' => '🍵', 'sort_order' => 11),
            array('name' => 'Овощи и фрукты', 'slug' => 'produce', 'icon' => '🥕', 'sort_order' => 12),
            array('name' => 'Другое', 'slug' => 'other', 'icon' => '📦', 'sort_order' => 99),
        );

        foreach ($categories as $cat) {
            $wpdb->insert($table, array(
                'parent_id' => 0,
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'description' => '',
                'icon' => $cat['icon'],
                'sort_order' => $cat['sort_order'],
                'products_count' => 0,
                'status' => 'active',
                'created_at' => $now,
            ));

            $parent_id = $wpdb->insert_id;

            // Add subcategories for some main categories
            if ($cat['slug'] === 'dairy') {
                $subcats = array(
                    array('name' => 'Сыры', 'slug' => 'cheese'),
                    array('name' => 'Молоко', 'slug' => 'milk'),
                    array('name' => 'Творог', 'slug' => 'cottage-cheese'),
                    array('name' => 'Сметана', 'slug' => 'sour-cream'),
                    array('name' => 'Масло', 'slug' => 'butter'),
                    array('name' => 'Йогурты', 'slug' => 'yogurt'),
                );
                foreach ($subcats as $i => $subcat) {
                    $wpdb->insert($table, array(
                        'parent_id' => $parent_id,
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'description' => '',
                        'icon' => '',
                        'sort_order' => $i + 1,
                        'products_count' => 0,
                        'status' => 'active',
                        'created_at' => $now,
                    ));
                }
            } elseif ($cat['slug'] === 'meat') {
                $subcats = array(
                    array('name' => 'Колбасы', 'slug' => 'sausages'),
                    array('name' => 'Копчёности', 'slug' => 'smoked'),
                    array('name' => 'Паштеты', 'slug' => 'pate'),
                    array('name' => 'Вяленое мясо', 'slug' => 'dried-meat'),
                );
                foreach ($subcats as $i => $subcat) {
                    $wpdb->insert($table, array(
                        'parent_id' => $parent_id,
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'description' => '',
                        'icon' => '',
                        'sort_order' => $i + 1,
                        'products_count' => 0,
                        'status' => 'active',
                        'created_at' => $now,
                    ));
                }
            } elseif ($cat['slug'] === 'sweets') {
                $subcats = array(
                    array('name' => 'Шоколад', 'slug' => 'chocolate'),
                    array('name' => 'Конфеты', 'slug' => 'candies'),
                    array('name' => 'Пастила', 'slug' => 'pastila'),
                    array('name' => 'Зефир', 'slug' => 'zephyr'),
                    array('name' => 'Варенье', 'slug' => 'jam'),
                );
                foreach ($subcats as $i => $subcat) {
                    $wpdb->insert($table, array(
                        'parent_id' => $parent_id,
                        'name' => $subcat['name'],
                        'slug' => $subcat['slug'],
                        'description' => '',
                        'icon' => '',
                        'sort_order' => $i + 1,
                        'products_count' => 0,
                        'status' => 'active',
                        'created_at' => $now,
                    ));
                }
            }
        }
    }
}
