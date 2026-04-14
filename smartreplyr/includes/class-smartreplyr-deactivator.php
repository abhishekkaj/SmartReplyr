<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_Deactivator {

    public static function deactivate() {
        // Clear any scheduled cron events
        wp_clear_scheduled_hook( 'smartreplyr_daily_cleanup' );
        flush_rewrite_rules();
    }
}
