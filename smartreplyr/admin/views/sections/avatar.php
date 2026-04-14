<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2>Avatar & Branding</h2>
<p class="description">Design the look and feel of the widget matching your brand.</p>
<table class="form-table">
    <tr>
        <th scope="row"><label for="primary_color">Primary Color</label></th>
        <td>
            <input name="primary_color" type="color" id="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>">
            <p class="description">Hex color utilized across icons and buttons.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="avatar_url">Bot Avatar</label></th>
        <td>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php 
                    $default_avatar = defined('SMARTREPLYR_PLUGIN_URL') ? SMARTREPLYR_PLUGIN_URL . 'assets/img/default-avatar.svg' : '';
                    $avatar_img = !empty($settings['avatar_url']) ? esc_url($settings['avatar_url']) : $default_avatar;
                ?>
                <img id="sr-avatar-preview" src="<?php echo esc_url($avatar_img); ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid <?php echo esc_attr($settings['primary_color']); ?>;">
                <input name="avatar_url" type="text" id="avatar_url" value="<?php echo esc_url($settings['avatar_url']); ?>" class="regular-text">
                <button type="button" class="button button-secondary" id="sr-upload-avatar">Choose Image</button>
            </div>
            <p class="description">Select a photo for your assistant to make them more welcoming.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="courses_list">Courses Dropdown</label></th>
        <td>
            <input name="courses_list" type="text" id="courses_list" value="<?php echo esc_attr( $settings['courses_list'] ); ?>" class="large-text">
            <p class="description">Comma-separated list (e.g., MBA, B.Tech, BCA, MCA)</p>
        </td>
    </tr>
</table>
