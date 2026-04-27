<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_Public {

    public function enqueue_assets() {
        wp_enqueue_style( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css', array(), null );
        wp_enqueue_style( 'smartreplyr-widget-css', SMARTREPLYR_PLUGIN_URL . 'public/css/widget.css', array(), SMARTREPLYR_VERSION );
        
        wp_enqueue_script( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js', array(), null, true );
        wp_enqueue_script( 'smartreplyr-widget-js', SMARTREPLYR_PLUGIN_URL . 'public/js/widget.js', array(), SMARTREPLYR_VERSION, true );
        
        $settings = SmartReplyr_DB::get_all_settings();
        $config = array(
            'api_url'       => rest_url( 'smartreplyr/v1' ),
            'bot_name'      => $settings['bot_name'] ?? 'SmartReplyr',
            'primary_color' => $settings['primary_color'] ?? '#6C5CE7',
            'position'      => $settings['chat_position'] ?? 'bottom-right',
            'avatar'        => ! empty( $settings['avatar_url'] ) ? $settings['avatar_url'] : SMARTREPLYR_PLUGIN_URL . 'assets/img/default-avatar.svg',
            'welcome_message' => $settings['welcome_message'] ?? 'How can we help you today?',
            'courses'       => array_map( 'trim', explode( ',', $settings['courses_list'] ?? '' ) ),
            'gdpr_enabled'  => $settings['gdpr_enabled'] ?? '0',
            'gdpr_text'     => $settings['gdpr_text'] ?? '',
            'nonce'         => hash_hmac( 'sha256', 'smartreplyr_public_api', wp_salt( 'nonce' ) ),
            'utm_source'    => isset( $_GET['utm_source'] ) ? sanitize_text_field( $_GET['utm_source'] ) : '',
            'utm_medium'    => isset( $_GET['utm_medium'] ) ? sanitize_text_field( $_GET['utm_medium'] ) : '',
            'utm_campaign'  => isset( $_GET['utm_campaign'] ) ? sanitize_text_field( $_GET['utm_campaign'] ) : '',
            'page_title'    => get_the_title() ?: get_bloginfo('name'),
        );

        wp_add_inline_script( 'smartreplyr-widget-js', 'var smartreplyrConfig = ' . wp_json_encode( $config ) . ';', 'before' );
    }

    public function render_widget() {
        // Render root DOM element for the vanilla JS widget to mount into
        echo '<!-- SmartReplyr Chatbot Root --><div id="smartreplyr-widget-root"></div>';
    }

    public function render_shortcode( $atts ) {
        // Output widget div dynamically for page builders
        return '<div id="smartreplyr-widget-root"></div>';
    }
}
