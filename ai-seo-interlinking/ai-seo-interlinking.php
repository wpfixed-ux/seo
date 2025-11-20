<?php
/**
 * Plugin Name: AI SEO Interlinking
 * Plugin URI: https://github.com/your-repo/ai-seo-interlinking
 * Description: Автоматическая внутренняя перелинковка для мультиязычных сайтов WooCommerce с использованием AI (OpenAI API)
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-seo-interlinking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AIL_VERSION', '1.0.0');
define('AIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIL_PLUGIN_BASENAME', plugin_basename(__FILE__));

// SEO Rules Constants
define('AIL_MAX_KEYWORD_DENSITY', 3);           // Max keyword density %
define('AIL_MIN_WORDS_BETWEEN_LINKS', 100);     // Min words between links
define('AIL_EXACT_MATCH_LIMIT', 30);            // Exact match limit %
define('AIL_NOFOLLOW_THRESHOLD', 5);            // Threshold for nofollow

/**
 * Plugin activation hook
 */
function ail_activate_plugin() {
    require_once AIL_PLUGIN_DIR . 'includes/class-activator.php';
    AIL_Activator::activate();
}
register_activation_hook(__FILE__, 'ail_activate_plugin');

/**
 * Plugin deactivation hook
 */
function ail_deactivate_plugin() {
    require_once AIL_PLUGIN_DIR . 'includes/class-deactivator.php';
    AIL_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'ail_deactivate_plugin');

/**
 * Initialize the plugin
 */
function ail_init() {
    // Load plugin text domain
    load_plugin_textdomain(
        'ai-seo-interlinking',
        false,
        dirname(AIL_PLUGIN_BASENAME) . '/languages'
    );

    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ail_woocommerce_missing_notice');
        return;
    }

    // Load required files
    require_once AIL_PLUGIN_DIR . 'includes/class-logger.php';
    require_once AIL_PLUGIN_DIR . 'includes/class-ai-processor.php';
    require_once AIL_PLUGIN_DIR . 'includes/class-link-builder.php';
    require_once AIL_PLUGIN_DIR . 'includes/class-scheduler.php';
    require_once AIL_PLUGIN_DIR . 'includes/class-multilang.php';
    require_once AIL_PLUGIN_DIR . 'includes/class-admin.php';

    // Initialize admin interface
    if (is_admin()) {
        AIL_Admin::get_instance();
    }

    // Initialize scheduler
    AIL_Scheduler::get_instance();
}
add_action('plugins_loaded', 'ail_init');

/**
 * WooCommerce missing notice
 */
function ail_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('AI SEO Interlinking requires WooCommerce to be installed and active.', 'ai-seo-interlinking'); ?></p>
    </div>
    <?php
}

/**
 * Add settings link on plugin page
 */
function ail_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=ai-seo-interlinking') . '">' . __('Settings', 'ai-seo-interlinking') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . AIL_PLUGIN_BASENAME, 'ail_add_settings_link');

/**
 * Process single post on save
 */
function ail_process_single_post($post_id) {
    // Skip autosaves and revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // Check if auto-processing is enabled
    $settings = get_option('ail_settings', []);
    if (!isset($settings['auto_process_on_save']) || !$settings['auto_process_on_save']) {
        return;
    }

    // Process the post
    $link_builder = new AIL_Link_Builder();
    $link_builder->build_links($post_id);
}
add_action('save_post', 'ail_process_single_post', 10, 1);

/**
 * Get plugin version
 */
function ail_get_version() {
    return AIL_VERSION;
}

/**
 * Check if plugin dependencies are met
 */
function ail_check_dependencies() {
    $dependencies = [
        'php_version' => version_compare(PHP_VERSION, '7.4', '>='),
        'wp_version' => version_compare(get_bloginfo('version'), '5.8', '>='),
        'woocommerce' => class_exists('WooCommerce'),
    ];

    return !in_array(false, $dependencies, true);
}
