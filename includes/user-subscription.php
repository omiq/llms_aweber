<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into user registration and membership enrollment
add_action('lifterlms_user_registered', 'custom_add_user_to_membership_and_aweber', 10, 3);
add_action('llms_user_added_to_membership_level', 'custom_add_existing_user_to_aweber', 10, 2);

function custom_add_user_to_membership_and_aweber($user_id)
{
    $membership_id = get_option('llms_membership_id', '');
    $list_id = get_option('llms_aweber_list_id');


    error_log( 'User registered ' . $user_id . ' adding to membership ' . $membership_id );

    if ($membership_id) {
        llms_enroll_student($user_id, $membership_id);
    }
    
    subscribe_user_to_aweber($user_id, $list_id);
}

function custom_add_existing_user_to_aweber($user_id, $membership_id)
{



    $specified_membership_id = get_option('llms_membership_id', '');

    $list_id = get_option('llms_aweber_list_id');

    if ($membership_id == $specified_membership_id) {
        error_log( 'User ' . $user_id . ' requested to be added to membership ' . $membership_id . ' and matches selected membership in admin, adding to list: ' . $list_id);
        subscribe_user_to_aweber($user_id, $list_id);
    }
    else
    {
        error_log( 'User ' . $user_id . ' requested to be added to membership ' . $membership_id . ' BUT DOES NOT selected membership in admin ('. $specified_membership_id.'), can not add to list: ' . $list_id);
        
    }

}
function subscribe_user_to_aweber($user_id, $list_id)
{
    $user = get_userdata($user_id);
    $email = $user->user_email;
    $name = $user->display_name;

 
    refresh_aweber_access_token();
    $access_token = get_option('llms_aweber_access_token');


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

    error_log( $result_message );
    
    set_transient('llms_aweber_subscription_message', $result_message, 30);
}
?>
