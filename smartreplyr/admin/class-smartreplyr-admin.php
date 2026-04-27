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
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle Reset Sequence
        if ( isset( $_POST['smartreplyr_reset_settings'] ) ) {
            check_admin_referer( 'smartreplyr_settings_action', 'smartreplyr_settings_nonce' );
            global $wpdb;
            $table = SmartReplyr_DB::get_settings_table();
            $wpdb->query("TRUNCATE TABLE $table"); // Clear all user settings safely
            if ( class_exists('SmartReplyr_Activator') ) {
                $activator = new ReflectionClass('SmartReplyr_Activator');
                $method = $activator->getMethod('seed_defaults');
                $method->setAccessible(true);
                $method->invoke(null);
            }
            add_settings_error( 'smartreplyr_messages', 'smartreplyr_message', 'Settings reset to production defaults successfully.', 'updated' );
            return;
        }

        if ( ! isset( $_POST['smartreplyr_save_settings'] ) ) {
            return;
        }

        check_admin_referer( 'smartreplyr_settings_action', 'smartreplyr_settings_nonce' );

        $active_tab = isset( $_POST['active_tab'] ) ? sanitize_text_field( $_POST['active_tab'] ) : 'general';

        // Map fields to their respective tabs to prevent cross-tab overwrites
        $tab_fields_map = array(
            'general' => array( 'bot_name', 'openai_api_key', 'openai_model', 'system_prompt', 'welcome_message', 'fallback_message', 'quick_prompts', 'debug_mode', 'gdpr_enabled', 'gdpr_text' ),
            'avatar'  => array( 'avatar_url', 'primary_color', 'chat_position', 'courses_list' ),
            'crm'     => array( 'webhook_enabled', 'webhook_url', 'lead_source', 'field_mapping' ),
            'email'   => array( 'email_enabled', 'notification_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption' ),
        );

        $fields = isset( $tab_fields_map[ $active_tab ] ) ? $tab_fields_map[ $active_tab ] : $tab_fields_map['general'];

        $has_errors = false;

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $val = is_string( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : $_POST[ $field ];
                
                // Detailed Sanitization checks
                if ( $field === 'bot_name' && empty( trim($val) ) ) {
                    add_settings_error( 'smartreplyr_messages', 'smartreplyr_botname_error', 'Bot Name cannot be empty.', 'error' );
                    $has_errors = true;
                    continue;
                }
                if ( $field === 'webhook_url' && ! empty( $val ) && ! filter_var( $val, FILTER_VALIDATE_URL ) ) {
                    add_settings_error( 'smartreplyr_messages', 'smartreplyr_webhook_error', 'Invalid Webhook URL format natively.', 'error' );
                    $has_errors = true;
                    continue;
                }
                if ( $field === 'notification_email' && ! empty( $val ) && ! is_email( $val ) ) {
                    add_settings_error( 'smartreplyr_messages', 'smartreplyr_email_error', 'Invalid Email format natively.', 'error' );
                    $has_errors = true;
                    continue;
                }

                if ( $field === 'primary_color' ) {
                    $val = sanitize_hex_color( $val );
                } elseif ( $field === 'avatar_url' || $field === 'webhook_url' ) {
                    $val = esc_url_raw( $val );
                } elseif ( $field === 'notification_email' ) {
                    $val = sanitize_email( $val );
                } elseif ( $field === 'system_prompt' || $field === 'welcome_message' || $field === 'fallback_message' || $field === 'gdpr_text' ) {
                    $val = sanitize_textarea_field( $val );
                } else if ( $field === 'field_mapping' ) {
                    $decoded = json_decode( $val, true );
                    $val = wp_json_encode( is_array( $decoded ) ? $decoded : array() );
                } else if ( in_array( $field, array( 'gdpr_enabled', 'webhook_enabled', 'email_enabled', 'debug_mode' ) ) ) {
                    $val = '1';
                } else {
                    $val = sanitize_text_field( $val );
                }
                
                if ( ! $has_errors ) {
                    SmartReplyr_DB::update_setting( $field, $val );
                }
            } else {
                // If it's a checkbox and NOT set, update to '0' ONLY IF we are on the tab that contains it
                if ( ! $has_errors && in_array( $field, array( 'gdpr_enabled', 'webhook_enabled', 'email_enabled', 'debug_mode' ) ) ) {
                    SmartReplyr_DB::update_setting( $field, '0' );
                }
            }
        }

        if ( ! $has_errors ) {
            add_settings_error( 'smartreplyr_messages', 'smartreplyr_message', 'Settings successfully saved and validated natively.', 'updated' );
        }
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

    public function ajax_test_bot() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
        if ( empty( $question ) ) {
            wp_send_json_error( 'Empty payload received natively.' );
        }

        require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-ai.php';
        $ai = new SmartReplyr_AI();
        $response = $ai->process_message( 0, $question, '' );

        if ( ! $response ) {
            wp_send_json_error( 'Engine exception. Check error logs.' );
        }

        wp_send_json_success( array(
            'message' => $response['message'],
            'intent'  => $response['intent'] ?? 'None'
        ) );
    }

    /* ─── Views ───────────────────────────────── */

    public function view_dashboard() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-dashboard.php'; }
    public function view_leads() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-leads.php'; }
    public function view_conversations() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-conversations.php'; }
    public function view_knowledge_base() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-knowledge-base.php'; }
    public function view_settings() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/settings-page.php'; }
}
