<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_Public {

    public function enqueue_assets() {
        wp_enqueue_style( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css', array(), null );
        wp_enqueue_style( 'edulead-widget-css', EDULEAD_AI_PLUGIN_URL . 'public/css/widget.css', array(), EDULEAD_AI_VERSION );
        
        wp_enqueue_script( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js', array(), null, true );
        // Lazy load the JS widget by adding defer attribute later
        wp_enqueue_script( 'edulead-widget-js', EDULEAD_AI_PLUGIN_URL . 'public/js/widget.js', array('intl-tel-input'), EDULEAD_AI_VERSION, true );
        
        $settings = EduLead_DB::get_all_settings();
        
        wp_localize_script( 'edulead-widget-js', 'eduleadConfig', array(
            'api_url'       => rest_url( 'edulead/v1' ),
            'bot_name'      => $settings['bot_name'] ?? 'EduLead AI',
            'primary_color' => $settings['primary_color'] ?? '#6C5CE7',
            'position'      => $settings['chat_position'] ?? 'bottom-right',
            'avatar'        => ! empty( $settings['avatar_url'] ) ? $settings['avatar_url'] : EDULEAD_AI_PLUGIN_URL . 'assets/img/default-avatar.svg',
            'courses'       => array_map( 'trim', explode( ',', $settings['courses_list'] ?? '' ) ),
            'gdpr_enabled'  => $settings['gdpr_enabled'] ?? '0',
            'gdpr_text'     => $settings['gdpr_text'] ?? '',
            'utm_source'    => isset( $_GET['utm_source'] ) ? sanitize_text_field( $_GET['utm_source'] ) : '',
            'utm_medium'    => isset( $_GET['utm_medium'] ) ? sanitize_text_field( $_GET['utm_medium'] ) : '',
            'utm_campaign'  => isset( $_GET['utm_campaign'] ) ? sanitize_text_field( $_GET['utm_campaign'] ) : '',
            'page_title'    => get_the_title(),
        ) );
    }

    public function render_widget() {
        // Render root DOM element for the vanilla JS widget to mount into
        echo '<div id="edulead-widget-root"></div>';
    }
}
