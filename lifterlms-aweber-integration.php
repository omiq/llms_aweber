<?php
/*
Plugin Name: LifterLMS AWeber Integration
Description: Adds users to a specific LifterLMS membership and subscribes them to an AWeber newsletter upon registration.
Version: 1.1
Author: Chris Garrett
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Hook into LifterLMS user registration
add_action('lifterlms_user_registered', 'custom_add_user_to_membership_and_aweber', 10, 1);

function custom_add_user_to_membership_and_aweber($user_id) {
    $membership_id = get_option('llms_aweber_membership_id');
    $list_id = get_option('llms_aweber_list_id');

    // Add the user to a specific membership
    if ($membership_id) {
        llms_enroll_student($user_id, $membership_id);
    }

    // Subscribe the user to AWeber newsletter
    $user = get_userdata($user_id);
    $email = $user->user_email;
    $name = $user->display_name; // or $user->first_name . ' ' . $user->last_name;

    subscribe_to_aweber($email, $name, $list_id);
}

function subscribe_to_aweber($email, $name, $list_id) {
    // Your AWeber API credentials
    $consumer_key = get_option('llms_aweber_consumer_key');
    $consumer_secret = get_option('llms_aweber_consumer_secret');
    $access_token = get_option('aweber_access_token');
    $refresh_token = get_option('aweber_refresh_token');

    // Refresh access token if necessary
    if (is_access_token_expired()) {
        $tokens = refresh_aweber_access_token($consumer_key, $consumer_secret, $refresh_token);
        $access_token = $tokens['access_token'];
        $refresh_token = $tokens['refresh_token'];
    }

    // AWeber API URL
    $account_id = get_option('llms_aweber_account_id');
    $url = "https://api.aweber.com/1.0/accounts/$account_id/lists/$list_id/subscribers";

    // Subscriber data
    $data = array(
        'ws.op' => 'create',
        'email' => $email,
        'name' => $name,
    );

    // Set up OAuth authorization
    $headers = array(
        "Authorization: Bearer $access_token",
        "Content-Type: application/json",
    );

    // Send request to AWeber API
    $response = wp_remote_post($url, array(
        'headers' => $headers,
        'body' => json_encode($data),
    ));

    // Handle response
    if (is_wp_error($response)) {
        error_log('AWeber subscription failed: ' . $response->get_error_message());
    } else {
        $body = wp_remote_retrieve_body($response);
        error_log('AWeber subscription response: ' . $body);
    }
}

function is_access_token_expired() {
    $expiry_time = get_option('aweber_access_token_expiry');
    return time() > $expiry_time;
}

function refresh_aweber_access_token($consumer_key, $consumer_secret, $refresh_token) {
    // AWeber token refresh URL
    $url = "https://auth.aweber.com/oauth2/token";

    // Data for token refresh request
    $data = array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
    );

    // Set up Basic Authentication
    $auth = base64_encode("$consumer_key:$consumer_secret");
    $headers = array(
        "Authorization: Basic $auth",
        "Content-Type: application/x-www-form-urlencoded",
    );

    // Send request to AWeber API
    $response = wp_remote_post($url, array(
        'headers' => $headers,
        'body' => http_build_query($data),
    ));

    // Handle response
    if (is_wp_error($response)) {
        error_log('AWeber token refresh failed: ' . $response->get_error_message());
        return null;
    } else {
        $body = wp_remote_retrieve_body($response);
        $tokens = json_decode($body, true);

        // Update stored tokens
        update_option('aweber_access_token', $tokens['access_token']);
        update_option('aweber_refresh_token', $tokens['refresh_token']);
        update_option('aweber_access_token_expiry', time() + $tokens['expires_in']);

        return $tokens;
    }
}

// Admin menu
add_action('admin_menu', 'llms_aweber_integration_menu');

function llms_aweber_integration_menu() {
    add_options_page(
        'LifterLMS AWeber Integration Settings',
        'LifterLMS AWeber',
        'manage_options',
        'llms-aweber-integration',
        'llms_aweber_integration_options_page'
    );
}

// Settings page
function llms_aweber_integration_options_page() {
    ?>
    <div class="wrap">
        <h1>LifterLMS AWeber Integration Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('llms_aweber_integration_settings');
            do_settings_sections('llms-aweber-integration');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'llms_aweber_integration_settings_init');

function llms_aweber_integration_settings_init() {
    register_setting('llms_aweber_integration_settings', 'llms_aweber_membership_id');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_consumer_key');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_consumer_secret');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_list_id');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_account_id');

    add_settings_section(
        'llms_aweber_integration_section',
        'AWeber API Settings',
        'llms_aweber_integration_section_callback',
        'llms-aweber-integration'
    );

    add_settings_field(
        'llms_aweber_membership_id',
        'LifterLMS Membership ID',
        'llms_aweber_membership_id_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );

    add_settings_field(
        'llms_aweber_consumer_key',
        'AWeber Consumer Key',
        'llms_aweber_consumer_key_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );

    add_settings_field(
        'llms_aweber_consumer_secret',
        'AWeber Consumer Secret',
        'llms_aweber_consumer_secret_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );

    add_settings_field(
        'llms_aweber_list_id',
        'AWeber List ID',
        'llms_aweber_list_id_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );

    add_settings_field(
        'llms_aweber_account_id',
        'AWeber Account ID',
        'llms_aweber_account_id_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );
}

function llms_aweber_integration_section_callback() {
    echo 'Enter your AWeber API settings below:';
}

function llms_aweber_membership_id_render() {
    $value = get_option('llms_aweber_membership_id', '');
    echo '<input type="text" name="llms_aweber_membership_id" value="' . esc_attr($value) . '" />';
}

function llms_aweber_consumer_key_render() {
    $value = get_option('llms_aweber_consumer_key', '');
    echo '<input type="text" name="llms_aweber_consumer_key" value="' . esc_attr($value) . '" />';
}

function llms_aweber_consumer_secret_render() {
    $value = get_option('llms_aweber_consumer_secret', '');
    echo '<input type="text" name="llms_aweber_consumer_secret" value="' . esc_attr($value) . '" />';
}

function llms_aweber_list_id_render() {
    $value = get_option('llms_aweber_list_id', '');
    echo '<input type="text" name="llms_aweber_list_id" value="' . esc_attr($value) . '" />';
}

function llms_aweber_account_id_render() {
    $value = get_option('llms_aweber_account_id', '');
    echo '<input type="text" name="llms_aweber_account_id" value="' . esc_attr($value) . '" />';
}
?>
