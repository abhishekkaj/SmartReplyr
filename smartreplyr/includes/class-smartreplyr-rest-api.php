<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_REST_API {

    const NAMESPACE = 'smartreplyr/v1';

    public function register_routes() {

        // Submit lead (public)
        register_rest_route( self::NAMESPACE, '/lead', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'submit_lead' ),
            'permission_callback' => array( $this, 'public_permission_check' ),
        ) );

        // Chat message (requires lead_id)
        register_rest_route( self::NAMESPACE, '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'chat_message' ),
            'permission_callback' => array( $this, 'public_permission_check' ),
        ) );

        // Public widget settings
        register_rest_route( self::NAMESPACE, '/widget-config', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_widget_config' ),
            'permission_callback' => array( $this, 'public_permission_check' ),
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

    public function public_permission_check( $request ) {
        // Rate Limiting (IP-based, max 30 req / min — chatbots need many rapid requests)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'sr_rate_limit_' . md5( $ip );
        $requests = get_transient( $transient_key ) ?: 0;

        if ( $requests >= 30 ) {
            SmartReplyr_DB::add_log('security', 'rate_limit', 'failed', "Rate limit exceeded for IP: $ip", array('ip' => $ip));
            return new WP_Error( 'rate_limit_exceeded', 'Too many requests. Please wait a minute and try again.', array( 'status' => 429 ) );
        }
        set_transient( $transient_key, $requests + 1, 60 );

        $nonce = $request->get_header( 'x-sr-nonce' );
        if ( ! $nonce ) {
            $nonce = $request->get_param( 'nonce' );
        }
        $expected = hash_hmac( 'sha256', 'smartreplyr_public_api', wp_salt( 'nonce' ) );
        
        if ( hash_equals( $expected, $nonce ) ) {
            return true;
        }
        
        SmartReplyr_DB::add_log('security', 'nonce_failure', 'failed', "Invalid custom nonce provided");
        return new WP_Error( 'rest_cookie_invalid_nonce', 'Invalid security token.', array( 'status' => 403 ) );
    }

    public function admin_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    /* ─── Lead Submission ─────────────────────── */

    public function submit_lead( $request ) {
        $result = smartreplyr_safe_execute(function() use ($request) {
            $params = $request->get_json_params();
            
            SmartReplyr_DB::add_log('lead', 'api_request', 'success', "Incoming lead submission", $params);

            // Validate required fields
            $name  = sanitize_text_field( $params['name'] ?? '' );
            $phone = sanitize_text_field( $params['phone'] ?? '' );
            $email = sanitize_email( $params['email'] ?? '' );

            if ( empty( $name ) || empty( $phone ) || empty( $email ) ) {
                SmartReplyr_DB::add_log('lead', 'validation', 'failed', "Missing required fields", $params);
                return new WP_Error( 'missing_fields', 'Name, phone, and email are required.', array( 'status' => 400 ) );
            }
            if ( ! is_email( $email ) ) {
                SmartReplyr_DB::add_log('lead', 'validation', 'failed', "Invalid email provided: $email", $params);
                return new WP_Error( 'invalid_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
            }

            // GDPR check
            $gdpr_enabled = SmartReplyr_DB::get_setting( 'gdpr_enabled', '0' );
            if ( $gdpr_enabled === '1' && empty( $params['consent'] ) ) {
                SmartReplyr_DB::add_log('lead', 'validation', 'failed', "GDPR consent missing", $params);
                return new WP_Error( 'consent_required', 'Please provide consent to proceed.', array( 'status' => 400 ) );
            }

            // Capture custom fields as JSON
            if ( ! empty( $params['extra_fields'] ) && is_array( $params['extra_fields'] ) ) {
                $params['meta_data'] = wp_json_encode( $params['extra_fields'] );
            }

            $lead_id = SmartReplyr_DB::insert_lead( $params );
            if ( ! $lead_id ) {
                SmartReplyr_DB::add_log('lead', 'database', 'failed', "DB insertion failed", $params);
                return new WP_Error( 'db_error', 'Could not save lead.', array( 'status' => 500 ) );
            }

            SmartReplyr_DB::add_log('lead', 'database', 'success', "Lead saved successfully: #$lead_id", array('lead_id' => $lead_id));

            // Create conversation
            $conv_id = SmartReplyr_DB::create_conversation( $lead_id, $params['page_url'] ?? '' );

            // Fire webhook (async-style: does it now but non-blocking to user)
            $webhook_enabled = SmartReplyr_DB::get_setting( 'webhook_enabled', '0' );
            if ( $webhook_enabled === '1' ) {
                $lead = SmartReplyr_DB::get_lead( $lead_id );
                $webhook = new SmartReplyr_Webhook();
                $sent = $webhook->send( $lead );
                if ( $sent ) {
                    SmartReplyr_DB::update_lead( $lead_id, array( 'webhook_sent' => 1 ) );
                    SmartReplyr_DB::add_log('webhook', 'external', 'success', "Webhook fired for lead #$lead_id", array('lead_id' => $lead_id));
                } else {
                    SmartReplyr_DB::add_log('webhook', 'external', 'failed', "Webhook failed for lead #$lead_id", array('lead_id' => $lead_id));
                }
            }

            // Send email notification
            $email_enabled = SmartReplyr_DB::get_setting( 'email_enabled', '0' );
            if ( $email_enabled === '1' ) {
                $lead = SmartReplyr_DB::get_lead( $lead_id );
                $mailer = new SmartReplyr_Email();
                $sent = $mailer->send_notification( $lead );
                if ( $sent ) {
                    SmartReplyr_DB::update_lead( $lead_id, array( 'email_sent' => 1 ) );
                    SmartReplyr_DB::add_log('email', 'notification', 'success', "Notification email sent for lead #$lead_id");
                } else {
                    SmartReplyr_DB::add_log('email', 'notification', 'failed', "Email failed for lead #$lead_id");
                }
            }

            return rest_ensure_response( array(
                'success'         => true,
                'lead_id'         => $lead_id,
                'conversation_id' => $conv_id,
                'message'         => SmartReplyr_DB::get_setting( 'welcome_message', 'Thanks for your details! How can I help you?' ),
            ) );
        });

        if ($result === null) {
            return new WP_Error( 'server_error', 'System error occurred during lead processing.', array( 'status' => 500 ) );
        }

        return $result;
    }

    /* ─── Chat Message ────────────────────────── */

    public function chat_message( $request ) {
        try {
            $params  = $request->get_json_params();
            $lead_id = intval( $params['lead_id'] ?? 0 );
            $message = sanitize_textarea_field( $params['message'] ?? '' );
            $page_context = sanitize_text_field( $params['page_context'] ?? '' );

            if ( ! $lead_id || empty( $message ) ) {
                SmartReplyr_DB::add_log('chat', 'validation', 'failed', "Missing Lead ID or message", $params);
                return new WP_Error( 'invalid_request', 'Lead ID and message are required.', array( 'status' => 400 ) );
            }

            // Verify lead exists
            $lead = SmartReplyr_DB::get_lead( $lead_id );
            if ( ! $lead ) {
                // Lead not found — might be stale localStorage. Still answer the question.
                SmartReplyr_DB::add_log('chat', 'existence', 'warning', "Lead #$lead_id not found, proceeding with empty context", array('lead_id' => $lead_id));
                $lead = array();
            }

            // Get or create conversation (with fault tolerance)
            $messages = array();
            $conversation = null;
            try {
                $conversation = SmartReplyr_DB::get_conversation_by_lead( $lead_id );
                if ( ! $conversation ) {
                    $conv_id = SmartReplyr_DB::create_conversation( $lead_id, $page_context );
                    $conversation = SmartReplyr_DB::get_conversation( $conv_id );
                }
                if ( $conversation ) {
                    $messages = json_decode( $conversation['messages'], true ) ?: array();
                }
            } catch ( Throwable $e ) {
                // Conversation DB issue — non-fatal, continue with empty history
                error_log( '[SmartReplyr] Conversation DB error: ' . $e->getMessage() );
            }

            // Add user message to history
            $messages[] = array(
                'role'      => 'user',
                'content'   => $message,
                'timestamp' => current_time( 'mysql' ),
            );

            // === AI Response Pipeline ===
            $reply = null;
            $debug_mode = SmartReplyr_DB::get_setting( 'debug_mode', '0' );
            $debug_info = array();

            // STAGE 1: Try offline NLP engine against Knowledge Base
            try {
                $nlp_match = SmartReplyr_NLP::match_query( $message, $lead );
                if ( $nlp_match && ! empty( $nlp_match['answer'] ) ) {
                    $reply = SmartReplyr_NLP::generate_response( $nlp_match, $lead, $messages );
                    SmartReplyr_DB::add_log('chat', 'nlp', 'success', "NLP match for lead #$lead_id (score: " . ( $nlp_match['_match_score'] ?? 'n/a' ) . ")", array('query' => $message));
                    if ( $debug_mode === '1' ) {
                        $debug_info = array(
                            'source'    => 'nlp_engine',
                            'intent'    => $nlp_match['_intent'] ?? 'none',
                            'score'     => $nlp_match['_match_score'] ?? 0,
                            'matched_q' => $nlp_match['question'] ?? '',
                        );
                    }
                }
            } catch ( Throwable $e ) {
                error_log( '[SmartReplyr] NLP engine error: ' . $e->getMessage() );
            }

            // STAGE 1.5: Try website content match (auto-crawled pages/posts)
            if ( $reply === null ) {
                try {
                    $site_match = SmartReplyr_NLP::match_site_content( $message, $page_context, $lead );
                    if ( $site_match && ! empty( $site_match['chunk_text'] ) ) {
                        $reply = SmartReplyr_NLP::generate_site_content_response( $site_match, $lead );
                        SmartReplyr_DB::add_log('chat', 'site_content', 'success', "Site content match for lead #$lead_id (score: " . ( $site_match['_match_score'] ?? 'n/a' ) . ")", array('query' => $message, 'source_url' => $site_match['source_url'] ?? ''));
                        if ( $debug_mode === '1' ) {
                            $debug_info = array(
                                'source'     => 'site_content',
                                'score'      => $site_match['_match_score'] ?? 0,
                                'page_title' => $site_match['title'] ?? '',
                                'source_url' => $site_match['source_url'] ?? '',
                            );
                        }
                    }
                } catch ( Throwable $e ) {
                    error_log( '[SmartReplyr] Site content match error: ' . $e->getMessage() );
                }
            }

            // STAGE 2: Try OpenAI if NLP didn't match and API key exists
            if ( $reply === null ) {
                try {
                    $api_key = SmartReplyr_DB::get_setting( 'openai_api_key', '' );
                    if ( ! empty( $api_key ) ) {
                        $ai = new SmartReplyr_AI();
                        $reply = $ai->get_response( $message, $messages, $page_context, $lead );
                        SmartReplyr_DB::add_log('chat', 'ai_processor', 'success', "OpenAI response for lead #$lead_id", array('query' => $message));
                        if ( $debug_mode === '1' ) {
                            $debug_info = array( 'source' => 'openai' );
                        }
                    }
                } catch ( Throwable $e ) {
                    error_log( '[SmartReplyr] OpenAI error: ' . $e->getMessage() );
                }
            }

            // STAGE 3: Smart offline fallback (ALWAYS succeeds — never fails)
            if ( $reply === null || $reply === '' ) {
                $reply = SmartReplyr_NLP::smart_fallback( $message, $lead, $messages );
                SmartReplyr_DB::add_log('chat', 'nlp_fallback', 'success', "Smart fallback for lead #$lead_id", array('query' => $message));
                if ( $debug_mode === '1' ) {
                    $debug_info = array( 'source' => 'smart_fallback' );
                }
            }

            // Add assistant message to history
            $messages[] = array(
                'role'      => 'assistant',
                'content'   => $reply,
                'timestamp' => current_time( 'mysql' ),
            );

            // Save conversation (non-fatal if this fails)
            try {
                if ( $conversation ) {
                    SmartReplyr_DB::update_conversation_messages( $conversation['id'], $messages );
                }
            } catch ( Throwable $e ) {
                error_log( '[SmartReplyr] Conversation save error: ' . $e->getMessage() );
            }

            $response_data = array(
                'success' => true,
                'reply'   => $reply,
            );

            if ( $debug_mode === '1' && ! empty( $debug_info ) ) {
                $response_data['debug_info'] = $debug_info;
            }

            return rest_ensure_response( $response_data );

        } catch ( Throwable $e ) {
            // Absolute last resort — STILL return success with a human-friendly fallback
            error_log( '[SmartReplyr Critical] Chat pipeline crash: ' . $e->getMessage() );
            $fallback = SmartReplyr_NLP::smart_fallback( $message ?? '', array(), array() );
            return rest_ensure_response(array(
                'success' => true,
                'reply'   => $fallback,
            ));
        }
    }

    /* ─── Widget Config ───────────────────────── */

    public function get_widget_config( $request ) {
        $settings = SmartReplyr_DB::get_all_settings();
        return rest_ensure_response( array(
            'bot_name'        => $settings['bot_name'] ?? 'SmartReplyr',
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
            'leads' => SmartReplyr_DB::get_leads( $args ),
            'total' => SmartReplyr_DB::count_leads( $args ),
        ) );
    }

    /* ─── Knowledge Base ──────────────────────── */

    public function get_kb( $request ) {
        return rest_ensure_response( SmartReplyr_DB::get_all_kb() );
    }

    public function save_kb( $request ) {
        $params = $request->get_json_params();
        $id = intval( $params['id'] ?? 0 );

        if ( $id ) {
            SmartReplyr_DB::update_kb( $id, $params );
        } else {
            $id = SmartReplyr_DB::insert_kb( $params );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function delete_kb( $request ) {
        $id = intval( $request['id'] );
        SmartReplyr_DB::delete_kb( $id );
        return rest_ensure_response( array( 'success' => true ) );
    }
}
