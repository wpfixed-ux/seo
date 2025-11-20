<?php
/**
 * AI Marketing Assistant
 *
 * @package           AIMarketingAssistant
 * @author            Your Name
 * @copyright         2025 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AI Marketing Assistant
 * Plugin URI:        https://example.com/ai-marketing-assistant
 * Description:       AI-powered marketing CRM assistant that analyzes purchases, generates personalized offers, manages customer segmentation, and automates email/Telegram campaigns for WooCommerce stores.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * Text Domain:       ai-marketing-assistant
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://example.com/ai-marketing-assistant
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('AIMA_VERSION', '1.0.0');

/**
 * Plugin paths and URLs
 */
define('AIMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIMA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIMA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AIMA_INCLUDES_DIR', AIMA_PLUGIN_DIR . 'includes/');
define('AIMA_ASSETS_URL', AIMA_PLUGIN_URL . 'assets/');
define('AIMA_ADMIN_DIR', AIMA_INCLUDES_DIR . 'admin/');
define('AIMA_API_DIR', AIMA_INCLUDES_DIR . 'api/');
define('AIMA_MODULES_DIR', AIMA_INCLUDES_DIR . 'modules/');

/**
 * The code that runs during plugin activation.
 */
function activate_ai_marketing_assistant() {
    require_once AIMA_INCLUDES_DIR . 'class-aima-activator.php';
    AIMA_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ai_marketing_assistant() {
    require_once AIMA_INCLUDES_DIR . 'class-aima-deactivator.php';
    AIMA_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_ai_marketing_assistant');
register_deactivation_hook(__FILE__, 'deactivate_ai_marketing_assistant');

/**
 * The core plugin class.
 */
require AIMA_INCLUDES_DIR . 'class-aima-core.php';

/**
 * Begins execution of the plugin.
 */
function run_ai_marketing_assistant() {
    $plugin = new AIMA_Core();
    $plugin->run();
}

run_ai_marketing_assistant();
