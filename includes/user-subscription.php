<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into user registration and membership enrollment
add_action('lifterlms_user_registered', 'custom_add_user_to_membership_and_aweber', 10, 1);
add_action('llms_user_enrolled_in_membership', 'custom_add_existing_user_to_aweber', 10, 2);

function custom_add_user_to_membership_and_aweber($user_id)
{
    $membership_id = get_option('llms_aweber_membership_id');
    $list_id = get_option('llms_aweber_list_id');

    if ($membership_id) {
        llms_enroll_student($user_id, $membership_id);
    }

    subscribe_user_to_aweber($user_id, $list_id);
}

function custom_add_existing_user_to_aweber($user_id, $membership_id)
{
    $specified_membership_id = get_option('llms_aweber_membership_id');
    $list_id = get_option('llms_aweber_list_id');

    if ($membership_id == $specified_membership_id) {
        subscribe_user_to_aweber($user_id, $list_id);
    }
}

function subscribe_user_to_aweber($user_id, $list_id)
{
    $user = get_userdata($user_id);
    $email = $user->user_email;
    $name = $user->display_name;

    $access_token = get_option('llms_aweber_access_token');

    if (is_access_token_expired()) {
        refresh_aweber_access_token();
        $access_token = get_option('llms_aweber_access_token');
    }

    $account_id = get_option('llms_aweber_account_id');
    $url = "https://api.aweber.com/1.0/accounts/$account_id/lists/$list_id/subscribers";

    $response = wp_remote_post($url, array(
        'headers' => array(
            "Authorization" => "Bearer $access_token",
            "Content-Type" => "application/json",
        ),
        'body' => json_encode(array(
            'ws.op' => 'create',
            'email' => $email,
            'name' => $name,
        )),
    ));

    $result_message = '';
    if (is_wp_error($response)) {
        $result_message = 'AWeber subscription failed: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $result_message = 'AWeber subscription response: ' . $body;
    }

    set_transient('llms_aweber_subscription_message', $result_message, 30);
}

// Utility functions
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

    $result_message = '';
    if (is_wp_error($response)) {
        $result_message = 'AWeber token refresh failed: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $tokens = json_decode($body, true);

        if (isset($tokens['access_token']) && isset($tokens['refresh_token']) && isset($tokens['expires_in'])) {
            update_option('llms_aweber_access_token', $tokens['access_token']);
            update_option('llms_aweber_refresh_token', $tokens['refresh_token']);
            update_option('llms_aweber_token_expiry', time() + $tokens['expires_in']);
            $result_message = 'AWeber token refresh successful.';
        } else {
            $result_message = 'AWeber token refresh response is missing required fields: ' . $body;
        }
    }

    set_transient('llms_aweber_result_message', $result_message, 30);
}
?>
