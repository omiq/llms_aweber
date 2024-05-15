<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
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
        $result_message = 'AWeber subscription failed: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $result_message = 'AWeber subscription response: ' . $body;
    }

    set_transient('llms_aweber_subscription_message', $result_message, 30);
}
?>
