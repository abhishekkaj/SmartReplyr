<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_Webhook {

    /**
     * Send lead data to configured webhook URL with dynamic field mapping.
     */
    public function send( $lead ) {
        return smartreplyr_safe_execute(function() use ($lead) {
            $webhook_url = SmartReplyr_DB::get_setting( 'webhook_url', '' );
            if ( empty( $webhook_url ) ) {
                return false;
            }

            // Get field mapping
            $mapping_json = SmartReplyr_DB::get_setting( 'field_mapping', '{}' );
            $mapping      = json_decode( $mapping_json, true ) ?: array();

            // Inject lead_source from settings into the lead data for mapping
            $lead['lead_source'] = SmartReplyr_DB::get_setting( 'lead_source', 'smartreplyr-chatbot' );

            // Build payload with mapping
            $payload = $this->build_payload( $lead, $mapping );

            // Send POST request
            $response = wp_remote_post( $webhook_url, array(
                'timeout'     => 15,
                'sslverify'   => false,
                'user-agent'  => 'SmartReplyr-Webhook/1.0',
                'headers'     => array(
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode( $payload ),
            ) );

            if ( is_wp_error( $response ) ) {
                $err_msg = $response->get_error_message();
                SmartReplyr_DB::add_log('webhook', 'external', 'failed', "Webhook cURL Error: " . $err_msg, array('lead_id' => $lead['id'] ?? 0));
                error_log( 'SmartReplyr Webhook Error: ' . $err_msg );
                return false;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( $code < 200 || $code >= 300 ) {
                $body_resp = wp_remote_retrieve_body( $response );
                SmartReplyr_DB::add_log('webhook', 'external', 'failed', "Webhook HTTP $code: " . $body_resp, array('lead_id' => $lead['id'] ?? 0));
                error_log( 'SmartReplyr Webhook HTTP ' . $code . ': ' . $body_resp );
                return false;
            }

            return true;
        });
    }

    /**
     * Build payload with dynamic field mapping.
     * Supports fuzzy mapping for common user errors (e.g. mapping "First Name" instead of "name").
     */
    private function build_payload( $lead, $mapping ) {
        $payload = array();
        
        // Define internal-to-nice aliases to help users who use "First Name" instead of "name"
        $aliases = array(
            'name'            => array('name', 'full name', 'first name', 'contact name', 'lead name'),
            'email'           => array('email', 'email address', 'email id'),
            'phone'           => array('phone', 'phone number', 'mobile', 'mobile number', 'contact'),
            'course_interest' => array('course', 'course interest', 'interest', 'program'),
            'lead_source'     => array('lead source', 'source', 'origin', 'lead_source'),
        );

        // Normalize mapping keys to lowercase for comparison
        $norm_mapping = array();
        foreach($mapping as $k => $v) {
            $norm_mapping[strtolower(trim($k))] = $v;
        }

        // 1. Map all database columns
        foreach ( $lead as $key => $value ) {
            $external_key = $key; // default
            
            // Try exact match
            if ( isset( $norm_mapping[ $key ] ) ) {
                $external_key = $norm_mapping[ $key ];
            } 
            // Try fuzzy match via aliases
            else if ( isset( $aliases[ $key ] ) ) {
                foreach ( $aliases[ $key ] as $alias ) {
                    if ( isset( $norm_mapping[ $alias ] ) ) {
                        $external_key = $norm_mapping[ $alias ];
                        break;
                    }
                }
            }
            
            $payload[ $external_key ] = $value;
        }

        // 2. Ensure system metadata
        if ( ! isset( $payload['source'] ) ) { $payload['source'] = 'smartreplyr-ai'; }
        if ( ! isset( $payload['site_url'] ) ) { $payload['site_url'] = get_site_url(); }

        // 3. Structured UTM support
        $utm = array();
        foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $utm_key ) {
            if ( ! empty( $lead[ $utm_key ] ) ) {
                $utm[ $utm_key ] = $lead[ $utm_key ];
            }
        }
        if ( ! empty( $utm ) && ! isset( $payload['utm'] ) ) {
            $payload['utm'] = $utm;
        }

        return $payload;
    }
}
