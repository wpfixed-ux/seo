<?php
/**
 * The core plugin class.
 *
 * @package    AIImageGenerator
 * @subpackage AIImageGenerator/includes
 */

class AIMG_Core {

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->version = AI_IMAGE_GENERATOR_VERSION;
        $this->plugin_name = 'ai-image-generator';

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        /**
         * API classes
         */
        require_once AIMG_INCLUDES_DIR . 'api/class-aimg-huggingface.php';

        /**
         * Admin-specific functionality
         */
        require_once AIMG_INCLUDES_DIR . 'admin/class-aimg-admin.php';

        /**
         * Generator classes
         */
        require_once AIMG_INCLUDES_DIR . 'generators/class-aimg-image-generator.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $plugin_admin = new AIMG_Admin($this->get_plugin_name(), $this->get_version());

        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        add_action('admin_init', array($plugin_admin, 'register_settings'));

        // Image generator
        $image_generator = new AIMG_Image_Generator();
        $image_generator->init();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        // Plugin is now running
    }

    /**
     * The name of the plugin used to uniquely identify it.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
