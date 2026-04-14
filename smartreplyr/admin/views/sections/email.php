<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2>Email Notifications & SMTP</h2>
<table class="form-table">
    <tr>
        <th scope="row">Enable Notifications</th>
        <td><label><input type="checkbox" name="email_enabled" value="1" <?php checked( $settings['email_enabled'], '1' ); ?>> Send email when new lead is captured</label></td>
    </tr>
    <tr>
        <th scope="row"><label for="notification_email">Send To</label></th>
        <td>
            <input name="notification_email" type="email" id="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ?: get_option('admin_email') ); ?>" class="regular-text">
            <p class="description">Must be a valid email address.</p>
        </td>
    </tr>
    
    <tr><td colspan="2"><hr><h3>Custom SMTP Setup (Optional)</h3><p class="description">Leave blank to use WordPress default mailer via wp_mail().</p></td></tr>
    
    <tr>
        <th scope="row"><label for="smtp_host">SMTP Host</label></th>
        <td><input name="smtp_host" type="text" id="smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>" class="regular-text"></td>
    </tr>
    <tr>
        <th scope="row"><label for="smtp_port">Port</label></th>
        <td><input name="smtp_port" type="number" id="smtp_port" value="<?php echo esc_attr( $settings['smtp_port'] ); ?>" class="small-text"></td>
    </tr>
    <tr>
        <th scope="row"><label for="smtp_encryption">Encryption</label></th>
        <td>
            <select name="smtp_encryption" id="smtp_encryption">
                <option value="tls" <?php selected( $settings['smtp_encryption'], 'tls' ); ?>>TLS</option>
                <option value="ssl" <?php selected( $settings['smtp_encryption'], 'ssl' ); ?>>SSL</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="smtp_username">Username</label></th>
        <td><input name="smtp_username" type="text" id="smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>" class="regular-text" autocomplete="off"></td>
    </tr>
    <tr>
        <th scope="row"><label for="smtp_password">Password</label></th>
        <td><input name="smtp_password" type="password" id="smtp_password" value="<?php echo esc_attr( $settings['smtp_password'] ); ?>" class="regular-text" autocomplete="new-password"></td>
    </tr>
</table>
