<?php
/**
 * AI Image Generator
 *
 * @package           AIImageGenerator
 * @author            Your Name
 * @copyright         2025 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AI Image Generator
 * Plugin URI:        https://example.com/ai-image-generator
 * Description:       AI-powered image generation for products and articles using Hugging Face Stable Diffusion XL. Completely FREE!
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * Text Domain:       ai-image-generator
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('AI_IMAGE_GENERATOR_VERSION', '1.0.0');

/**
 * Plugin paths and URLs
 */
define('AIMG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIMG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIMG_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AIMG_INCLUDES_DIR', AIMG_PLUGIN_DIR . 'includes/');
define('AIMG_ASSETS_URL', AIMG_PLUGIN_URL . 'assets/');

/**
 * The code that runs during plugin activation.
 */
function activate_ai_image_generator() {
    // Set default options
    $default_options = array(
        'huggingface_api_key' => '',
        'default_model' => 'stabilityai/stable-diffusion-xl-base-1.0'
    );

    add_option('aimg_settings', $default_options);
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ai_image_generator() {
    // Cleanup if needed
}

register_activation_hook(__FILE__, 'activate_ai_image_generator');
register_deactivation_hook(__FILE__, 'deactivate_ai_image_generator');

/**
 * The core plugin class.
 */
require AIMG_INCLUDES_DIR . 'class-aimg-core.php';

/**
 * Begins execution of the plugin.
 */
function run_ai_image_generator() {
    $plugin = new AIMG_Core();
    $plugin->run();
}

run_ai_image_generator();
