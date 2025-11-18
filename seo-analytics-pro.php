<?php
/**
 * SEO Analytics Pro
 *
 * @package           SEOAnalyticsPro
 * @author            Your Name
 * @copyright         2025 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SEO Analytics Pro
 * Plugin URI:        https://example.com/seo-analytics-pro
 * Description:       AI-powered SEO analytics and content strategy tool that analyzes competitors, identifies optimal keywords, and generates detailed technical specifications for content creation using Claude AI.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * Text Domain:       seo-analytics-pro
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://example.com/seo-analytics-pro
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('SEO_ANALYTICS_PRO_VERSION', '1.0.0');

/**
 * Plugin paths and URLs
 */
define('SAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SAP_INCLUDES_DIR', SAP_PLUGIN_DIR . 'includes/');
define('SAP_ASSETS_URL', SAP_PLUGIN_URL . 'assets/');

/**
 * The code that runs during plugin activation.
 */
function activate_seo_analytics_pro() {
    require_once SAP_INCLUDES_DIR . 'class-sap-activator.php';
    SAP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_seo_analytics_pro() {
    require_once SAP_INCLUDES_DIR . 'class-sap-deactivator.php';
    SAP_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_seo_analytics_pro');
register_deactivation_hook(__FILE__, 'deactivate_seo_analytics_pro');

/**
 * The core plugin class.
 */
require SAP_INCLUDES_DIR . 'class-sap-core.php';

/**
 * Begins execution of the plugin.
 */
function run_seo_analytics_pro() {
    $plugin = new SAP_Core();
    $plugin->run();
}

run_seo_analytics_pro();
