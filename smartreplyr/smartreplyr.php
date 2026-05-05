<?php
/**
 * Plugin Name:       SmartReplyr
 * Plugin URI:        https://github.com/abhishekjha/smartreplyr
 * Description:       Turn Visitors Into Leads Automatically. Captures leads first, then answers queries using website + custom knowledge base data.
 * Version:           2.0.0
 * Author:            Abhishek Jha
 * Author URI:        
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smartreplyr
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('smartreplyr_sandbox_shutdown')) {
    function smartreplyr_sandbox_shutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            error_log('[SmartReplyr Fatal Sandbox] ' . print_r($error, true));
        }
    }
    register_shutdown_function('smartreplyr_sandbox_shutdown');
}

if (!function_exists('smartreplyr_safe_execute')) {
    function smartreplyr_safe_execute($callback)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            error_log('[SmartReplyr Error] ' . $e->getMessage());
            return null;
        }
    }
}

/* ──────────────────────────────────────────────
 * Constants
 * ──────────────────────────────────────────── */
define('SMARTREPLYR_VERSION', '2.3.0');
define('SMARTREPLYR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMARTREPLYR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMARTREPLYR_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SMARTREPLYR_DB_VERSION', '2.3.0');

/* ──────────────────────────────────────────────
 * Activation / Deactivation / Uninstall
 * ──────────────────────────────────────────── */
register_activation_hook(__FILE__, array('SmartReplyr_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('SmartReplyr_Deactivator', 'deactivate'));

/* ──────────────────────────────────────────────
 * Autoload includes
 * ──────────────────────────────────────────── */
try {
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-activator.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-deactivator.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-loader.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-db.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-rest-api.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-nlp.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-ai.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-crawler.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-webhook.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'includes/class-smartreplyr-email.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'admin/class-smartreplyr-admin.php';
    require_once SMARTREPLYR_PLUGIN_DIR . 'public/class-smartreplyr-public.php';
} catch (Throwable $e) {
    error_log('[SmartReplyr Critical Error] Failed to load plugin files: ' . $e->getMessage());
    return; // Prevent further execution that would crash the site
}

/* ──────────────────────────────────────────────
 * DB Migration
 * ──────────────────────────────────────────── */
add_action('plugins_loaded', function () {
    if (get_option('smartreplyr_db_version') !== SMARTREPLYR_DB_VERSION) {
        SmartReplyr_Activator::activate();
    }
});

/* ──────────────────────────────────────────────
 * Boot the plugin
 * ──────────────────────────────────────────── */
function smartreplyr_run()
{
    $loader = new SmartReplyr_Loader();

    // Admin hooks
    $admin = new SmartReplyr_Admin();
    $loader->add_action('admin_menu', $admin, 'register_menus');
    $loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_assets');
    $loader->add_action('admin_init', $admin, 'handle_settings');
    $loader->add_action('wp_ajax_smartreplyr_export_csv', $admin, 'export_csv');
    $loader->add_action('wp_ajax_smartreplyr_save_kb', $admin, 'ajax_save_kb');
    $loader->add_action('wp_ajax_smartreplyr_delete_kb', $admin, 'ajax_delete_kb');
    $loader->add_action('wp_ajax_smartreplyr_sync_content', $admin, 'ajax_sync_content');
    $loader->add_action('wp_ajax_smartreplyr_clear_content', $admin, 'ajax_clear_content');
    $loader->add_action('wp_ajax_smartreplyr_import_kb', $admin, 'ajax_import_kb');
    $loader->add_action('wp_ajax_smartreplyr_download_kb_template', $admin, 'ajax_download_kb_template');

    // Public hooks
    $public = new SmartReplyr_Public();
    $loader->add_action('wp_enqueue_scripts', $public, 'enqueue_assets');
    $loader->add_action('wp_footer', $public, 'render_widget');

    // Shortcodes
    add_shortcode('smartreplyr_chat', array($public, 'render_shortcode'));
    add_shortcode('edulead_chat', array($public, 'render_shortcode'));

    // REST API
    $rest = new SmartReplyr_REST_API();
    $loader->add_action('rest_api_init', $rest, 'register_routes');

    smartreplyr_safe_execute(function () use ($loader) {
        $loader->run();
    });
}
smartreplyr_run();
