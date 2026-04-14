<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_Email {

    /**
     * Configure SMTP if set in plugin settings.
     */
    public function maybe_configure_smtp() {
        $host = EduLead_DB::get_setting( 'smtp_host', '' );
        if ( empty( $host ) ) {
            return; // Use WP default mail
        }

        add_action( 'phpmailer_init', function( $phpmailer ) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = EduLead_DB::get_setting( 'smtp_host' );
            $phpmailer->Port       = intval( EduLead_DB::get_setting( 'smtp_port', 587 ) );
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = EduLead_DB::get_setting( 'smtp_username' );
            $phpmailer->Password   = EduLead_DB::get_setting( 'smtp_password' );
            $phpmailer->SMTPSecure = EduLead_DB::get_setting( 'smtp_encryption', 'tls' );
        } );
    }

    /**
     * Send lead notification email.
     */
    public function send_notification( $lead ) {
        $to = EduLead_DB::get_setting( 'notification_email', get_option( 'admin_email' ) );
        if ( empty( $to ) ) {
            return false;
        }

        // Configure SMTP if needed
        $this->maybe_configure_smtp();

        $subject = sprintf(
            '[New Lead] %s — %s | EduLead AI',
            $lead['name'],
            $lead['course_interest'] ?: 'General Inquiry'
        );

        $body = $this->build_email_body( $lead );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: EduLead AI <' . get_option( 'admin_email' ) . '>',
        );

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( ! $sent ) {
            error_log( 'EduLead Email Error: Failed to send notification for lead #' . $lead['id'] );
        }

        return $sent;
    }

    /**
     * Build HTML email body.
     */
    private function build_email_body( $lead ) {
        $template_path = EDULEAD_AI_PLUGIN_DIR . 'templates/email-template.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Fallback inline template
        $color = EduLead_DB::get_setting( 'primary_color', '#6C5CE7' );
        $site  = get_bloginfo( 'name' );

        return '
        <div style="font-family:Inter,Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="background:' . esc_attr( $color ) . ';padding:24px 32px;">
                <h1 style="color:#fff;margin:0;font-size:22px;">🎓 New Lead Captured</h1>
                <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;">' . esc_html( $site ) . ' — EduLead AI</p>
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
                <a href="' . esc_url( admin_url( 'admin.php?page=edulead-leads' ) ) . '" style="display:inline-block;background:' . esc_attr( $color ) . ';color:#fff;padding:10px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">View in Dashboard</a>
            </div>
        </div>';
    }
}
