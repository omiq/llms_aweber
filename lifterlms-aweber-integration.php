<?php
/*
Plugin Name: LifterLMS AWeber Integration
Description: Adds users to a specific LifterLMS membership and subscribes them to an AWeber newsletter upon registration or membership enrollment.
Version: 2.1
Author: Chris Garrett
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/aweber-authentication.php';
require_once plugin_dir_path(__FILE__) . 'includes/user-subscription.php';

// Register settings
add_action('admin_init', 'llms_aweber_integration_settings_init');

// Add a menu item for AWeber settings
add_action('admin_menu', 'llms_aweber_integration_menu');

// Handle the authorization code input from the user
add_action('admin_post_save_aweber_auth_code', 'llms_aweber_exchange_code_for_tokens');

// Test AWeber Credentials AJAX handler
add_action('wp_ajax_test_aweber_credentials', 'test_aweber_credentials');

// Revoke tokens on uninstallation
register_uninstall_hook(__FILE__, 'llms_aweber_integration_uninstall');
?>
