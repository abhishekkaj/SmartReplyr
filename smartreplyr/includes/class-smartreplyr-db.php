<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_DB {

    private static $tables_cache = array();

    private static function get_table_name($new_name, $old_name) {
        global $wpdb;
        if ( isset( self::$tables_cache[$new_name] ) ) {
            return self::$tables_cache[$new_name];
        }
        $new_table = $wpdb->prefix . $new_name;
        $old_table = $wpdb->prefix . $old_name;
        $exists_new = $wpdb->get_var("SHOW TABLES LIKE '$new_table'");
        if ( $exists_new === $new_table ) {
            self::$tables_cache[$new_name] = $new_table;
        } else {
            $exists_old = $wpdb->get_var("SHOW TABLES LIKE '$old_table'");
            if ( $exists_old === $old_table ) {
                self::$tables_cache[$new_name] = $old_table;
            } else {
                self::$tables_cache[$new_name] = $new_table;
            }
        }
        return self::$tables_cache[$new_name];
    }

    public static function get_leads_table() { return self::get_table_name('smartreplyr_leads', 'edulead_leads'); }
    public static function get_conversations_table() { return self::get_table_name('smartreplyr_conversations', 'edulead_conversations'); }
    public static function get_settings_table() { return self::get_table_name('smartreplyr_settings', 'edulead_settings'); }
    public static function get_kb_table() { return self::get_table_name('smartreplyr_knowledge_base', 'edulead_knowledge_base'); }

    /* ─── Settings ────────────────────────────── */

    public static function get_setting( $key, $default = '' ) {
        global $wpdb;
        $table = self::get_settings_table();
        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM $table WHERE option_name = %s",
            $key
        ) );
        return $value !== null ? $value : $default;
    }

    public static function update_setting( $key, $value ) {
        global $wpdb;
        $table = self::get_settings_table();
        return $wpdb->replace( $table, array(
            'option_name'  => sanitize_text_field( $key ),
            'option_value' => $value,
        ) );
    }

    public static function get_all_settings() {
        global $wpdb;
        $table   = self::get_settings_table();
        $rows    = $wpdb->get_results( "SELECT option_name, option_value FROM $table", ARRAY_A );
        $settings = array();
        if ( $rows ) {
            foreach ( $rows as $row ) {
                $settings[ $row['option_name'] ] = $row['option_value'];
            }
        }
        return $settings;
    }

    /* ─── Leads ───────────────────────────────── */

    public static function insert_lead( $data ) {
        global $wpdb;
        $table = self::get_leads_table();

        $result = $wpdb->insert( $table, array(
            'name'            => sanitize_text_field( $data['name'] ),
            'phone'           => sanitize_text_field( $data['phone'] ),
            'email'           => sanitize_email( $data['email'] ),
            'course_interest' => sanitize_text_field( $data['course_interest'] ?? '' ),
            'page_url'        => esc_url_raw( $data['page_url'] ?? '' ),
            'page_title'      => sanitize_text_field( $data['page_title'] ?? '' ),
            'referrer'        => esc_url_raw( $data['referrer'] ?? '' ),
            'utm_source'      => sanitize_text_field( $data['utm_source'] ?? '' ),
            'utm_medium'      => sanitize_text_field( $data['utm_medium'] ?? '' ),
            'utm_campaign'    => sanitize_text_field( $data['utm_campaign'] ?? '' ),
            'utm_term'        => sanitize_text_field( $data['utm_term'] ?? '' ),
            'utm_content'     => sanitize_text_field( $data['utm_content'] ?? '' ),
            'consent'         => intval( $data['consent'] ?? 0 ),
            'status'          => 'new',
            'created_at'      => current_time( 'mysql' ),
        ) );

        return $result ? $wpdb->insert_id : false;
    }

    public static function get_lead( $id ) {
        global $wpdb;
        $table = self::get_leads_table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d", $id
        ), ARRAY_A );
    }

    public static function get_leads( $args = array() ) {
        global $wpdb;
        $table = self::get_leads_table();

        $where   = '1=1';
        $params  = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['course'] ) ) {
            $where   .= ' AND course_interest = %s';
            $params[] = $args['course'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $order   = 'ORDER BY created_at DESC';
        $limit   = '';
        if ( isset( $args['per_page'] ) ) {
            $offset  = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
            $limit   = $wpdb->prepare( ' LIMIT %d OFFSET %d', intval( $args['per_page'] ), $offset );
        }

        $sql = "SELECT * FROM $table WHERE $where $order$limit";
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    public static function count_leads( $args = array() ) {
        global $wpdb;
        $table = self::get_leads_table();

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE $where";
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return (int) $wpdb->get_var( $sql );
    }

    public static function update_lead( $id, $data ) {
        global $wpdb;
        $table = self::get_leads_table();
        return $wpdb->update( $table, $data, array( 'id' => $id ) );
    }

    /* ─── Conversations ───────────────────────── */

    public static function create_conversation( $lead_id, $page_context = '' ) {
        global $wpdb;
        $table = self::get_conversations_table();
        $wpdb->insert( $table, array(
            'lead_id'      => intval( $lead_id ),
            'messages'     => wp_json_encode( array() ),
            'page_context' => sanitize_text_field( $page_context ),
            'created_at'   => current_time( 'mysql' ),
        ) );
        return $wpdb->insert_id;
    }

    public static function get_conversation( $id ) {
        global $wpdb;
        $table = self::get_conversations_table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d", $id
        ), ARRAY_A );
    }

    public static function get_conversation_by_lead( $lead_id ) {
        global $wpdb;
        $table = self::get_conversations_table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE lead_id = %d ORDER BY updated_at DESC LIMIT 1",
            $lead_id
        ), ARRAY_A );
    }

    public static function update_conversation_messages( $id, $messages ) {
        global $wpdb;
        $table = self::get_conversations_table();
        return $wpdb->update( $table, array(
            'messages'   => wp_json_encode( $messages ),
            'updated_at' => current_time( 'mysql' ),
        ), array( 'id' => $id ) );
    }

    public static function get_conversations( $args = array() ) {
        global $wpdb;
        $t_conv = self::get_conversations_table();
        $t_lead = self::get_leads_table();

        $order = 'ORDER BY c.updated_at DESC';
        $limit = '';
        if ( isset( $args['per_page'] ) ) {
            $offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
            $limit  = $wpdb->prepare( ' LIMIT %d OFFSET %d', intval( $args['per_page'] ), $offset );
        }

        return $wpdb->get_results(
            "SELECT c.*, l.name, l.email, l.phone
             FROM $t_conv c
             LEFT JOIN $t_lead l ON l.id = c.lead_id
             $order $limit",
            ARRAY_A
        );
    }

    /* ─── Knowledge Base ──────────────────────── */

    public static function insert_kb( $data ) {
        global $wpdb;
        $table = self::get_kb_table();
        $wpdb->insert( $table, array(
            'question'   => sanitize_textarea_field( $data['question'] ),
            'answer'     => wp_kses_post( $data['answer'] ),
            'keywords'   => isset($data['keywords']) ? wp_json_encode(array_map('trim', explode(',', $data['keywords']))) : '[]',
            'intent'     => sanitize_text_field( $data['intent'] ?? 'general' ),
            'category'   => sanitize_text_field( $data['category'] ?? 'general' ),
            'source'     => sanitize_text_field( $data['source'] ?? 'manual' ),
            'created_at' => current_time( 'mysql' ),
        ) );
        return $wpdb->insert_id;
    }

    public static function update_kb( $id, $data ) {
        global $wpdb;
        $table = self::get_kb_table();
        $update = array();
        if ( isset( $data['question'] ) ) $update['question'] = sanitize_textarea_field( $data['question'] );
        if ( isset( $data['answer'] ) )   $update['answer']   = wp_kses_post( $data['answer'] );
        if ( isset( $data['keywords'] ) ) $update['keywords'] = wp_json_encode(array_map('trim', explode(',', $data['keywords'])));
        if ( isset( $data['intent'] ) )   $update['intent']   = sanitize_text_field( $data['intent'] );
        if ( isset( $data['category'] ) ) $update['category'] = sanitize_text_field( $data['category'] );
        return $wpdb->update( $table, $update, array( 'id' => $id ) );
    }

    public static function delete_kb( $id ) {
        global $wpdb;
        $table = self::get_kb_table();
        return $wpdb->delete( $table, array( 'id' => intval( $id ) ) );
    }

    public static function get_all_kb() {
        global $wpdb;
        $table = self::get_kb_table();
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );
    }

    public static function search_kb( $query, $limit = 5 ) {
        global $wpdb;
        $table = self::get_kb_table();
        $like  = '%' . $wpdb->esc_like( $query ) . '%';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, question, answer, keywords, intent, category FROM $table
             WHERE question LIKE %s OR answer LIKE %s OR keywords LIKE %s OR category LIKE %s
             ORDER BY
                CASE WHEN question LIKE %s THEN 0 ELSE 1 END,
                created_at DESC
             LIMIT %d",
            $like, $like, $like, $like, $like, $limit
        ), ARRAY_A );
    }
}
