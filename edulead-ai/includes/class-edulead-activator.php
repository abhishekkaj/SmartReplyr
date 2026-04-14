<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_Activator {

    public static function activate() {
        self::create_tables();
        self::seed_defaults();
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── Leads ────────────────────────────────────
        $sql_leads = "CREATE TABLE {$prefix}edulead_leads (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(255) NOT NULL,
            phone         VARCHAR(50)  NOT NULL,
            email         VARCHAR(255) NOT NULL,
            course_interest VARCHAR(255) DEFAULT '',
            page_url      TEXT,
            page_title    VARCHAR(500) DEFAULT '',
            referrer      TEXT,
            utm_source    VARCHAR(255) DEFAULT '',
            utm_medium    VARCHAR(255) DEFAULT '',
            utm_campaign  VARCHAR(255) DEFAULT '',
            utm_term      VARCHAR(255) DEFAULT '',
            utm_content   VARCHAR(255) DEFAULT '',
            consent       TINYINT(1) DEFAULT 0,
            status        VARCHAR(50) DEFAULT 'new',
            webhook_sent  TINYINT(1) DEFAULT 0,
            email_sent    TINYINT(1) DEFAULT 0,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_email (email),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset;";

        // ── Conversations ────────────────────────────
        $sql_conversations = "CREATE TABLE {$prefix}edulead_conversations (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id       BIGINT(20) UNSIGNED NOT NULL,
            messages      LONGTEXT NOT NULL,
            page_context  TEXT,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_lead (lead_id)
        ) $charset;";

        // ── Settings ─────────────────────────────────
        $sql_settings = "CREATE TABLE {$prefix}edulead_settings (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            option_name   VARCHAR(255) NOT NULL,
            option_value  LONGTEXT,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_option_name (option_name)
        ) $charset;";

        // ── Knowledge Base ───────────────────────────
        $sql_kb = "CREATE TABLE {$prefix}edulead_knowledge_base (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question      TEXT NOT NULL,
            answer        LONGTEXT NOT NULL,
            keywords      LONGTEXT,
            intent        VARCHAR(255) DEFAULT '',
            category      VARCHAR(255) DEFAULT 'general',
            source        VARCHAR(50)  DEFAULT 'manual',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        dbDelta( $sql_leads );
        dbDelta( $sql_conversations );
        dbDelta( $sql_settings );
        dbDelta( $sql_kb );

        update_option( 'edulead_ai_db_version', EDULEAD_AI_DB_VERSION );
    }

    private static function seed_defaults() {
        global $wpdb;
        $table = $wpdb->prefix . 'edulead_settings';

        $defaults = array(
            'bot_name'          => 'EduLead AI',
            'primary_color'     => '#6C5CE7',
            'chat_position'     => 'bottom-right',
            'welcome_message'   => 'Hi there! 👋 I\'m here to help you explore our programs. Please share your details so I can assist you better.',
            'fallback_message'  => 'I\'m sorry, I don\'t have that information right now. Our team will get back to you soon!',
            'openai_api_key'    => '',
            'openai_model'      => 'gpt-4o-mini',
            'webhook_url'       => '',
            'webhook_enabled'   => '0',
            'field_mapping'     => '{}',
            'email_enabled'     => '0',
            'notification_email'=> '',
            'smtp_host'         => '',
            'smtp_port'         => '587',
            'smtp_username'     => '',
            'smtp_password'     => '',
            'smtp_encryption'   => 'tls',
            'gdpr_enabled'      => '1',
            'gdpr_text'         => 'I consent to having my data collected and stored.',
            'courses_list'      => 'MBA,BBA,B.Tech,M.Tech,BCA,MCA,B.Sc,M.Sc,Other',
            'avatar_url'        => '',
            'debug_mode'        => '0',
            'system_prompt'     => 'You are an AI education counselor for {{institute_name}}. Answer student queries about courses, fees, admissions, and campus. Be helpful, concise, and encourage the student to visit or apply. Use the knowledge base context provided. If you don\'t know the answer, say so politely and suggest contacting the admissions office.',
        );

        foreach ( $defaults as $key => $value ) {
            $wpdb->replace( $table, array(
                'option_name'  => $key,
                'option_value' => $value,
            ) );
        }
    }
}
