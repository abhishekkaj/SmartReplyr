<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = SmartReplyr_DB::get_all_settings(); // maintain compat
$settings = wp_parse_args($settings, array(
    'bot_name' => 'SmartReplyr',
    'primary_color' => '#4f46e5',
    'avatar_url' => '',
    'openai_api_key' => '',
    'openai_model' => 'gpt-4o-mini',
    'system_prompt' => '',
    'fallback_message' => '',
    'webhook_enabled' => '0',
    'webhook_url' => '',
    'field_mapping' => '{}',
    'email_enabled' => '0',
    'notification_email' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'courses_list' => '',
    'gdpr_enabled' => '1',
    'gdpr_text' => '',
    'debug_mode' => '0'
));

global $smartreplyr_settings_data;
$smartreplyr_settings_data = $settings;

if ( ! function_exists('smartreplyr_render_section') ) {
    function smartreplyr_render_section($section) {
        global $smartreplyr_settings_data;
        $settings = $smartreplyr_settings_data; // Ensure accessible natively inside include scope

        $file = SMARTREPLYR_PLUGIN_DIR . "admin/views/sections/{$section}.php";

        if (file_exists($file)) {
            try {
                include $file;
            } catch (Throwable $e) {
                echo '<div class="notice notice-error inline"><p><strong>Warning:</strong> The ' . esc_html($section) . ' section failed to load safely.</p></div>';
                error_log('[SmartReplyr Sandbox Error] Section ' . $section . ': ' . $e->getMessage());
            }
        } else {
            error_log('[SmartReplyr Sandbox] Missing admin section: ' . $section);
        }
    }
}
?>
<div class="wrap smartreplyr-wrap">
    <h1>SmartReplyr Configuration</h1>
    <?php settings_errors('smartreplyr_messages'); ?>

    <h2 class="nav-tab-wrapper">
        <?php $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field($_GET['tab']) : 'general'; ?>
        <a href="?page=smartreplyr-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General & Bot UI</a>
        <a href="?page=smartreplyr-settings&tab=avatar" class="nav-tab <?php echo $active_tab == 'avatar' ? 'nav-tab-active' : ''; ?>">Avatar & Branding</a>
        <a href="?page=smartreplyr-settings&tab=crm" class="nav-tab <?php echo $active_tab == 'crm' ? 'nav-tab-active' : ''; ?>">CRM Webhook</a>
        <a href="?page=smartreplyr-settings&tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>">Email & SMTP</a>
        <a href="?page=smartreplyr-settings&tab=test-bot" class="nav-tab <?php echo $active_tab == 'test-bot' ? 'nav-tab-active' : ''; ?>">Test Chatbot</a>
    </h2>

    <form method="post" action="">
        <?php 
            settings_fields( 'smartreplyr_settings_group' );
            wp_nonce_field( 'smartreplyr_settings_action', 'smartreplyr_settings_nonce' ); 
        ?>
        <input type="hidden" name="smartreplyr_save_settings" value="1">
        <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">
        
        <div class="sr-settings-content" style="margin-top:20px; background: #fff; padding: 20px; border: 1px solid #ccc;">
            <?php
            if ($active_tab === 'general') {
                smartreplyr_render_section('general');
            } elseif ($active_tab === 'avatar') {
                smartreplyr_render_section('avatar');
            } elseif ($active_tab === 'crm') {
                smartreplyr_render_section('crm');
            } elseif ($active_tab === 'email') {
                smartreplyr_render_section('email');
            } elseif ($active_tab === 'test-bot') {
                smartreplyr_render_section('test-bot');
            }
            ?>
        </div>
        
        <?php if ($active_tab !== 'test-bot') : ?>
            <div style="margin-top: 15px;">
                <?php submit_button( 'Save Configuration', 'primary', 'submit', false ); ?>
            </div>
        <?php endif; ?>
    </form>
    
    <hr style="margin-top:30px;">
    <form method="post" action="" onsubmit="return confirm('Are you sure you want to reset all settings to default? This cannot be undone.');">
        <?php wp_nonce_field( 'smartreplyr_settings_action', 'smartreplyr_settings_nonce' ); ?>
        <input type="hidden" name="smartreplyr_reset_settings" value="1">
        <button type="submit" class="button button-link-delete">Reset to Default</button>
    </form>
</div>
