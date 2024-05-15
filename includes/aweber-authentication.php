<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
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

        $result_message = '';
        if (is_wp_error($response)) {
            $result_message = 'AWeber token request failed: ' . $response->get_error_message();
        } else {
            $body = wp_remote_retrieve_body($response);
            $tokens = json_decode($body, true);

            if (isset($tokens['access_token']) && isset($tokens['refresh_token']) && isset($tokens['expires_in'])) {
                update_option('llms_aweber_access_token', $tokens['access_token']);
                update_option('llms_aweber_refresh_token', $tokens['refresh_token']);
                update_option('llms_aweber_token_expiry', time() + $tokens['expires_in']);
                $result_message = 'AWeber token exchange successful.';
            } else {
                $result_message = 'AWeber token request failed: ' . $body;
            }
        }

        set_transient('llms_aweber_result_message', $result_message, 30);
    }
    wp_redirect(admin_url('options-general.php?page=llms-aweber-integration'));
    exit;
}

function test_aweber_credentials()
{
    $client_id = get_option('llms_aweber_client_id');
    $refresh_token = get_option('llms_aweber_refresh_token');

    $result_message = 'Client ID: ' . $client_id . '<br>Refresh Token: ' . $refresh_token . '<br>';

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
        $result_message .= 'AWeber credentials test failed: ' . $response->get_error_message();
        wp_send_json_error(array('message' => $result_message));
    } else {
        $body = wp_remote_retrieve_body($response);
        $tokens = json_decode($body, true);

        if (isset($tokens['access_token']) && isset($tokens['refresh_token']) && isset($tokens['expires_in'])) {
            $result_message .= 'AWeber credentials are valid.';
            wp_send_json_success(array('message' => $result_message));
        } else {
            $result_message .= 'AWeber credentials test failed: ' . $body;
            wp_send_json_error(array('message' => $result_message));
        }
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
