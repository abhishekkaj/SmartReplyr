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
                'data_format' => 'body',
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
     */
    private function build_payload( $lead, $mapping ) {
        // Default field names
        $default_fields = array(
            'name'            => 'name',
            'phone'           => 'phone',
            'email'           => 'email',
            'course_interest' => 'course_interest',
            'page_url'        => 'page_url',
            'page_title'      => 'page_title',
            'referrer'        => 'referrer',
        );

        $payload = array();

        // Map fields
        foreach ( $default_fields as $internal => $default_external ) {
            $external = isset( $mapping[ $internal ] ) && ! empty( $mapping[ $internal ] )
                ? $mapping[ $internal ]
                : $default_external;
            $payload[ $external ] = $lead[ $internal ] ?? '';
        }

        // Always include metadata
        $payload['source']        = 'smartreplyr-ai';
        $payload['timestamp']     = $lead['created_at'] ?? current_time( 'mysql' );
        $payload['site_url']      = get_site_url();

        // UTM parameters
        $utm = array();
        foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $key ) {
            if ( ! empty( $lead[ $key ] ) ) {
                $utm[ $key ] = $lead[ $key ];
            }
        }
        if ( ! empty( $utm ) ) {
            $payload['utm'] = $utm;
        }

        return $payload;
    }
}
