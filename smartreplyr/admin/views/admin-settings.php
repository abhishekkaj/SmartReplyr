<?php
try {
?>
<div class="wrap smartreplyr-wrap">
    <h1>SmartReplyr Settings</h1>
    
    <?php settings_errors( 'smartreplyr_messages' ); ?>
    <?php $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general'; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=smartreplyr-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General & Widget</a>
        <a href="?page=smartreplyr-settings&tab=contact" class="nav-tab <?php echo $active_tab == 'contact' ? 'nav-tab-active' : ''; ?>">&#128222; Contact Info</a>
        <a href="?page=smartreplyr-settings&tab=ai" class="nav-tab <?php echo $active_tab == 'ai' ? 'nav-tab-active' : ''; ?>">AI Engine Config</a>
        <a href="?page=smartreplyr-settings&tab=webhook" class="nav-tab <?php echo $active_tab == 'webhook' ? 'nav-tab-active' : ''; ?>">CRM Webhook</a>
        <a href="?page=smartreplyr-settings&tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>">Email & SMTP</a>
    </h2>
    
    <form method="post" action="">
        <?php 
            settings_fields( 'smartreplyr_settings_group' );
            wp_nonce_field( 'smartreplyr_settings_action', 'smartreplyr_settings_nonce' ); 
        ?>
        <input type="hidden" name="smartreplyr_save_settings" value="1">
        <?php $settings = SmartReplyr_DB::get_all_settings(); ?>
        
        <div class="sr-settings-content">
            
            <?php if ( $active_tab == 'general' ) : ?>
            <!-- ── Widget Settings ── -->
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bot_name">Bot Name</label></th>
                    <td><input name="bot_name" type="text" id="bot_name" value="<?php echo esc_attr( $settings['bot_name'] ?? 'SmartReplyr' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="primary_color">Primary Color</label></th>
                    <td><input name="primary_color" type="color" id="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ?? '#6C5CE7' ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="welcome_message">Welcome Message</label></th>
                    <td>
                        <textarea name="welcome_message" id="welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $settings['welcome_message'] ?? '' ); ?></textarea>
                        <p class="description">First message shown to the user after they submit their details.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="avatar_url">Bot Avatar</label></th>
                    <td>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <?php 
                                $default_avatar = defined('SMARTREPLYR_PLUGIN_URL') ? SMARTREPLYR_PLUGIN_URL . 'assets/img/default-avatar.svg' : '';
                                $avatar_img = ! empty( $settings['avatar_url'] ) ? $settings['avatar_url'] : $default_avatar;
                            ?>
                            <img id="sr-avatar-preview" src="<?php echo esc_url( $avatar_img ); ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #ccc;">
                            <input name="avatar_url" type="text" id="avatar_url" value="<?php echo esc_attr( $settings['avatar_url'] ?? '' ); ?>" class="regular-text">
                            <button type="button" class="button button-secondary" id="sr-upload-avatar">Choose Image</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="courses_list">Courses Dropdown</label></th>
                    <td>
                        <input name="courses_list" type="text" id="courses_list" value="<?php echo esc_attr( $settings['courses_list'] ?? '' ); ?>" class="large-text">
                        <p class="description">Comma-separated list (e.g., MBA, B.Tech, BCA, MCA)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Debug Mode</th>
                    <td>
                        <label><input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'] ?? '0', '1' ); ?>> Enable NLP Debug Logging</label>
                        <p class="description">When checked, API responses print intent scoring and matched KB info.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">GDPR Consent</th>
                    <td>
                        <label><input type="checkbox" name="gdpr_enabled" value="1" <?php checked( $settings['gdpr_enabled'] ?? '1', '1' ); ?>> Enable mandatory checkbox on form</label><br><br>
                        <input name="gdpr_text" type="text" value="<?php echo esc_attr( $settings['gdpr_text'] ?? '' ); ?>" class="large-text">
                    </td>
                </tr>
            </table>
            
            <?php endif; ?>

            <?php if ( $active_tab == 'contact' ) : ?>
            <!-- ── Contact Info ── -->
            <table class="form-table">
                <tr><td colspan="2"><p class="description" style="font-size:14px;padding:10px;background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:10px;">⚡ <strong>These details are shown by the bot</strong> when a user says things like "contact", "call", "apply", "WhatsApp", "email me", etc.</p></td></tr>
                <tr>
                    <th scope="row"><label for="contact_email">Contact Email</label></th>
                    <td>
                        <input name="contact_email" type="email" id="contact_email" value="<?php echo esc_attr( $settings['contact_email'] ?? '' ); ?>" class="regular-text" placeholder="admissions@example.com">
                        <p class="description">Shown when user asks to email or contact.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="contact_phone">Contact Phone / Helpline</label></th>
                    <td>
                        <input name="contact_phone" type="text" id="contact_phone" value="<?php echo esc_attr( $settings['contact_phone'] ?? '' ); ?>" class="regular-text" placeholder="+91 98765 43210">
                        <p class="description">Shown when user asks to call or contact.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="contact_whatsapp">WhatsApp Number</label></th>
                    <td>
                        <input name="contact_whatsapp" type="text" id="contact_whatsapp" value="<?php echo esc_attr( $settings['contact_whatsapp'] ?? '' ); ?>" class="regular-text" placeholder="+91 98765 43210">
                        <p class="description">Shown when user mentions WhatsApp or asks to chat.</p>
                    </td>
                </tr>
            </table>

            <?php elseif ( $active_tab == 'general' ) : ?>
            <!-- ── AI Config ── -->
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
                    <td>
                        <input name="openai_api_key" type="password" id="openai_api_key" value="<?php echo esc_attr( $settings['openai_api_key'] ?? '' ); ?>" class="regular-text">
                        <p class="description">Get yours at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openai_model">Model</label></th>
                    <td>
                        <select name="openai_model" id="openai_model">
                            <option value="gpt-4o-mini" <?php selected( $settings['openai_model'] ?? 'gpt-4o-mini', 'gpt-4o-mini' ); ?>>GPT-4o Mini (Fast & Cheap)</option>
                            <option value="gpt-4o" <?php selected( $settings['openai_model'] ?? 'gpt-4o-mini', 'gpt-4o' ); ?>>GPT-4o (Smartest)</option>
                            <option value="gpt-3.5-turbo" <?php selected( $settings['openai_model'] ?? 'gpt-4o-mini', 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Legacy)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="system_prompt">System Engine Prompt</label></th>
                    <td>
                        <textarea name="system_prompt" id="system_prompt" rows="8" class="large-text code"><?php echo esc_textarea( $settings['system_prompt'] ?? '' ); ?></textarea>
                        <p class="description">Variables allowed: <code>{{institute_name}}</code>. The KB and page context will be auto-appended.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fallback_message">Fallback Message</label></th>
                    <td>
                        <textarea name="fallback_message" id="fallback_message" rows="2" class="large-text"><?php echo esc_textarea( $settings['fallback_message'] ?? '' ); ?></textarea>
                        <p class="description">Used when AI fails or API key is missing.</p>
                    </td>
                </tr>
            </table>

            <?php elseif ( $active_tab == 'webhook' ) : ?>
            <!-- ── Webhook Config ── -->
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Webhook</th>
                    <td><label><input type="checkbox" name="webhook_enabled" value="1" <?php checked( $settings['webhook_enabled'] ?? '0', '1' ); ?>> Send leads to external CRM via Webhook</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="webhook_url">Webhook Endpoint URL</label></th>
                    <td><input name="webhook_url" type="url" id="webhook_url" value="<?php echo esc_attr( $settings['webhook_url'] ?? '' ); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="field_mapping">Field Mapping (JSON)</label></th>
                    <td>
                        <textarea name="field_mapping" id="field_mapping" rows="6" class="large-text code"><?php 
                            $mapping = $settings['field_mapping'] ?? '{}';
                            // format JSON for readability
                            $decoded = json_decode($mapping);
                            echo $decoded ? esc_textarea(json_encode($decoded, JSON_PRETTY_PRINT)) : esc_textarea($mapping);
                        ?></textarea>
                        <p class="description">Map internal fields to your CRM keys: <code>{"name":"first_name", "phone":"mobile"}</code>. Internal keys: name, phone, email, course_interest</p>
                    </td>
                </tr>
            </table>

            <?php elseif ( $active_tab == 'email' ) : ?>
            <!-- ── Email Config ── -->
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Notifications</th>
                    <td><label><input type="checkbox" name="email_enabled" value="1" <?php checked( $settings['email_enabled'] ?? '0', '1' ); ?>> Send email when new lead is captured</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="notification_email">Send To</label></th>
                    <td><input name="notification_email" type="email" id="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ?? get_option('admin_email') ); ?>" class="regular-text"></td>
                </tr>
                
                <tr><td colspan="2"><hr><h3>Custom SMTP Setup (Optional)</h3><p class="description">Leave blank to use WordPress default mailer.</p></td></tr>
                
                <tr>
                    <th scope="row"><label for="smtp_host">SMTP Host</label></th>
                    <td><input name="smtp_host" type="text" id="smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_port">Port</label></th>
                    <td><input name="smtp_port" type="number" id="smtp_port" value="<?php echo esc_attr( $settings['smtp_port'] ?? '587' ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_encryption">Encryption</label></th>
                    <td>
                        <select name="smtp_encryption" id="smtp_encryption">
                            <option value="tls" <?php selected( $settings['smtp_encryption'] ?? 'tls', 'tls' ); ?>>TLS</option>
                            <option value="ssl" <?php selected( $settings['smtp_encryption'] ?? 'tls', 'ssl' ); ?>>SSL</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_username">Username</label></th>
                    <td><input name="smtp_username" type="text" id="smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="smtp_password">Password</label></th>
                    <td><input name="smtp_password" type="password" id="smtp_password" value="<?php echo esc_attr( $settings['smtp_password'] ?? '' ); ?>" class="regular-text" autocomplete="new-password"></td>
                </tr>
            </table>
            <?php endif; ?>
        </div>
        
        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
<?php
} catch (Throwable $e) {
    echo '<div class="notice notice-error"><p><strong>SmartReplyr Safe Execute Warning:</strong> Failed to render settings page: ' . esc_html($e->getMessage()) . '</p></div>';
}
?>
