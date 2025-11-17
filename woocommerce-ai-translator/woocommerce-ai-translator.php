<?php
/**
 * Plugin Name: WooCommerce AI Translator
 * Plugin URI: https://example.com/woocommerce-ai-translator
 * Description: Automatic translation of WooCommerce websites using Polylang and OpenAI API. Translate posts, pages, products, categories, attributes, tags, alt tags, and SEO tags with AI-powered batch translation.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-translator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, polylang
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WCAT_VERSION', '1.0.0');
define('WCAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCAT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_wc_ai_translator() {
    require_once WCAT_PLUGIN_DIR . 'includes/class-wcat-activator.php';
    WCAT_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wc_ai_translator() {
    require_once WCAT_PLUGIN_DIR . 'includes/class-wcat-deactivator.php';
    WCAT_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wc_ai_translator');
register_deactivation_hook(__FILE__, 'deactivate_wc_ai_translator');

/**
 * The core plugin class
 */
require WCAT_PLUGIN_DIR . 'includes/class-wcat-core.php';

/**
 * Begins execution of the plugin.
 */
function run_wc_ai_translator() {
    $plugin = new WCAT_Core();
    $plugin->run();
}

run_wc_ai_translator();
