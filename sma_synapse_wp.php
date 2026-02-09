<?php

/**
 * Plugin Name: SynapseWP - AI Assistant
 * Description: An AI assistant for categorization and writing.
 * Version: 1.1.0
 * Author: Syed Muhammad Arieb
 * License: GPLv2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SYNAPSEWP_VERSION', '1.1.0');
define('SYNAPSEWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SYNAPSEWP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core files
require_once SYNAPSEWP_PLUGIN_DIR . 'includes/class-synapsewp-settings.php';
require_once SYNAPSEWP_PLUGIN_DIR . 'includes/class-synapsewp-api.php';
require_once SYNAPSEWP_PLUGIN_DIR . 'includes/class-synapsewp-writer.php';
require_once SYNAPSEWP_PLUGIN_DIR . 'includes/class-synapsewp-ui.php';

// Initialize the plugin
function synapsewp_init()
{
    new SynapseWP_Settings();
    new SynapseWP_Writer();
    new SynapseWP_UI();
}

add_action('plugins_loaded', 'synapsewp_init');
