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
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
