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
            'Website Scanner',
            'Website Scanner',
            'manage_options',
            'smartreplyr-scanner',
            array( $this, 'view_scanner' )
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
            'general'      => array( 'bot_name', 'openai_api_key', 'openai_model', 'system_prompt', 'welcome_message', 'fallback_message', 'quick_prompts', 'debug_mode', 'gdpr_enabled', 'gdpr_text' ),
            'avatar'       => array( 'avatar_url', 'primary_color', 'chat_position', 'courses_list' ),
            'crm'          => array( 'webhook_enabled', 'webhook_url', 'lead_source', 'field_mapping' ),
            'email'        => array( 'email_enabled', 'notification_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption' ),
            'form-builder' => array( 'form_fields' ),
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
                } else if ( $field === 'form_fields' ) {
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

    /* ─── Website Scanner AJAX ────────────────── */

    public function ajax_sync_content() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        // Increase limits for crawling
        @set_time_limit( 120 );
        @ini_set( 'memory_limit', '256M' );

        $force = isset( $_POST['force_full'] ) && $_POST['force_full'] === '1';

        try {
            $stats = SmartReplyr_Crawler::crawl_all( $force );
            $db_stats = SmartReplyr_DB::get_site_content_stats();
            wp_send_json_success( array(
                'message'         => "Sync completed! {$stats['pages_processed']} pages scanned, {$stats['chunks_created']} content chunks created.",
                'stats'           => $stats,
                'total_chunks'    => $db_stats['total_chunks'],
                'total_pages'     => $db_stats['total_pages'],
                'last_sync'       => $db_stats['last_sync'],
            ) );
        } catch ( Throwable $e ) {
            error_log( '[SmartReplyr Crawler] Fatal: ' . $e->getMessage() );
            wp_send_json_error( 'Sync failed: ' . $e->getMessage() );
        }
    }

    public function ajax_clear_content() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        SmartReplyr_DB::clear_site_content();
        wp_send_json_success( array( 'message' => 'All crawled content has been cleared.' ) );
    }

    /* ─── KB Import / Export AJAX ─────────────── */

    /**
     * Handle Excel/CSV Knowledge Base import.
     * Accepts .csv and .xlsx files.
     */
    public function ajax_import_kb() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        if ( empty( $_FILES['kb_file'] ) || $_FILES['kb_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'No file uploaded or upload error.' );
        }

        $file = $_FILES['kb_file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
            wp_send_json_error( 'Invalid file type. Only .csv and .xlsx are accepted.' );
        }

        // Max 5MB
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            wp_send_json_error( 'File too large. Maximum size is 5MB.' );
        }

        $import_mode = isset( $_POST['import_mode'] ) ? sanitize_text_field( $_POST['import_mode'] ) : 'append';

        // Parse file into rows
        $rows = array();
        if ( $ext === 'csv' ) {
            $rows = $this->parse_csv( $file['tmp_name'] );
        } else {
            $rows = $this->parse_xlsx( $file['tmp_name'] );
        }

        if ( is_wp_error( $rows ) ) {
            wp_send_json_error( $rows->get_error_message() );
        }

        if ( empty( $rows ) ) {
            wp_send_json_error( 'No valid data rows found in the file.' );
        }

        // Clear existing KB if replace mode
        if ( $import_mode === 'replace' ) {
            SmartReplyr_DB::clear_all_kb();
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = array();

        foreach ( $rows as $i => $row ) {
            $row_num = $i + 2; // +2 because: 0-indexed + header row

            $question = isset( $row['question'] ) ? trim( $row['question'] ) : '';
            $answer   = isset( $row['answer'] )   ? trim( $row['answer'] )   : '';

            // Validation: Question and Answer are required
            if ( empty( $question ) || empty( $answer ) ) {
                $skipped++;
                $errors[] = "Row {$row_num}: Missing Question or Answer — skipped.";
                continue;
            }

            // Clean invalid characters
            $question = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $question );
            $answer   = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $answer );

            // Reject if too short after cleaning
            if ( strlen( $question ) < 3 || strlen( $answer ) < 10 ) {
                $skipped++;
                $errors[] = "Row {$row_num}: Question too short (min 3 chars) or Answer too short (min 10 chars) — skipped.";
                continue;
            }

            // Process keywords: normalize, lowercase, trim
            $keywords_raw = isset( $row['keywords'] ) ? trim( $row['keywords'] ) : '';
            $keywords = '';
            if ( ! empty( $keywords_raw ) ) {
                $kw_arr = array_filter( array_map( function( $k ) {
                    return strtolower( trim( preg_replace( '/[^a-zA-Z0-9\s\-]/u', '', $k ) ) );
                }, explode( ',', $keywords_raw ) ) );
                $keywords = implode( ', ', $kw_arr );
            }

            $intent   = isset( $row['intent'] )   ? strtolower( trim( $row['intent'] ) )   : 'general';
            $category = isset( $row['category'] ) ? strtolower( trim( $row['category'] ) ) : 'general';
            $source   = isset( $row['source'] )   ? trim( $row['source'] )                 : 'excel_import';

            // Sanitize intent/category to alphanumeric + underscore
            $intent   = preg_replace( '/[^a-z0-9_]/u', '', $intent )   ?: 'general';
            $category = preg_replace( '/[^a-z0-9_]/u', '', $category ) ?: 'general';

            $data = array(
                'question' => $question,
                'answer'   => $answer,
                'keywords' => $keywords,
                'intent'   => $intent,
                'category' => $category,
                'source'   => $source,
            );

            $result = SmartReplyr_DB::insert_kb( $data );
            if ( $result ) {
                $imported++;
            } else {
                $skipped++;
                $errors[] = "Row {$row_num}: Database insert failed — skipped.";
            }
        }

        $total_kb = SmartReplyr_DB::count_kb();

        wp_send_json_success( array(
            'message'   => "Import complete! {$imported} entries added, {$skipped} skipped.",
            'imported'  => $imported,
            'skipped'   => $skipped,
            'total_rows'=> count( $rows ),
            'total_kb'  => $total_kb,
            'errors'    => array_slice( $errors, 0, 10 ), // Max 10 error messages
        ) );
    }

    /**
     * Parse CSV file into associative array of rows.
     */
    private function parse_csv( $filepath ) {
        $handle = fopen( $filepath, 'r' );
        if ( ! $handle ) return new WP_Error( 'file_error', 'Could not read the CSV file.' );

        // Try to detect BOM and skip it
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            return new WP_Error( 'format_error', 'CSV file has no header row.' );
        }

        // Normalize headers: lowercase, trim
        $header = array_map( function( $h ) {
            return strtolower( trim( preg_replace( '/[^a-z0-9_]/u', '', strtolower( trim( $h ) ) ) ) );
        }, $header );

        // Validate required columns
        if ( ! in_array( 'question', $header ) || ! in_array( 'answer', $header ) ) {
            fclose( $handle );
            return new WP_Error( 'format_error', 'CSV must have "Question" and "Answer" columns.' );
        }

        $rows = array();
        while ( ( $line = fgetcsv( $handle ) ) !== false ) {
            if ( count( $line ) < count( $header ) ) {
                $line = array_pad( $line, count( $header ), '' );
            }
            $row = array_combine( $header, array_slice( $line, 0, count( $header ) ) );
            if ( $row !== false ) {
                $rows[] = $row;
            }
        }

        fclose( $handle );
        return $rows;
    }

    /**
     * Parse XLSX file into associative array using ZipArchive + XML.
     * Lightweight — no external library required.
     */
    private function parse_xlsx( $filepath ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'dependency_error', 'PHP ZipArchive extension is required for .xlsx import. Please use .csv format instead.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $filepath ) !== true ) {
            return new WP_Error( 'file_error', 'Could not open the XLSX file.' );
        }

        // Read shared strings
        $strings = array();
        $ss_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $ss_xml ) {
            $ss = simplexml_load_string( $ss_xml );
            if ( $ss ) {
                foreach ( $ss->si as $si ) {
                    // Handle both simple <t> and rich text <r><t>
                    $text = '';
                    if ( isset( $si->t ) ) {
                        $text = (string) $si->t;
                    } elseif ( isset( $si->r ) ) {
                        foreach ( $si->r as $r ) {
                            $text .= (string) $r->t;
                        }
                    }
                    $strings[] = $text;
                }
            }
        }

        // Read worksheet (sheet1)
        $sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
        if ( ! $sheet_xml ) {
            $zip->close();
            return new WP_Error( 'format_error', 'Could not read worksheet data from XLSX.' );
        }

        $sheet = simplexml_load_string( $sheet_xml );
        $zip->close();

        if ( ! $sheet || ! isset( $sheet->sheetData->row ) ) {
            return new WP_Error( 'format_error', 'XLSX file appears empty or malformed.' );
        }

        $all_rows = array();
        foreach ( $sheet->sheetData->row as $row ) {
            $row_data = array();
            foreach ( $row->c as $cell ) {
                $col_letter = preg_replace( '/[0-9]/', '', (string) $cell['r'] );
                $col_index  = $this->xlsx_col_to_index( $col_letter );
                $value = '';
                if ( isset( $cell['t'] ) && (string) $cell['t'] === 's' ) {
                    // Shared string reference
                    $idx = intval( (string) $cell->v );
                    $value = isset( $strings[ $idx ] ) ? $strings[ $idx ] : '';
                } elseif ( isset( $cell->v ) ) {
                    $value = (string) $cell->v;
                } elseif ( isset( $cell->is ) ) {
                    $value = (string) $cell->is->t;
                }
                $row_data[ $col_index ] = $value;
            }
            $all_rows[] = $row_data;
        }

        if ( count( $all_rows ) < 2 ) {
            return new WP_Error( 'format_error', 'XLSX must have a header row and at least one data row.' );
        }

        // First row = header
        $header_raw = $all_rows[0];
        $max_col = max( array_keys( $header_raw ) );
        $header = array();
        for ( $c = 0; $c <= $max_col; $c++ ) {
            $h = isset( $header_raw[ $c ] ) ? $header_raw[ $c ] : '';
            $header[ $c ] = strtolower( trim( preg_replace( '/[^a-z0-9_]/u', '', strtolower( trim( $h ) ) ) ) );
        }

        if ( ! in_array( 'question', $header ) || ! in_array( 'answer', $header ) ) {
            return new WP_Error( 'format_error', 'XLSX must have "Question" and "Answer" columns in the header row.' );
        }

        $rows = array();
        for ( $r = 1; $r < count( $all_rows ); $r++ ) {
            $row = array();
            foreach ( $header as $col_idx => $col_name ) {
                if ( empty( $col_name ) ) continue;
                $row[ $col_name ] = isset( $all_rows[ $r ][ $col_idx ] ) ? $all_rows[ $r ][ $col_idx ] : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Convert Excel column letter (A, B, ..., Z, AA, AB) to 0-based index.
     */
    private function xlsx_col_to_index( $col ) {
        $col = strtoupper( $col );
        $index = 0;
        for ( $i = 0; $i < strlen( $col ); $i++ ) {
            $index = $index * 26 + ( ord( $col[ $i ] ) - ord( 'A' ) + 1 );
        }
        return $index - 1;
    }

    /**
     * Generate and serve a sample CSV template for KB import.
     */
    public function ajax_download_kb_template() {
        check_ajax_referer( 'smartreplyr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        // Clean any output buffers to prevent corrupted CSV or header errors
        if ( ob_get_length() ) {
            ob_clean();
        }

        $filename = 'smartreplyr-kb-template.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );

        // BOM for Excel UTF-8 compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // Header row
        fputcsv( $output, array( 'Question', 'Answer', 'Keywords', 'Intent', 'Category', 'Source' ) );

        // Sample rows
        fputcsv( $output, array(
            'What is the fee structure for MBA?',
            'Our MBA program fee is Rs 1,20,000 per year. We offer merit-based scholarships covering up to 50% of the fee. EMI options are also available.',
            'fees, cost, mba fees, scholarship, emi',
            'fees',
            'financial',
            'manual',
        ) );
        fputcsv( $output, array(
            'What are the admission requirements?',
            'Admission requires a minimum of 50% in graduation with a valid entrance exam score. Apply online through our admissions portal.',
            'admission, apply, eligibility, requirements',
            'admission',
            'academic',
            'manual',
        ) );
        fputcsv( $output, array(
            'What courses do you offer?',
            'We offer BBA, MBA, BCA, MCA, B.Tech, M.Tech, and various diploma programs across multiple specializations.',
            'courses, programs, degree, specialization',
            'courses',
            'academic',
            'manual',
        ) );

        fclose( $output );
        exit;
    }

    /* ─── Views ───────────────────────────────── */

    public function view_dashboard() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-dashboard.php'; }
    public function view_leads() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-leads.php'; }
    public function view_conversations() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-conversations.php'; }
    public function view_knowledge_base() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-knowledge-base.php'; }
    public function view_scanner() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/admin-website-scanner.php'; }
    public function view_settings() { include SMARTREPLYR_PLUGIN_DIR . 'admin/views/settings-page.php'; }
}
