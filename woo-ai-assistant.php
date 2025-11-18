<?php
/**
 * Plugin Name: WooCommerce AI Sales Assistant
 * Plugin URI: https://example.com/woo-ai-assistant
 * Description: AI-powered sales assistant for WooCommerce with vector database for intelligent product recommendations
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woo-ai-assistant
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
define('WAA_VERSION', '1.0.0');
define('WAA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WAA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WAA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function waa_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo esc_html__('WooCommerce AI Sales Assistant requires WooCommerce to be installed and active.', 'woo-ai-assistant');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Initialize the plugin
 */
function waa_init() {
    // Load text domain
    load_plugin_textdomain('woo-ai-assistant', false, dirname(WAA_PLUGIN_BASENAME) . '/languages');

    // Check WooCommerce
    if (!waa_check_woocommerce()) {
        return;
    }

    // Load required files
    require_once WAA_PLUGIN_DIR . 'includes/class-waa-core.php';

    // Initialize core
    WAA_Core::get_instance();
}
add_action('plugins_loaded', 'waa_init');

/**
 * Activation hook
 */
function waa_activate() {
    require_once WAA_PLUGIN_DIR . 'includes/class-waa-activator.php';
    WAA_Activator::activate();
}
register_activation_hook(__FILE__, 'waa_activate');

/**
 * Deactivation hook
 */
function waa_deactivate() {
    require_once WAA_PLUGIN_DIR . 'includes/class-waa-deactivator.php';
    WAA_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'waa_deactivate');

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
