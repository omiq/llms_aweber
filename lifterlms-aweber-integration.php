<?php
/*
Plugin Name: LifterLMS AWeber Integration
Description: Adds users to a specific LifterLMS membership and subscribes them to an AWeber newsletter upon registration.
Version: 1.7
Author: Chris Garrett
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add a menu item for AWeber settings
add_action('admin_menu', 'llms_aweber_integration_menu');

function llms_aweber_integration_menu()
{
    add_options_page(
        'LifterLMS AWeber Integration Settings',
        'LifterLMS AWeber',
        'manage_options',
        'llms-aweber-integration',
        'llms_aweber_integration_options_page'
    );
}

// Register settings
add_action('admin_init', 'llms_aweber_integration_settings_init');

function llms_aweber_integration_settings_init()
{
    register_setting('llms_aweber_integration_settings', 'llms_aweber_client_id');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_list_id');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_account_id');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_code_verifier');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_access_token');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_refresh_token');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_token_expiry');
    register_setting('llms_aweber_integration_settings', 'llms_aweber_auth_code');

    add_settings_section(
        'llms_aweber_integration_section',
        'AWeber API Settings',
        'llms_aweber_integration_section_callback',
        'llms-aweber-integration'
    );

    add_settings_field(
        'llms_aweber_client_id',
        'AWeber Client ID',
        'llms_aweber_client_id_render',
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

    add_settings_field(
        'llms_aweber_authorize_button',
        'Authorize with AWeber',
        'llms_aweber_authorize_button_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );

    add_settings_field(
        'llms_aweber_auth_code',
        'AWeber Authorization Code',
        'llms_aweber_auth_code_render',
        'llms-aweber-integration',
        'llms_aweber_integration_section'
    );
}

function llms_aweber_integration_section_callback()
{
    echo 'Enter your AWeber API settings below:';
}

function llms_aweber_client_id_render()
{
    $value = get_option('llms_aweber_client_id', '');
    echo '<input type="text" name="llms_aweber_client_id" value="' . esc_attr($value) . '" />';
}

function llms_aweber_list_id_render()
{
    $value = get_option('llms_aweber_list_id', '');
    echo '<input type="text" name="llms_aweber_list_id" value="' . esc_attr($value) . '" />';
}

function llms_aweber_account_id_render()
{
    $value = get_option('llms_aweber_account_id', '');
    echo '<input type="text" name="llms_aweber_account_id" value="' . esc_attr($value) . '" />';
}

function llms_aweber_authorize_button_render()
{
    $authorize_url = llms_get_aweber_authorize_url();
    echo '<a href="' . esc_url($authorize_url) . '" class="button button-primary" target="_blank">Authorize with AWeber</a>';
    echo '<p class="description">Click the button above to authorize with AWeber. Copy the authorization code and paste it below.</p>';
}

function llms_aweber_auth_code_render()
{
    $value = get_option('llms_aweber_auth_code', '');
    echo '<input type="text" name="llms_aweber_auth_code" value="' . esc_attr($value) . '" />';
    echo '<p class="description">Paste the authorization code obtained from AWeber here and save settings.</p>';
}

function llms_get_aweber_authorize_url()
{
    $client_id = get_option('llms_aweber_client_id');
    $redirect_uri = 'urn:ietf:wg:oauth:2.0:oob';
    $code_verifier = base64_encode(random_bytes(32));
    $code_challenge = str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode(hash('sha256', $code_verifier, true)));

    update_option('llms_aweber_code_verifier', $code_verifier);

    $authorize_url = 'https://auth.aweber.com/oauth2/authorize?' . http_build_query(array(
        'response_type' => 'code',
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => 'account.read list.read list.write',
        'state' => wp_create_nonce('aweber_auth'),
        'code_challenge' => $code_challenge,
        'code_challenge_method' => 'S256',
    ));

    return $authorize_url;
}

// Define the settings page function
function llms_aweber_integration_options_page()
{
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
        <button id="test-aweber-credentials" class="button button-secondary">Test Credentials</button>
        <div id="test-aweber-credentials-result"></div>
    </div>
    <script type="text/javascript">
    document.getElementById('test-aweber-credentials').addEventListener('click', function() {
        var data = {
            'action': 'test_aweber_credentials'
        };
        jQuery.post(ajaxurl, data, function(response) {
            document.getElementById('test-aweber-credentials-result').innerHTML = response.data.message;
        });
    });
    </script>
    <?php
}

// Handle the authorization code input from the user
add_action('admin_post_save_aweber_auth_code', 'llms_aweber_exchange_code_for_tokens');

function llms_aweber_exchange_code_for_tokens()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['llms_aweber_auth_code']) && check_admin_referer('llms_aweber_save_auth_code', 'llms_aweber_nonce')) {
        $authorization_code = sanitize_text_field($_POST['llms_aweber_auth_code']);
        update_option('llms_aweber_auth_code', $authorization_code);

        $client_id = get_option('llms_aweber_client_id');
        $code_verifier = get_option('llms_aweber_code_verifier');
        $redirect_uri = 'urn:ietf:wg:oauth:2.0:oob';

        $response = wp_remote_post('https://auth.aweber.com/oauth2/token', array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'code' => $authorization_code,
                'redirect_uri' => $redirect_uri,
                'code_verifier' => $code_verifier,
            ),
        ));

        if (is_wp_error($response)) {
            error_log('AWeber token request failed: ' . $response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $tokens = json_decode($body, true);

        if (isset($tokens['access_token']) && isset($tokens['refresh_token']) && isset($tokens['expires_in'])) {
            update_option('llms_aweber_access_token', $tokens['access_token']);
            update_option('llms_aweber_refresh_token', $tokens['refresh_token']);
            update_option('llms_aweber_token_expiry', time() + $tokens['expires_in']);
        } else {
            error_log('AWeber token request failed: ' . $body);
        }
    }
    wp_redirect(admin_url('options-general.php?page=llms-aweber-integration'));
    exit;
}

// Enroll user and subscribe to AWeber
add_action('lifterlms_user_registered', 'custom_add_user_to_membership_and_aweber', 10, 1);

function custom_add_user_to_membership_and_aweber($user_id)
{
    $membership_id = get_option('llms_aweber_membership_id');
    $list_id = get_option('llms_aweber_list_id');

    if ($membership_id) {
        llms_enroll_student($user_id, $membership_id);
    }

    $user = get_userdata($user_id);
    $email = $user->user_email;
    $name = $user->display_name;

    subscribe_to_aweber($email, $name, $list_id);
}

function subscribe_to_aweber($email, $name, $list_id)
{
    $access_token = get_option('llms_aweber_access_token');

    if (is_access_token_expired()) {
        refresh_aweber_access_token();
        $access_token = get_option('llms_aweber_access_token');
    }

    $account_id = get_option('llms_aweber_account_id');
    $url = "https://api.aweber.com/1.0/accounts/$account_id/lists/$list_id/subscribers";

    $response = wp_remote_post($url, array(
        'headers' => array(
            "Authorization: Bearer $access_token",
            "Content-Type: application/json",
        ),
        'body' => json_encode(array(
            'ws.op' => 'create',
            'email' => $email,
            'name' => $name,
        )),
    ));

    if (is_wp_error($response)) {
        error_log('AWeber subscription failed: ' . $response->get_error_message());
    } else {
        $body = wp_remote_retrieve_body($response);
        error_log('AWeber subscription response: ' . $body);
    }
}

function is_access_token_expired()
{
    $expiry_time = get_option('llms_aweber_token_expiry');
    return time() > $expiry_time;
}

function refresh_aweber_access_token()
{
    $client_id = get_option('llms_aweber_client_id');
    $refresh_token = get_option('llms_aweber_refresh_token');

    $response = wp_remote_post('https://auth.aweber.com/oauth2/token', array(
        'body' => array(
            'grant_type' => 'refresh_token',
            'client_id' => $client_id,
            'refresh_token' => $refresh_token,
            'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        ),
    ));

    if (is_wp_error($response)) {
        error_log('AWeber token refresh failed: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $tokens = json_decode($body, true);

    if (isset($tokens['access_token']) && isset($tokens['refresh_token']) && isset($tokens['expires_in'])) {
        update_option('llms_aweber_access_token', $tokens['access_token']);
        update_option('llms_aweber_refresh_token', $tokens['refresh_token']);
        update_option('llms_aweber_token_expiry', time() + $tokens['expires_in']);
    } else {
        error_log('AWeber token refresh response is missing required fields: ' . $body);
    }
}

// Test AWeber Credentials AJAX handler
add_action('wp_ajax_test_aweber_credentials', 'test_aweber_credentials');

function test_aweber_credentials()
{
    $client_id = get_option('llms_aweber_client_id');
    $refresh_token = get_option('llms_aweber_refresh_token');

    // Debugging: Log the values being retrieved
    error_log('Client ID: ' . $client_id);
    error_log('Refresh Token: ' . $refresh_token);

    if (empty($client_id) || empty($refresh_token)) {
        wp_send_json_error(array('message' => 'Missing AWeber credentials. Please fill in all fields.'));
    }

    $url = "https://auth.aweber.com/oauth2/token";

    $data = array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
    );

    $response = wp_remote_post($url, array(
        'body' => http_build_query($data),
        'headers' => array(
            "Content-Type: application/x-www-form-urlencoded",
        ),
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'AWeber credentials test failed: ' . $response->get_error_message()));
    } else {
        $body = wp_remote_retrieve_body($response);
        $tokens = json_decode($body, true);

        if (isset($tokens['access_token']) && isset($tokens['refresh_token']) && isset($tokens['expires_in'])) {
            wp_send_json_success(array('message' => 'AWeber credentials are valid.'));
        } else {
            wp_send_json_error(array('message' => 'AWeber credentials test failed: ' . $body));
        }
    }
}

// Revoke tokens on uninstallation
register_uninstall_hook(__FILE__, 'llms_aweber_integration_uninstall');

function llms_aweber_integration_uninstall()
{
    $client_id = get_option('llms_aweber_client_id');
    $refresh_token = get_option('llms_aweber_refresh_token');

    wp_remote_post('https://auth.aweber.com/oauth2/revoke', array(
        'body' => array(
            'client_id' => $client_id,
            'token' => $refresh_token,
        ),
    ));

    delete_option('llms_aweber_access_token');
    delete_option('llms_aweber_refresh_token');
    delete_option('llms_aweber_token_expiry');
}
?>
