<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2>General & AI Config</h2>
<p class="description">Core configurations mapping out the behavior of the OpenAI integration and standard text fields.</p>
<table class="form-table">
    <tr>
        <th scope="row"><label for="bot_name">Bot Name</label></th>
        <td>
            <input name="bot_name" type="text" id="bot_name" value="<?php echo esc_attr( $settings['bot_name'] ); ?>" class="regular-text" required>
            <p class="description">The display name of your chatbot assistant.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
        <td>
            <input name="openai_api_key" type="password" id="openai_api_key" value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" class="regular-text">
            <p class="description">Get yours at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="openai_model">Model</label></th>
        <td>
            <select name="openai_model" id="openai_model">
                <option value="gpt-4o-mini" <?php selected( $settings['openai_model'], 'gpt-4o-mini' ); ?>>GPT-4o Mini (Fast & Cheap)</option>
                <option value="gpt-4o" <?php selected( $settings['openai_model'], 'gpt-4o' ); ?>>GPT-4o (Smartest)</option>
                <option value="gpt-3.5-turbo" <?php selected( $settings['openai_model'], 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Legacy)</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="system_prompt">System Engine Prompt</label></th>
        <td>
            <textarea name="system_prompt" id="system_prompt" rows="8" class="large-text code"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
            <p class="description">Variables allowed: <code>{{institute_name}}</code>. The KB and page context will be auto-appended.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="welcome_message">Welcome Message</label></th>
        <td>
            <textarea name="welcome_message" id="welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea>
            <p class="description">First message shown to the user after they submit their details.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="fallback_message">Fallback Message</label></th>
        <td>
            <textarea name="fallback_message" id="fallback_message" rows="2" class="large-text"><?php echo esc_textarea( $settings['fallback_message'] ); ?></textarea>
            <p class="description">Used when AI fails or API key is missing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Debug Mode</th>
        <td>
            <label><input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'], '1' ); ?>> Enable NLP Debug Logging</label>
            <p class="description">When checked, API responses print intent scoring and matched KB info.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">GDPR Consent</th>
        <td>
            <label><input type="checkbox" name="gdpr_enabled" value="1" <?php checked( $settings['gdpr_enabled'], '1' ); ?>> Enable mandatory checkbox on form</label><br><br>
            <input name="gdpr_text" type="text" value="<?php echo esc_attr( $settings['gdpr_text'] ); ?>" class="large-text">
        </td>
    </tr>
</table>
