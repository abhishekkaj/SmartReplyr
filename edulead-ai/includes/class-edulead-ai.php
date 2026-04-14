<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_AI {

    private $api_key;
    private $model;
    private $system_prompt;

    public function __construct() {
        $this->api_key       = EduLead_DB::get_setting( 'openai_api_key', '' );
        $this->model         = EduLead_DB::get_setting( 'openai_model', 'gpt-4o-mini' );
        $this->system_prompt = EduLead_DB::get_setting( 'system_prompt', '' );
    }

    /**
     * Get AI response for user message.
     */
    public function get_response( $user_message, $history, $page_context, $lead ) {
        if ( empty( $this->api_key ) ) {
            return EduLead_DB::get_setting( 'fallback_message', 'Our team will get back to you shortly!' );
        }

        // Search knowledge base for relevant context
        $kb_results = EduLead_DB::search_kb( $user_message, 5 );
        $kb_context = $this->format_kb_context( $kb_results );

        // Build system prompt with context
        $system = $this->build_system_prompt( $kb_context, $page_context, $lead );

        // Build messages array for API
        $api_messages = array();
        $api_messages[] = array( 'role' => 'system', 'content' => $system );

        // Add conversation history (last 10 messages for token management)
        $recent = array_slice( $history, -10 );
        foreach ( $recent as $msg ) {
            if ( in_array( $msg['role'], array( 'user', 'assistant' ), true ) ) {
                $api_messages[] = array(
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                );
            }
        }

        // Call OpenAI API
        $response = $this->call_openai( $api_messages );

        if ( is_wp_error( $response ) ) {
            error_log( 'EduLead AI Error: ' . $response->get_error_message() );
            return EduLead_DB::get_setting( 'fallback_message', 'I\'m experiencing a technical issue. Please try again or contact our admissions team directly.' );
        }

        return $response;
    }

    /**
     * Build contextual system prompt.
     */
    private function build_system_prompt( $kb_context, $page_context, $lead ) {
        $prompt = $this->system_prompt;

        // Replace placeholders
        $site_name = get_bloginfo( 'name' );
        $prompt = str_replace( '{{institute_name}}', $site_name, $prompt );

        // Append knowledge base context
        if ( ! empty( $kb_context ) ) {
            $prompt .= "\n\n--- KNOWLEDGE BASE CONTEXT ---\n";
            $prompt .= "Use the following information to answer questions. If the answer is in this context, use it:\n\n";
            $prompt .= $kb_context;
        }

        // Append page context
        if ( ! empty( $page_context ) ) {
            $prompt .= "\n\n--- PAGE CONTEXT ---\n";
            $prompt .= "The student is currently viewing: " . $page_context . "\n";
            $prompt .= "Prioritize information relevant to this page when answering.\n";
        }

        // Append lead info
        if ( ! empty( $lead ) ) {
            $prompt .= "\n\n--- STUDENT INFO ---\n";
            $prompt .= "Name: " . ( $lead['name'] ?? 'Unknown' ) . "\n";
            if ( ! empty( $lead['course_interest'] ) ) {
                $prompt .= "Interested in: " . $lead['course_interest'] . "\n";
            }
        }

        $prompt .= "\n\nIMPORTANT RULES:\n";
        $prompt .= "1. Be conversational, friendly, and helpful.\n";
        $prompt .= "2. Keep answers concise (2-3 paragraphs max).\n";
        $prompt .= "3. If you don't know something, say so and suggest contacting the admissions office.\n";
        $prompt .= "4. Encourage the student to visit, apply, or schedule a campus tour.\n";
        $prompt .= "5. Use the student's name occasionally to personalize responses.\n";

        return $prompt;
    }

    /**
     * Format knowledge base results into context string.
     */
    private function format_kb_context( $results ) {
        if ( empty( $results ) ) {
            return '';
        }

        $context = '';
        foreach ( $results as $item ) {
            $context .= "Q: " . $item['question'] . "\n";
            $context .= "A: " . $item['answer'] . "\n";
            if ( ! empty( $item['category'] ) ) {
                $context .= "Category: " . $item['category'] . "\n";
            }
            $context .= "\n";
        }

        return $context;
    }

    /**
     * Call OpenAI Chat Completions API.
     */
    private function call_openai( $messages ) {
        $body = array(
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => 500,
            'temperature' => 0.7,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $err_msg = $data['error']['message'] ?? 'Unknown API error';
            return new WP_Error( 'openai_error', $err_msg );
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
