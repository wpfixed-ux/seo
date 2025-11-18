<?php
/**
 * Plugin Name: WooCommerce Product Manager Pro
 * Plugin URI: https://example.com/wc-product-manager-pro
 * Description: Comprehensive WooCommerce product management with AI-powered SEO descriptions, image generation, multi-marketplace publishing (Prom.ua, WooCommerce), inventory sync, CRM, and Telegram bot integration.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-product-manager-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WCPMP_VERSION', '1.0.0');
define('WCPMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCPMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCPMP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WCPMP_INCLUDES_DIR', WCPMP_PLUGIN_DIR . 'includes/');
define('WCPMP_ASSETS_URL', WCPMP_PLUGIN_URL . 'assets/');

// Load activator and deactivator early (needed for activation/deactivation hooks)
require_once WCPMP_INCLUDES_DIR . 'class-wcpmp-activator.php';
require_once WCPMP_INCLUDES_DIR . 'class-wcpmp-deactivator.php';

/**
 * Main plugin class
 */
final class WC_Product_Manager_Pro {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $loader;
    public $admin;
    public $api;
    public $openai;
    public $gemini;
    public $prom_ua;
    public $woo_api;
    public $sync;
    public $warehouse;
    public $crm;
    public $telegram;
    public $importer;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->init_components();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core
        require_once WCPMP_INCLUDES_DIR . 'class-wcpmp-loader.php';

        // Admin
        require_once WCPMP_INCLUDES_DIR . 'admin/class-wcpmp-admin.php';
        require_once WCPMP_INCLUDES_DIR . 'admin/class-wcpmp-settings.php';
        require_once WCPMP_INCLUDES_DIR . 'admin/class-wcpmp-products-table.php';
        require_once WCPMP_INCLUDES_DIR . 'admin/class-wcpmp-importer.php';

        // AI Integrations
        require_once WCPMP_INCLUDES_DIR . 'ai/class-wcpmp-openai.php';
        require_once WCPMP_INCLUDES_DIR . 'ai/class-wcpmp-gemini.php';

        // Marketplace Integrations
        require_once WCPMP_INCLUDES_DIR . 'integrations/class-wcpmp-prom-ua.php';
        require_once WCPMP_INCLUDES_DIR . 'integrations/class-wcpmp-woocommerce-api.php';
        require_once WCPMP_INCLUDES_DIR . 'integrations/class-wcpmp-store-manager.php';

        // Sync & Warehouse
        require_once WCPMP_INCLUDES_DIR . 'sync/class-wcpmp-sync-manager.php';
        require_once WCPMP_INCLUDES_DIR . 'sync/class-wcpmp-inventory-tracker.php';
        require_once WCPMP_INCLUDES_DIR . 'warehouse/class-wcpmp-warehouse.php';
        require_once WCPMP_INCLUDES_DIR . 'warehouse/class-wcpmp-orders.php';

        // CRM
        require_once WCPMP_INCLUDES_DIR . 'crm/class-wcpmp-crm.php';
        require_once WCPMP_INCLUDES_DIR . 'crm/class-wcpmp-segments.php';
        require_once WCPMP_INCLUDES_DIR . 'crm/class-wcpmp-email-campaigns.php';

        // Telegram Bot
        require_once WCPMP_INCLUDES_DIR . 'telegram/class-wcpmp-telegram-bot.php';
        require_once WCPMP_INCLUDES_DIR . 'telegram/class-wcpmp-knowledge-base.php';

        // API
        require_once WCPMP_INCLUDES_DIR . 'api/class-wcpmp-rest-api.php';

        $this->loader = new WCPMP_Loader();
    }

    /**
     * Set plugin locale
     */
    private function set_locale() {
        add_action('plugins_loaded', function() {
            load_plugin_textdomain(
                'wc-product-manager-pro',
                false,
                dirname(WCPMP_PLUGIN_BASENAME) . '/languages/'
            );
        });
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->admin = new WCPMP_Admin();
        $this->openai = new WCPMP_OpenAI();
        $this->gemini = new WCPMP_Gemini();
        $this->prom_ua = new WCPMP_Prom_UA();
        $this->woo_api = new WCPMP_WooCommerce_API();
        $this->sync = new WCPMP_Sync_Manager();
        $this->warehouse = new WCPMP_Warehouse();
        $this->crm = new WCPMP_CRM();
        $this->telegram = new WCPMP_Telegram_Bot();
        $this->api = new WCPMP_REST_API();
        $this->importer = new WCPMP_Importer();
    }

    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        // Admin menu and pages
        $this->loader->add_action('admin_menu', $this->admin, 'add_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
        $this->loader->add_action('admin_init', $this->admin, 'register_settings');

        // AJAX handlers
        $this->loader->add_action('wp_ajax_wcpmp_generate_seo_description', $this->openai, 'ajax_generate_description');
        $this->loader->add_action('wp_ajax_wcpmp_generate_image', $this->gemini, 'ajax_generate_image');
        $this->loader->add_action('wp_ajax_wcpmp_change_background', $this->gemini, 'ajax_change_background');
        $this->loader->add_action('wp_ajax_wcpmp_publish_to_prom', $this->prom_ua, 'ajax_publish_product');
        $this->loader->add_action('wp_ajax_wcpmp_publish_to_woo', $this->woo_api, 'ajax_publish_product');
        $this->loader->add_action('wp_ajax_wcpmp_sync_inventory', $this->sync, 'ajax_sync_inventory');
        $this->loader->add_action('wp_ajax_wcpmp_update_prices', $this->admin, 'ajax_update_prices');
        $this->loader->add_action('wp_ajax_wcpmp_send_campaign', $this->crm, 'ajax_send_campaign');
        $this->loader->add_action('wp_ajax_wcpmp_get_orders', $this->warehouse, 'ajax_get_orders');
        $this->loader->add_action('wp_ajax_wcpmp_get_inventory', $this->warehouse, 'ajax_get_inventory');

        // Bulk actions
        $this->loader->add_action('wp_ajax_wcpmp_bulk_generate_seo', $this->openai, 'ajax_bulk_generate');
        $this->loader->add_action('wp_ajax_wcpmp_bulk_publish', $this->admin, 'ajax_bulk_publish');
        $this->loader->add_action('wp_ajax_wcpmp_bulk_sync', $this->sync, 'ajax_bulk_sync');

        // Import handlers
        $this->loader->add_action('wp_ajax_wcpmp_import_woocommerce', $this->importer, 'ajax_import_woocommerce');
        $this->loader->add_action('wp_ajax_wcpmp_import_csv', $this->importer, 'ajax_import_csv');
        $this->loader->add_action('wp_ajax_wcpmp_import_store', $this->importer, 'ajax_import_store');

        // Cron jobs
        $this->loader->add_action('wcpmp_sync_inventory_cron', $this->sync, 'run_scheduled_sync');
        $this->loader->add_action('wcpmp_fetch_orders_cron', $this->warehouse, 'fetch_orders_from_stores');
        $this->loader->add_action('wcpmp_process_campaigns_cron', $this->crm, 'process_scheduled_campaigns');
    }

    /**
     * Define public hooks
     */
    private function define_public_hooks() {
        // REST API
        $this->loader->add_action('rest_api_init', $this->api, 'register_routes');

        // Telegram webhook
        $this->loader->add_action('init', $this->telegram, 'register_webhook_endpoint');

        // PolyLang integration
        $this->loader->add_filter('wcpmp_product_languages', $this, 'get_polylang_languages');
        $this->loader->add_action('pll_save_post', $this, 'sync_translations', 10, 3);
    }

    /**
     * Get PolyLang languages
     */
    public function get_polylang_languages($languages) {
        if (function_exists('pll_languages_list')) {
            return pll_languages_list(array('fields' => 'slug'));
        }
        return array('uk', 'ru'); // Default Ukrainian and Russian
    }

    /**
     * Sync translations when saved
     */
    public function sync_translations($post_id, $post, $translations) {
        if (get_post_type($post_id) === 'product') {
            do_action('wcpmp_translation_saved', $post_id, $translations);
        }
    }

    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }
}

/**
 * Activation hook
 */
register_activation_hook(__FILE__, array('WCPMP_Activator', 'activate'));

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, array('WCPMP_Deactivator', 'deactivate'));

/**
 * Get plugin instance
 */
function wcpmp() {
    return WC_Product_Manager_Pro::instance();
}

// Initialize plugin
add_action('plugins_loaded', function() {
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        wcpmp()->run();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            esc_html_e('WooCommerce Product Manager Pro requires WooCommerce to be installed and active.', 'wc-product-manager-pro');
            echo '</p></div>';
        });
    }
});
