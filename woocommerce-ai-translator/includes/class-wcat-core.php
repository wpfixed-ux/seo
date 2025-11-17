<?php
/**
 * The core plugin class
 *
 * @package WC_AI_Translator
 */

class WCAT_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks
     */
    protected $loader;

    /**
     * The unique identifier of this plugin
     */
    protected $plugin_name;

    /**
     * The current version of the plugin
     */
    protected $version;

    /**
     * Initialize the core plugin
     */
    public function __construct() {
        $this->plugin_name = 'wc-ai-translator';
        $this->version = WCAT_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin
     */
    private function load_dependencies() {
        // Loader class
        require_once WCAT_PLUGIN_DIR . 'includes/class-wcat-loader.php';
        $this->loader = new WCAT_Loader();

        // API classes
        require_once WCAT_PLUGIN_DIR . 'includes/api/class-wcat-openai.php';

        // Translator classes
        require_once WCAT_PLUGIN_DIR . 'includes/translators/class-wcat-polylang-translator.php';
        require_once WCAT_PLUGIN_DIR . 'includes/translators/class-wcat-content-translator.php';
        require_once WCAT_PLUGIN_DIR . 'includes/translators/class-wcat-woocommerce-translator.php';
        require_once WCAT_PLUGIN_DIR . 'includes/translators/class-wcat-seo-translator.php';

        // Processor classes
        require_once WCAT_PLUGIN_DIR . 'includes/processors/class-wcat-queue-manager.php';
        require_once WCAT_PLUGIN_DIR . 'includes/processors/class-wcat-batch-processor.php';
        require_once WCAT_PLUGIN_DIR . 'includes/processors/class-wcat-content-scanner.php';

        // Model classes
        require_once WCAT_PLUGIN_DIR . 'includes/models/class-wcat-translation-log.php';
        require_once WCAT_PLUGIN_DIR . 'includes/models/class-wcat-translation-queue.php';

        // Admin classes
        require_once WCAT_PLUGIN_DIR . 'includes/admin/class-wcat-admin.php';
        require_once WCAT_PLUGIN_DIR . 'includes/admin/class-wcat-settings.php';
        require_once WCAT_PLUGIN_DIR . 'includes/admin/class-wcat-metaboxes.php';
        require_once WCAT_PLUGIN_DIR . 'includes/admin/class-wcat-ajax-handler.php';
    }

    /**
     * Define the locale for this plugin for internationalization
     */
    private function set_locale() {
        $this->loader->add_action('plugins_loaded', $this, 'load_plugin_textdomain');
    }

    /**
     * Load the plugin text domain for translation
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wc-ai-translator',
            false,
            dirname(WCAT_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Register all admin-related hooks
     */
    private function define_admin_hooks() {
        $admin = new WCAT_Admin($this->plugin_name, $this->version);
        $settings = new WCAT_Settings($this->plugin_name, $this->version);
        $metaboxes = new WCAT_Metaboxes($this->plugin_name, $this->version);
        $ajax_handler = new WCAT_Ajax_Handler($this->plugin_name, $this->version);

        // Enqueue admin scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');

        // Add admin menu
        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');

        // Register settings
        $this->loader->add_action('admin_init', $settings, 'register_settings');

        // Add metaboxes
        $this->loader->add_action('add_meta_boxes', $metaboxes, 'add_translation_metaboxes');
        $this->loader->add_action('save_post', $metaboxes, 'save_translation_metabox');

        // AJAX handlers
        $this->loader->add_action('wp_ajax_wcat_scan_content', $ajax_handler, 'scan_content');
        $this->loader->add_action('wp_ajax_wcat_batch_translate', $ajax_handler, 'batch_translate');
        $this->loader->add_action('wp_ajax_wcat_translate_single', $ajax_handler, 'translate_single');
        $this->loader->add_action('wp_ajax_wcat_preview_translation', $ajax_handler, 'preview_translation');
        $this->loader->add_action('wp_ajax_wcat_get_logs', $ajax_handler, 'get_logs');
        $this->loader->add_action('wp_ajax_wcat_get_queue_status', $ajax_handler, 'get_queue_status');
        $this->loader->add_action('wp_ajax_wcat_cancel_translation', $ajax_handler, 'cancel_translation');
        $this->loader->add_action('wp_ajax_wcat_clear_logs', $ajax_handler, 'clear_logs');
    }

    /**
     * Register all public-related hooks
     */
    private function define_public_hooks() {
        // Register cron jobs
        if (!wp_next_scheduled('wcat_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'wcat_process_queue');
        }

        if (!wp_next_scheduled('wcat_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wcat_cleanup_logs');
        }

        // Add custom cron schedule
        $this->loader->add_filter('cron_schedules', $this, 'add_cron_schedules');

        // Hook into queue processing
        $batch_processor = new WCAT_Batch_Processor();
        $this->loader->add_action('wcat_process_queue', $batch_processor, 'process_queue');

        // Hook into log cleanup
        $translation_log = new WCAT_Translation_Log();
        $this->loader->add_action('wcat_cleanup_logs', $translation_log, 'cleanup_old_logs');
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'wc-ai-translator')
        );
        return $schedules;
    }

    /**
     * Run the loader to execute all of the hooks
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Get the plugin name
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Get the loader
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Get the version number
     */
    public function get_version() {
        return $this->version;
    }
}
