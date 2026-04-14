<?php
/**
 * HTML Email Template for Lead Notifications
 * 
 * Variables available:
 * $lead - Array containing lead data (name, email, phone, course_interest, page_url, page_title, created_at)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$color = EduLead_DB::get_setting( 'primary_color', '#6C5CE7' );
$site  = get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Lead Captured</title>
</head>
<body style="margin:0;padding:20px;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);">
        
        <!-- Header -->
        <div style="background:<?php echo esc_attr( $color ); ?>;padding:30px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:600;">🎓 New Lead Captured</h1>
            <p style="margin:10px 0 0;color:rgba(255,255,255,0.9);font-size:15px;"><?php echo esc_html( $site ); ?> — EduLead AI</p>
        </div>

        <!-- Body -->
        <div style="padding:40px 30px;">
            <p style="margin:0 0 20px;color:#4b5563;font-size:16px;">A new prospective student has submitted their details via the chat widget.</p>
            
            <table style="width:100%;border-collapse:collapse;margin-top:20px;">
                <tr>
                    <th style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;text-align:left;width:35%;">Full Name</th>
                    <td style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#111827;font-weight:600;"><?php echo esc_html( $lead['name'] ); ?></td>
                </tr>
                <tr>
                    <th style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;text-align:left;">Email Address</th>
                    <td style="padding:15px 0;border-bottom:1px solid #e5e7eb;">
                        <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>" style="color:<?php echo esc_attr( $color ); ?>;text-decoration:none;font-weight:500;"><?php echo esc_html( $lead['email'] ); ?></a>
                    </td>
                </tr>
                <tr>
                    <th style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;text-align:left;">Phone Number</th>
                    <td style="padding:15px 0;border-bottom:1px solid #e5e7eb;">
                        <a href="tel:<?php echo esc_attr( $lead['phone'] ); ?>" style="color:<?php echo esc_attr( $color ); ?>;text-decoration:none;font-weight:500;"><?php echo esc_html( $lead['phone'] ); ?></a>
                    </td>
                </tr>
                <tr>
                    <th style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;text-align:left;">Course of Interest</th>
                    <td style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#111827;">
                        <span style="background:#f3f4f6;padding:4px 10px;border-radius:6px;font-size:14px;font-weight:500;">
                            <?php echo esc_html( $lead['course_interest'] ?: 'General Inquiry' ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th style="padding:15px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;text-align:left;">Source Page</th>
                    <td style="padding:15px 0;border-bottom:1px solid #e5e7eb;">
                        <a href="<?php echo esc_url( $lead['page_url'] ); ?>" style="color:<?php echo esc_attr( $color ); ?>;text-decoration:none;font-weight:500;word-break:break-all;">
                            <?php echo esc_html( $lead['page_title'] ?: $lead['page_url'] ); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th style="padding:15px 0;color:#6b7280;font-weight:500;text-align:left;">Captured On</th>
                    <td style="padding:15px 0;color:#4b5563;">
                        <?php echo esc_html( wp_date( get_option('date_format') . ' ' . get_option('time_format'), strtotime( $lead['created_at'] ) ) ); ?>
                    </td>
                </tr>
            </table>

            <?php if ( ! empty($lead['utm_source']) ) : ?>
            <div style="margin-top:30px;background:#f9fafb;padding:15px;border-radius:8px;border:1px dashed #d1d5db;">
                <p style="margin:0 0 10px;font-size:13px;font-weight:600;color:#6b7280;text-transform:uppercase;">Marketing Attribution</p>
                <p style="margin:0;font-size:14px;color:#4b5563;font-family:monospace;">
                    Source: <?php echo esc_html($lead['utm_source']); ?> <br>
                    Medium: <?php echo esc_html($lead['utm_medium']); ?> <br>
                    Campaign: <?php echo esc_html($lead['utm_campaign']); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="background:#f9fafb;padding:25px 30px;border-top:1px solid #e5e7eb;text-align:center;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=edulead-leads' ) ); ?>" style="display:inline-block;background:<?php echo esc_attr( $color ); ?>;color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">View Leads Dashboard</a>
            <p style="margin:15px 0 0;font-size:12px;color:#9ca3af;">This was an automated notification from the EduLead AI WordPress Plugin.</p>
        </div>

    </div>

</body>
</html>
