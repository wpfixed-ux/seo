<?php
/**
 * Uninstall Script
 * Fired when the plugin is uninstalled
 *
 * @package AI_SEO_Interlinking
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load deactivator class
require_once plugin_dir_path(__FILE__) . 'includes/class-deactivator.php';

// Run uninstall
AIL_Deactivator::uninstall();
