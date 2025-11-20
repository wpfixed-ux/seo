<?php
/**
 * The core plugin class
 *
 * @package AIMarketingAssistant
 */

class AIMA_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks
     */
    protected $loader;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once AIMA_INCLUDES_DIR . 'class-aima-loader.php';
        require_once AIMA_INCLUDES_DIR . 'class-aima-database.php';

        // Admin classes
        require_once AIMA_ADMIN_DIR . 'class-aima-admin.php';
        require_once AIMA_ADMIN_DIR . 'class-aima-admin-analytics.php';
        require_once AIMA_ADMIN_DIR . 'class-aima-admin-segments.php';
        require_once AIMA_ADMIN_DIR . 'class-aima-admin-offers.php';
        require_once AIMA_ADMIN_DIR . 'class-aima-admin-settings.php';
        require_once AIMA_ADMIN_DIR . 'class-aima-admin-import.php';

        // API classes
        require_once AIMA_API_DIR . 'class-aima-ai-client.php';
        require_once AIMA_API_DIR . 'class-aima-telegram-bot.php';

        // Module classes
        require_once AIMA_MODULES_DIR . 'class-aima-customer-analyzer.php';
        require_once AIMA_MODULES_DIR . 'class-aima-segmentation-engine.php';
        require_once AIMA_MODULES_DIR . 'class-aima-offer-generator.php';
        require_once AIMA_MODULES_DIR . 'class-aima-email-campaign.php';
        require_once AIMA_MODULES_DIR . 'class-aima-woocommerce-integration.php';
        require_once AIMA_MODULES_DIR . 'class-aima-trigger-system.php';
        require_once AIMA_MODULES_DIR . 'class-aima-email-queue.php';
        require_once AIMA_MODULES_DIR . 'class-aima-deduplication.php';
        require_once AIMA_MODULES_DIR . 'class-aima-data-cleanup.php';
        require_once AIMA_MODULES_DIR . 'class-aima-campaign-analytics.php';

        $this->loader = new AIMA_Loader();
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        $admin = new AIMA_Admin();

        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');

        // AJAX handlers
        $this->loader->add_action('wp_ajax_aima_import_csv', $admin, 'handle_csv_import');
        $this->loader->add_action('wp_ajax_aima_generate_offer', $admin, 'handle_generate_offer');
        $this->loader->add_action('wp_ajax_aima_send_campaign', $admin, 'handle_send_campaign');
        $this->loader->add_action('wp_ajax_aima_update_segment', $admin, 'handle_update_segment');
        $this->loader->add_action('wp_ajax_aima_test_api', $admin, 'handle_test_api');
        $this->loader->add_action('wp_ajax_aima_get_models', $admin, 'handle_get_models');
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks() {
        // Campaign analytics tracking
        $analytics = new AIMA_Campaign_Analytics();
        $this->loader->add_action('init', $analytics, 'handle_tracking_request');

        // WooCommerce integration
        if (class_exists('WooCommerce')) {
            $woo_integration = new AIMA_WooCommerce_Integration();

            $this->loader->add_action('woocommerce_new_order', $woo_integration, 'track_new_order', 10, 1);
            $this->loader->add_action('woocommerce_order_status_completed', $woo_integration, 'process_completed_order', 10, 1);
            $this->loader->add_action('woocommerce_thankyou', $woo_integration, 'show_telegram_subscription', 10, 1);
        }
    }

    /**
     * Register cron hooks
     */
    private function define_cron_hooks() {
        $analyzer = new AIMA_Customer_Analyzer();
        $segmentation = new AIMA_Segmentation_Engine();
        $campaign = new AIMA_Email_Campaign();
        $triggers = new AIMA_Trigger_System();
        $email_queue = new AIMA_Email_Queue();
        $cleanup = new AIMA_Data_Cleanup();

        // Existing hooks
        $this->loader->add_action('aima_daily_analytics_update', $analyzer, 'update_customer_analytics');
        $this->loader->add_action('aima_hourly_campaign_check', $campaign, 'process_scheduled_campaigns');
        $this->loader->add_action('aima_update_segments', $segmentation, 'update_all_segments');

        // New hooks
        $this->loader->add_action('aima_process_email_queue', $email_queue, 'process_queue');
        $this->loader->add_action('aima_check_triggers', $triggers, 'check_triggers');
        $this->loader->add_action('aima_cleanup_old_data', $cleanup, 'run_cleanup');
    }

    /**
     * Run the loader to execute all hooks
     */
    public function run() {
        $this->loader->run();
    }
}
