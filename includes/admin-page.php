<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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
        ' ',
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
    echo '<p class="description">Click the button above to authorize with AWeber.</p>';
}

function llms_aweber_auth_code_render()
{
    $value = get_option('llms_aweber_auth_code', '');
    echo '<input type="hidden" name="llms_aweber_auth_code" value="' . esc_attr($value) . '" />';
    
}

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
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="save_aweber_auth_code">
            <?php wp_nonce_field('llms_aweber_save_auth_code', 'llms_aweber_nonce'); ?>
            <label for="llms_aweber_auth_code">Authorization Code</label>
            <p>Copy the authorization code and paste it below.</p>
            <input type="text" name="llms_aweber_auth_code" id="llms_aweber_auth_code" />
            <?php submit_button('Save Authorization Code'); ?>
        </form>
        <button id="test-aweber-credentials" class="button button-secondary">Test Credentials</button>
        <div id="test-aweber-credentials-result"></div>
        <?php
        $result_message = get_transient('llms_aweber_result_message');
        if ($result_message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $result_message . '</p></div>';
            delete_transient('llms_aweber_result_message');
        }
        ?>
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
?>
