<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_Admin {

    public function register_menus() {
        add_menu_page(
            'SmartReplyr',
            'SmartReplyr',
            'manage_options',
            'smartreplyr-dashboard',
            array( $this, 'view_dashboard' ),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'smartreplyr-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'smartreplyr-dashboard',
            array( $this, 'view_dashboard' )
        );

        add_submenu_page(
            'smartreplyr-dashboard',
            'Leads',
            'Leads',
            'manage_options',
            'smartreplyr-leads',
            array( $this, 'view_leads' )
        );

        add_submenu_page(
            'smartreplyr-dashboard',
            'Conversations',
            'Conversations',
            'manage_options',
            'smartreplyr-conversations',
            array( $this, 'view_conversations' )
        );

        add_submenu_page(
            'smartreplyr-dashboard',
            'Knowledge Base',
            'Knowledge Base',
            'manage_options',
            'smartreplyr-kb',
            array( $this, 'view_knowledge_base' )
        );

        add_submenu_page(
            'smartreplyr-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'smartreplyr-settings',
            array( $this, 'view_settings' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'smartreplyr' ) === false ) {
            return;
        }

        wp_enqueue_style( 'smartreplyr-admin-css', SMARTREPLYR_PLUGIN_URL . 'admin/css/admin.css', array(), SMARTREPLYR_VERSION );
        wp_enqueue_media(); // Load media uploader for avatar
        wp_enqueue_script( 'smartreplyr-admin-js', SMARTREPLYR_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), SMARTREPLYR_VERSION, true );
        
        wp_localize_script( 'smartreplyr-admin-js', 'smartreplyrAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smartreplyr_admin_nonce' ),
        ) );
    }

    public function handle_settings() {
        if ( ! isset( $_POST['smartreplyr_save_settings'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        check_admin_referer( 'smartreplyr_settings_action', 'smartreplyr_settings_nonce' );

        $fields = array(
            'bot_name', 'primary_color', 'chat_position', 'welcome_message', 'fallback_message',
            'courses_list', 'gdpr_enabled', 'gdpr_text', 'avatar_url', 'debug_mode',
            'openai_api_key', 'openai_model', 'system_prompt',
            'webhook_enabled', 'webhook_url', 'field_mapping',
            'email_enabled', 'notification_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption'
        );

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $val = is_string( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : $_POST[ $field ];
                
                if ( $field === 'system_prompt' || $field === 'welcome_message' || $field === 'fallback_message' || $field === 'gdpr_text' ) {
                    $val = sanitize_textarea_field( $val );
                } else if ( $field === 'field_mapping' ) {
                    $val = wp_json_encode( is_string( $val ) ? json_decode( $val, true ) : $val );
                } else if ( in_array( $field, array( 'gdpr_enabled', 'webhook_enabled', 'email_enabled', 'debug_mode' ) ) ) {
                    $val = '1';
                } else {
                    $val = sanitize_text_field( $val );
                }
                
                SmartReplyr_DB::update_setting( $field, $val );
            } else {
                // Handle unchecked checkboxes
                if ( in_array( $field, array( 'gdpr_enabled', 'webhook_enabled', 'email_enabled', 'debug_mode' ) ) ) {
                    SmartReplyr_DB::update_setting( $field, '0' );
                }
            }
        }

        add_settings_error( 'smartreplyr_messages', 'smartreplyr_message', 'Settings saved successfully.', 'updated' );
    }

    public function export_csv() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die();

        $leads = SmartReplyr_DB::get_leads( array( 'per_page' => 99999 ) );

        $filename = 'smartreplyr-leads-' . date('Y-m-d') . '.csv';
        
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        
        $output = fopen( 'php://output', 'w' );
        
        fputcsv( $output, array( 'ID', 'Name', 'Phone', 'Email', 'Course', 'Page URL', 'Date', 'Webhook Sent', 'Email Sent' ) );
        
        foreach ( $leads as $lead ) {
            fputcsv( $output, array(
                $lead['id'],
                $lead['name'],
                $lead['phone'],
                $lead['email'],
                $lead['course_interest'],
                $lead['page_url'],
                $lead['created_at'],
                $lead['webhook_sent'] ? 'Yes' : 'No',
                $lead['email_sent'] ? 'Yes' : 'No',
            ) );
        }
        
        fclose( $output );
        wp_die();
    }

    public function ajax_save_kb() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $id       = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $question = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
        $answer   = isset( $_POST['answer'] ) ? wp_kses_post( wp_unslash( $_POST['answer'] ) ) : '';
        $keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
        $intent   = isset( $_POST['intent'] ) ? sanitize_text_field( wp_unslash( $_POST['intent'] ) ) : 'general';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'general';

        if ( empty( $question ) || empty( $answer ) ) {
            wp_send_json_error( 'Question and answer are required.' );
        }

        $data = compact( 'question', 'answer', 'keywords', 'intent', 'category' );
        if ( $id ) {
            SmartReplyr_DB::update_kb( $id, $data );
        } else {
            $id = SmartReplyr_DB::insert_kb( $data );
        }

        wp_send_json_success( array( 'id' => $id, 'message' => 'Saved successfully.' ) );
    }

    public function ajax_delete_kb() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        if ( $id ) {
            SmartReplyr_DB::delete_kb( $id );
        }

        wp_send_json_success( 'Deleted successfully.' );
    }

    /* ─── Views ───────────────────────────────── */

    public function view_dashboard() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-dashboard.php'; }
    public function view_leads() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-leads.php'; }
    public function view_conversations() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-conversations.php'; }
    public function view_knowledge_base() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-knowledge-base.php'; }
    public function view_settings() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-settings.php'; }
}
