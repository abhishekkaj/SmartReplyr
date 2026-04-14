<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EduLead_Deactivator {

    public static function deactivate() {
        // Clear any scheduled cron events
        wp_clear_scheduled_hook( 'edulead_daily_cleanup' );
        flush_rewrite_rules();
    }
}
