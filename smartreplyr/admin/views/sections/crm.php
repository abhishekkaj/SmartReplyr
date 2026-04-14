<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2>CRM Webhook Configuration</h2>
<p class="description">Push newly captured leads out dynamically to a webhook service (Zapier/Make/Custom).</p>
<table class="form-table">
    <tr>
        <th scope="row">Enable Webhook</th>
        <td><label><input type="checkbox" name="webhook_enabled" value="1" <?php checked( $settings['webhook_enabled'], '1' ); ?>> Send leads to external CRM via Webhook</label></td>
    </tr>
    <tr>
        <th scope="row"><label for="webhook_url">Webhook Endpoint URL</label></th>
        <td>
            <input name="webhook_url" type="url" id="webhook_url" value="<?php echo esc_url($settings['webhook_url']); ?>" class="large-text">
            <p class="description">Must be a valid URL endpoint beginning with http or https.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="field_mapping">Field Mapping (JSON)</label></th>
        <td>
            <textarea name="field_mapping" id="field_mapping" rows="6" class="large-text code"><?php 
                $mapping = $settings['field_mapping'] ?: '{}';
                $decoded = json_decode($mapping);
                echo $decoded ? esc_textarea(json_encode($decoded, JSON_PRETTY_PRINT)) : esc_textarea($mapping);
            ?></textarea>
            <p class="description">Map internal fields to your CRM keys: <code>{"name":"first_name", "phone":"mobile"}</code>. Internal keys: name, phone, email, course_interest</p>
        </td>
    </tr>
</table>
