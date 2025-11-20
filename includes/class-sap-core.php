<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes
 */

class SAP_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      SAP_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = SEO_ANALYTICS_PRO_VERSION;
        $this->plugin_name = 'seo-analytics-pro';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_api_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the core plugin.
         */
        require_once SAP_INCLUDES_DIR . 'class-sap-loader.php';

        /**
         * Admin-specific functionality
         */
        require_once SAP_INCLUDES_DIR . 'admin/class-sap-admin.php';
        require_once SAP_INCLUDES_DIR . 'admin/class-sap-settings.php';

        /**
         * API classes
         */
        require_once SAP_INCLUDES_DIR . 'api/class-sap-rest-api.php';
        require_once SAP_INCLUDES_DIR . 'api/class-sap-claude-ai.php';
        require_once SAP_INCLUDES_DIR . 'api/class-sap-gemini-ai.php';
        require_once SAP_INCLUDES_DIR . 'api/class-sap-scraper.php';
        require_once SAP_INCLUDES_DIR . 'api/class-sap-serp.php';

        /**
         * Analyzer classes
         */
        require_once SAP_INCLUDES_DIR . 'analyzers/class-sap-keyword-analyzer.php';
        require_once SAP_INCLUDES_DIR . 'analyzers/class-sap-competitor-analyzer.php';
        require_once SAP_INCLUDES_DIR . 'analyzers/class-sap-content-analyzer.php';
        require_once SAP_INCLUDES_DIR . 'analyzers/class-sap-strategy-generator.php';
        require_once SAP_INCLUDES_DIR . 'analyzers/class-sap-lsi-extractor.php';

        /**
         * Processor classes
         */
        require_once SAP_INCLUDES_DIR . 'processors/class-sap-batch-processor.php';
        require_once SAP_INCLUDES_DIR . 'processors/class-sap-scheduler.php';
        require_once SAP_INCLUDES_DIR . 'processors/class-sap-queue-manager.php';

        /**
         * Exporter classes
         */
        require_once SAP_INCLUDES_DIR . 'exporters/class-sap-csv-exporter.php';
        require_once SAP_INCLUDES_DIR . 'exporters/class-sap-json-exporter.php';

        /**
         * Generator classes
         */
        require_once SAP_INCLUDES_DIR . 'generators/class-sap-content-generator.php';
        require_once SAP_INCLUDES_DIR . 'generators/class-sap-image-generator.php';

        /**
         * Model classes
         */
        require_once SAP_INCLUDES_DIR . 'models/class-sap-project.php';
        require_once SAP_INCLUDES_DIR . 'models/class-sap-keyword.php';
        require_once SAP_INCLUDES_DIR . 'models/class-sap-competitor.php';
        require_once SAP_INCLUDES_DIR . 'models/class-sap-report.php';

        $this->loader = new SAP_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new SAP_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');

        // Register metaboxes for content generation
        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'register_metaboxes');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_term_metaboxes');

        // Save metabox data
        $this->loader->add_action('save_post', $plugin_admin, 'save_metabox_data');
        $this->loader->add_action('created_term', $plugin_admin, 'save_term_meta', 10, 3);
        $this->loader->add_action('edited_term', $plugin_admin, 'save_term_meta', 10, 3);

        // AJAX handlers
        $this->loader->add_action('wp_ajax_sap_analyze_competitor', $plugin_admin, 'ajax_analyze_competitor');
        $this->loader->add_action('wp_ajax_sap_analyze_keyword', $plugin_admin, 'ajax_analyze_keyword');
        $this->loader->add_action('wp_ajax_sap_generate_content_spec', $plugin_admin, 'ajax_generate_content_spec');
        $this->loader->add_action('wp_ajax_sap_import_keywords', $plugin_admin, 'ajax_import_keywords');

        // Metabox AJAX handlers
        $this->loader->add_action('wp_ajax_sap_generate_metabox_content', $plugin_admin, 'ajax_generate_metabox_content');
        $this->loader->add_action('wp_ajax_sap_generate_term_content', $plugin_admin, 'ajax_generate_term_content');

        // Image generator
        $image_generator = new SAP_Image_Generator();
        $image_generator->init();
    }

    /**
     * Register all of the hooks related to the API functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_api_hooks() {
        $plugin_api = new SAP_REST_API();

        $this->loader->add_action('rest_api_init', $plugin_api, 'register_routes');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks.
     *
     * @since     1.0.0
     * @return    SAP_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
