<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_Email {

    /**
     * Configure SMTP if set in plugin settings.
     * Hooks into phpmailer_init to override default mail behavior.
     */
    public function maybe_configure_smtp() {
        $host = SmartReplyr_DB::get_setting( 'smtp_host', '' );
        if ( empty( $host ) ) {
            return;
        }

        add_action( 'phpmailer_init', function( $phpmailer ) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = SmartReplyr_DB::get_setting( 'smtp_host' );
            $phpmailer->Port       = intval( SmartReplyr_DB::get_setting( 'smtp_port', 587 ) );
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = SmartReplyr_DB::get_setting( 'smtp_username' );
            $phpmailer->Password   = SmartReplyr_DB::get_setting( 'smtp_password' );
            $phpmailer->SMTPSecure = SmartReplyr_DB::get_setting( 'smtp_encryption', 'tls' );
            
            // Force From Name to match SMTP Username if it looks like an email
            if ( is_email( $phpmailer->Username ) ) {
                $phpmailer->From = $phpmailer->Username;
            }
        } );
    }

    /**
     * Send lead notification email.
     */
    public function send_notification( $lead ) {
        return smartreplyr_safe_execute(function() use ($lead) {
            $enabled = SmartReplyr_DB::get_setting( 'email_enabled', '0' );
            if ( $enabled !== '1' ) {
                return false;
            }

            $to = SmartReplyr_DB::get_setting( 'notification_email', get_option( 'admin_email' ) );
            if ( empty( $to ) ) {
                return false;
            }

            // Ensure SMTP is configured
            $this->maybe_configure_smtp();

            $subject = sprintf(
                '[New Lead] %s — %s | SmartReplyr',
                $lead['name'],
                $lead['course_interest'] ?: 'General Inquiry'
            );

            $body = $this->build_email_body( $lead );
            $from_email = SmartReplyr_DB::get_setting( 'smtp_username', get_option( 'admin_email' ) );
            if ( ! is_email( $from_email ) ) {
                $from_email = get_option( 'admin_email' );
            }

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: SmartReplyr <' . $from_email . '>',
            );

            // Capture errors during sending
            ob_start();
            $sent = wp_mail( $to, $subject, $body, $headers );
            $possible_error = ob_get_clean();

            if ( ! $sent ) {
                SmartReplyr_DB::add_log('email', 'internal', 'failed', "Email sending failed. " . strip_tags($possible_error), array('lead_id' => $lead['id']));
                error_log( 'SmartReplyr Email Error: ' . $possible_error );
            } else {
                // Update lead record
                global $wpdb;
                $wpdb->update( $wpdb->prefix . 'smartreplyr_leads', array( 'email_sent' => 1 ), array( 'id' => $lead['id'] ) );
            }

            return $sent;
        });
    }

    /**
     * Build HTML email body.
     */
    private function build_email_body( $lead ) {
        $template_path = SMARTREPLYR_PLUGIN_DIR . 'templates/email-template.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Fallback inline template
        $color = SmartReplyr_DB::get_setting( 'primary_color', '#6C5CE7' );
        $site  = get_bloginfo( 'name' );

        return '
        <div style="font-family:Inter,Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="background:' . esc_attr( $color ) . ';padding:24px 32px;">
                <h1 style="color:#fff;margin:0;font-size:22px;">🎓 New Lead Captured</h1>
                <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;">' . esc_html( $site ) . ' — SmartReplyr</p>
            </div>
            <div style="padding:28px 32px;">
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;color:#6b7280;width:140px;">Name</td><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;font-weight:600;">' . esc_html( $lead['name'] ) . '</td></tr>
                    <tr><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;color:#6b7280;">Email</td><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;"><a href="mailto:' . esc_attr( $lead['email'] ) . '" style="color:' . esc_attr( $color ) . ';text-decoration:none;">' . esc_html( $lead['email'] ) . '</a></td></tr>
                    <tr><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;color:#6b7280;">Phone</td><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;"><a href="tel:' . esc_attr( $lead['phone'] ) . '" style="color:' . esc_attr( $color ) . ';text-decoration:none;">' . esc_html( $lead['phone'] ) . '</a></td></tr>
                    <tr><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;color:#6b7280;">Course</td><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">' . esc_html( $lead['course_interest'] ?: '—' ) . '</td></tr>
                    <tr><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;color:#6b7280;">Page</td><td style="padding:10px 0;border-bottom:1px solid #f3f4f6;"><a href="' . esc_url( $lead['page_url'] ) . '" style="color:' . esc_attr( $color ) . ';text-decoration:none;word-break:break-all;">' . esc_html( $lead['page_title'] ?: $lead['page_url'] ) . '</a></td></tr>
                    <tr><td style="padding:10px 0;color:#6b7280;">Time</td><td style="padding:10px 0;">' . esc_html( $lead['created_at'] ) . '</td></tr>
                </table>
            </div>
            <div style="background:#f9fafb;padding:16px 32px;text-align:center;">
                <a href="' . esc_url( admin_url( 'admin.php?page=smartreplyr-leads' ) ) . '" style="display:inline-block;background:' . esc_attr( $color ) . ';color:#fff;padding:10px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">View in Dashboard</a>
            </div>
        </div>';
    }
}
