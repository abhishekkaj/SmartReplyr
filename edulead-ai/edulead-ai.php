<?php
/**
 * Plugin Name:       EduLead AI
 * Plugin URI:        https://github.com/abhishekjha/edulead-ai
 * Description:       AI-powered lead generation chatbot for education institutes. Captures leads first, then answers queries using website + custom knowledge base data.
 * Version:           1.0.0
 * Author:            Abhishek Jha
 * Author URI:        https://abhishekjha.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       edulead-ai
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ──────────────────────────────────────────────
 * Constants
 * ──────────────────────────────────────────── */
define( 'EDULEAD_AI_VERSION', '1.1.0' );
define( 'EDULEAD_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDULEAD_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EDULEAD_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'EDULEAD_AI_DB_VERSION', '1.1.0' );

/* ──────────────────────────────────────────────
 * Activation / Deactivation / Uninstall
 * ──────────────────────────────────────────── */
register_activation_hook( __FILE__, array( 'EduLead_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EduLead_Deactivator', 'deactivate' ) );

/* ──────────────────────────────────────────────
 * Autoload includes
 * ──────────────────────────────────────────── */
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-activator.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-deactivator.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-loader.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-db.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-rest-api.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-nlp.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-ai.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-webhook.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'includes/class-edulead-email.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'admin/class-edulead-admin.php';
require_once EDULEAD_AI_PLUGIN_DIR . 'public/class-edulead-public.php';

/* ──────────────────────────────────────────────
 * DB Migration
 * ──────────────────────────────────────────── */
add_action( 'plugins_loaded', function() {
    if ( get_option( 'edulead_ai_db_version' ) !== EDULEAD_AI_DB_VERSION ) {
        EduLead_Activator::activate();
    }
} );

/* ──────────────────────────────────────────────
 * Boot the plugin
 * ──────────────────────────────────────────── */
function edulead_ai_run() {
    $loader = new EduLead_Loader();

    // Admin hooks
    $admin = new EduLead_Admin();
    $loader->add_action( 'admin_menu', $admin, 'register_menus' );
    $loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
    $loader->add_action( 'admin_init', $admin, 'handle_settings' );
    $loader->add_action( 'wp_ajax_edulead_export_csv', $admin, 'export_csv' );
    $loader->add_action( 'wp_ajax_edulead_save_kb', $admin, 'ajax_save_kb' );
    $loader->add_action( 'wp_ajax_edulead_delete_kb', $admin, 'ajax_delete_kb' );

    // Public hooks
    $public = new EduLead_Public();
    $loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );
    $loader->add_action( 'wp_footer', $public, 'render_widget' );

    // REST API
    $rest = new EduLead_REST_API();
    $loader->add_action( 'rest_api_init', $rest, 'register_routes' );

    $loader->run();
}
edulead_ai_run();
