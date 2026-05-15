<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// $settings is passed from settings-page.php
?>
<h3>📞 Contact Information Settings</h3>
<p class="description" style="font-size:14px;padding:10px;background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:10px; border-radius: 4px;">
    ⚡ <strong>Bot Integration:</strong> These details are used by the chatbot when a user asks to "contact", "call", "apply", "whatsapp", or "email". 
    Providing these ensures the bot gives accurate contact details without guessing.
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="contact_email">Public Contact Email</label></th>
        <td>
            <input name="contact_email" type="email" id="contact_email" value="<?php echo esc_attr( $settings['contact_email'] ?? '' ); ?>" class="regular-text" placeholder="admissions@example.com">
            <p class="description">This email will be shown in the chat window when users ask for email contact.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="contact_phone">Helpline / Support Phone</label></th>
        <td>
            <input name="contact_phone" type="text" id="contact_phone" value="<?php echo esc_attr( $settings['contact_phone'] ?? '' ); ?>" class="regular-text" placeholder="+91 98765 43210">
            <p class="description">Shown when users ask to call or speak with an agent.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="contact_whatsapp">WhatsApp Number</label></th>
        <td>
            <input name="contact_whatsapp" type="text" id="contact_whatsapp" value="<?php echo esc_attr( $settings['contact_whatsapp'] ?? '' ); ?>" class="regular-text" placeholder="+91 98765 43210">
            <p class="description">Direct WhatsApp number (including country code) for the "WhatsApp" query trigger.</p>
        </td>
    </tr>
</table>
