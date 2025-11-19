<?php

/**
 * Plugin Name: Post Status Changer
 * Description: Change post statuses or move posts to trash in bulk using a CSV file or by searching posts with a regex pattern (title, slug, content, or excerpt). Includes a Post Actions select and regex search for advanced bulk actions. Compatible with most WordPress versions as it uses standard WP APIs.
 * Version: 1.2.0
 * Author: Sam Clark
 * Text Domain: post-status-changer
 */

/**
 * Changelog
 *
 * 1.2.0
 *   - Added regex search feature for bulk post actions (title, slug, content, excerpt)
 *   - UI improvements for regex search field selection
 *   - Context-aware field options for media/attachments
 *
 * 1.1.0
 *   - Added ability to move posts to trash in bulk
 *   - Added Post Actions select for status change or trash
 *   - Removed password protection functionality
 *
 * 1.0.0
 *   - Initial release: Bulk change post statuses using a CSV file of slugs
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSC_VERSION', '1.2.0');

// Include required files
require_once PSC_PLUGIN_DIR . 'includes/class-post-status-changer.php';
require_once PSC_PLUGIN_DIR . 'includes/class-post-status-processor.php';

// Initialize the plugin
function psc_initialize_plugin()
{
    $plugin = new Post_Status_Changer();
    $plugin->init();
}
add_action('plugins_loaded', 'psc_initialize_plugin');
