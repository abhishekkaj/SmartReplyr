<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_REST_API {

    const NAMESPACE = 'edulead/v1';

    public function register_routes() {

        // Submit lead (public)
        register_rest_route( self::NAMESPACE, '/lead', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'submit_lead' ),
            'permission_callback' => '__return_true',
        ) );

        // Chat message (requires lead_id)
        register_rest_route( self::NAMESPACE, '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'chat_message' ),
            'permission_callback' => '__return_true',
        ) );

        // Public widget settings
        register_rest_route( self::NAMESPACE, '/widget-config', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_widget_config' ),
            'permission_callback' => '__return_true',
        ) );

        // Admin — leads list
        register_rest_route( self::NAMESPACE, '/leads', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_leads' ),
            'permission_callback' => array( $this, 'admin_check' ),
        ) );

        // Admin — knowledge base CRUD
        register_rest_route( self::NAMESPACE, '/knowledge', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_kb' ),
                'permission_callback' => array( $this, 'admin_check' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_kb' ),
                'permission_callback' => array( $this, 'admin_check' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/knowledge/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_kb' ),
            'permission_callback' => array( $this, 'admin_check' ),
        ) );
    }

    public function admin_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    /* ─── Lead Submission ─────────────────────── */

    public function submit_lead( $request ) {
        $params = $request->get_json_params();

        // Validate required fields
        $name  = sanitize_text_field( $params['name'] ?? '' );
        $phone = sanitize_text_field( $params['phone'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );

        if ( empty( $name ) || empty( $phone ) || empty( $email ) ) {
            return new WP_Error( 'missing_fields', 'Name, phone, and email are required.', array( 'status' => 400 ) );
        }
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
        }
        if ( ! preg_match( '/^[\d\s\+\-\(\)]{7,20}$/', $phone ) ) {
            return new WP_Error( 'invalid_phone', 'Please enter a valid phone number.', array( 'status' => 400 ) );
        }

        // GDPR check
        $gdpr_enabled = EduLead_DB::get_setting( 'gdpr_enabled', '0' );
        if ( $gdpr_enabled === '1' && empty( $params['consent'] ) ) {
            return new WP_Error( 'consent_required', 'Please provide consent to proceed.', array( 'status' => 400 ) );
        }

        $lead_id = EduLead_DB::insert_lead( $params );
        if ( ! $lead_id ) {
            return new WP_Error( 'db_error', 'Could not save lead.', array( 'status' => 500 ) );
        }

        // Create conversation
        $conv_id = EduLead_DB::create_conversation( $lead_id, $params['page_url'] ?? '' );

        // Fire webhook (async-style: does it now but non-blocking to user)
        $webhook_enabled = EduLead_DB::get_setting( 'webhook_enabled', '0' );
        if ( $webhook_enabled === '1' ) {
            $lead = EduLead_DB::get_lead( $lead_id );
            $webhook = new EduLead_Webhook();
            $sent = $webhook->send( $lead );
            if ( $sent ) {
                EduLead_DB::update_lead( $lead_id, array( 'webhook_sent' => 1 ) );
            }
        }

        // Send email notification
        $email_enabled = EduLead_DB::get_setting( 'email_enabled', '0' );
        if ( $email_enabled === '1' ) {
            $lead = EduLead_DB::get_lead( $lead_id );
            $mailer = new EduLead_Email();
            $sent = $mailer->send_notification( $lead );
            if ( $sent ) {
                EduLead_DB::update_lead( $lead_id, array( 'email_sent' => 1 ) );
            }
        }

        return rest_ensure_response( array(
            'success'         => true,
            'lead_id'         => $lead_id,
            'conversation_id' => $conv_id,
            'message'         => EduLead_DB::get_setting( 'welcome_message', 'Thanks for your details! How can I help you?' ),
        ) );
    }

    /* ─── Chat Message ────────────────────────── */

    public function chat_message( $request ) {
        $params  = $request->get_json_params();
        $lead_id = intval( $params['lead_id'] ?? 0 );
        $message = sanitize_textarea_field( $params['message'] ?? '' );
        $page_context = sanitize_text_field( $params['page_context'] ?? '' );

        if ( ! $lead_id || empty( $message ) ) {
            return new WP_Error( 'invalid_request', 'Lead ID and message are required.', array( 'status' => 400 ) );
        }

        // Verify lead exists
        $lead = EduLead_DB::get_lead( $lead_id );
        if ( ! $lead ) {
            return new WP_Error( 'invalid_lead', 'Lead not found.', array( 'status' => 404 ) );
        }

        // Get or create conversation
        $conversation = EduLead_DB::get_conversation_by_lead( $lead_id );
        if ( ! $conversation ) {
            $conv_id = EduLead_DB::create_conversation( $lead_id, $page_context );
            $conversation = EduLead_DB::get_conversation( $conv_id );
        }

        $messages = json_decode( $conversation['messages'], true ) ?: array();

        // Add user message
        $messages[] = array(
            'role'      => 'user',
            'content'   => $message,
            'timestamp' => current_time( 'mysql' ),
        );

        // Try rule-based NLP match first
        $nlp_match = EduLead_NLP::match_query( $message );
        $reply = null;
        
        $debug_mode = EduLead_DB::get_setting( 'debug_mode', '0' );
        $debug_info = array();

        if ( $nlp_match && ! empty( $nlp_match['answer'] ) ) {
            $reply = wp_kses_post( $nlp_match['answer'] );
            if ( $debug_mode === '1' ) {
                $debug_info = array(
                    'source'    => 'nlp',
                    'intent'    => $nlp_match['intent_detected'] ?? 'none',
                    'score'     => $nlp_match['match_score'] ?? 0,
                    'sim_score' => $nlp_match['sim_score'] ?? 0,
                    'kw_score'  => $nlp_match['kw_score'] ?? 0,
                    'distance'  => $nlp_match['levenshtein_dist'] ?? 0,
                );
            }
        } else {
            // Get AI response
            $ai      = new EduLead_AI();
            $reply   = $ai->get_response( $message, $messages, $page_context, $lead );
            if ( $debug_mode === '1' ) {
                $debug_info = array( 'source' => 'openai' );
            }
        }

        // Add assistant message
        $messages[] = array(
            'role'      => 'assistant',
            'content'   => $reply,
            'timestamp' => current_time( 'mysql' ),
        );

        // Save conversation
        EduLead_DB::update_conversation_messages( $conversation['id'], $messages );

        $response_data = array(
            'success' => true,
            'reply'   => $reply,
        );

        if ( $debug_mode === '1' && ! empty( $debug_info ) ) {
            $response_data['debug_info'] = $debug_info;
        }

        return rest_ensure_response( $response_data );
    }

    /* ─── Widget Config ───────────────────────── */

    public function get_widget_config( $request ) {
        $settings = EduLead_DB::get_all_settings();
        return rest_ensure_response( array(
            'bot_name'        => $settings['bot_name'] ?? 'EduLead AI',
            'primary_color'   => $settings['primary_color'] ?? '#6C5CE7',
            'chat_position'   => $settings['chat_position'] ?? 'bottom-right',
            'welcome_message' => $settings['welcome_message'] ?? '',
            'gdpr_enabled'    => $settings['gdpr_enabled'] ?? '0',
            'gdpr_text'       => $settings['gdpr_text'] ?? '',
            'courses_list'    => $settings['courses_list'] ?? '',
            'avatar_url'      => $settings['avatar_url'] ?? '',
        ) );
    }

    /* ─── Admin Leads ─────────────────────────── */

    public function get_leads( $request ) {
        $args = array(
            'per_page'  => $request->get_param( 'per_page' ) ?: 20,
            'offset'    => $request->get_param( 'offset' ) ?: 0,
            'status'    => $request->get_param( 'status' ) ?: '',
            'course'    => $request->get_param( 'course' ) ?: '',
            'date_from' => $request->get_param( 'date_from' ) ?: '',
            'date_to'   => $request->get_param( 'date_to' ) ?: '',
            'search'    => $request->get_param( 'search' ) ?: '',
        );
        return rest_ensure_response( array(
            'leads' => EduLead_DB::get_leads( $args ),
            'total' => EduLead_DB::count_leads( $args ),
        ) );
    }

    /* ─── Knowledge Base ──────────────────────── */

    public function get_kb( $request ) {
        return rest_ensure_response( EduLead_DB::get_all_kb() );
    }

    public function save_kb( $request ) {
        $params = $request->get_json_params();
        $id = intval( $params['id'] ?? 0 );

        if ( $id ) {
            EduLead_DB::update_kb( $id, $params );
        } else {
            $id = EduLead_DB::insert_kb( $params );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function delete_kb( $request ) {
        $id = intval( $request['id'] );
        EduLead_DB::delete_kb( $id );
        return rest_ensure_response( array( 'success' => true ) );
    }
}
