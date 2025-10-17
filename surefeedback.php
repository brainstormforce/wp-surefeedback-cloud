<?php
/**
 * Plugin Name: SureFeedback Client
 * Plugin URI: http://surefeedback.com
 * Description: Collect note-style feedback from your client's websites and sync them with your SureFeedback parent project.
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * Version: 1.0.0
 * Developer: Anurag Singh <anurags@bsf.io>
 *
 * Requires at least: 4.7
 * Tested up to: 6.8
 *
 * Text Domain: surefeedback
 * Domain Path: languages
 *
 * @package SureFeedback
 * @author Brainstorm Force
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Constants before init
 *
 * @since 1.0.0
 */

// Plugin Folder Path.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_DIR' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_URL' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin Root File.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_FILE' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_FILE', __FILE__ );
}

// Plugin Version.
if ( ! defined( 'SUREFEEDBACK_VERSION' ) ) {
	define( 'SUREFEEDBACK_VERSION', '1.0.1' );
}

// Plugin Basename.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_BASENAME' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Include Environment Helper
require_once SUREFEEDBACK_PLUGIN_DIR . 'includes/env-helper.php';

/*
|--------------------------------------------------------------------------
| Bootstrap The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel-style application 
| instance which serves as the "glue" for all the components, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = require_once __DIR__ . '/bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

// Boot the application when plugins are loaded
add_action('plugins_loaded', function() use ($app) {
    $app->boot();
});

/**
 * Plugin activation hook
 */
register_activation_hook(SUREFEEDBACK_PLUGIN_FILE, function() {
    // Set installation date for tracking
    if (!get_option('surefeedback_installation_date')) {
        update_option('surefeedback_installation_date', current_time('mysql'));
    }
    
    // Set default settings
    $defaults = [
        'surefeedback_widget_enabled' => true,
        'surefeedback_role_can_comment' => ['administrator'],
        'surefeedback_guest_comments' => false,
        'surefeedback_admin_can_comment' => true,
        'surefeedback_show_on_admin' => false,
        'surefeedback_disable_for_admin' => false,
        'surefeedback_debug_mode' => false
    ];
    
    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            update_option($option, $value);
        }
    }
    
    // Set activation redirect flag
    update_option('surefeedback_activation_redirect', true);
});

/**
 * Redirect to welcome page after activation
 */
add_action('admin_init', function() {
    // Check if we should redirect to welcome page
    if (get_option('surefeedback_activation_redirect', false)) {
        // Clear the redirect flag
        delete_option('surefeedback_activation_redirect');

        // Only redirect if this is a single plugin activation (not bulk)
        if (!isset($_GET['activate-multi']) && !wp_doing_ajax() && !wp_doing_cron()) {
            wp_safe_redirect(admin_url('admin.php?page=surefeedback-connection#setup'));
            exit;
        }
    }
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(SUREFEEDBACK_PLUGIN_FILE, function() {
    // Clear scheduled events
    wp_clear_scheduled_hook('surefeedback_auto_verify');
    wp_clear_scheduled_hook('surefeedback_hourly_verify');
});

/**
 * Load plugin text domain for internationalization
 */
add_action('init', function() {
    load_plugin_textdomain(
        'surefeedback',
        false,
        dirname(plugin_basename(SUREFEEDBACK_PLUGIN_FILE)) . '/languages/'
    );
});

/**
 * Handle automatic verification using application services
 */
add_action('surefeedback_auto_verify', function() {
    global $app;
    try {
        $saas_client = $app->make('SureFeedback\Services\SaasClientService');
        $saas_client->verify_script_integration();
    } catch (Exception $e) {
        // Error handled silently
    }
});

/**
 * Handle hourly verification updates
 */
add_action('surefeedback_hourly_verify', function() {
    global $app;
    try {
        $saas_client = $app->make('SureFeedback\Services\SaasClientService');
        $saas_client->schedule_verification();
    } catch (Exception $e) {
        // Error handled silently
    }
});

/**
 * Add settings link to plugin list table
 */
add_filter('plugin_action_links_' . SUREFEEDBACK_PLUGIN_BASENAME, function($links) {
    $dashboard_link = '<a href="' . admin_url('admin.php?page=surefeedback-connection') . '">' . __('Dashboard', 'surefeedback') . '</a>';
    $settings_link = '<a href="' . admin_url('admin.php?page=surefeedback-settings') . '">' . __('Settings', 'surefeedback') . '</a>';
    array_unshift($links, $dashboard_link, $settings_link);
    return $links;
});

/**
 * White label text replacement on plugins page
 */
add_action('admin_init', function() {
    global $pagenow;
    if (is_admin() && 'plugins.php' === $pagenow) {
        add_filter('gettext', function($translated_text, $untranslated_text, $domain) {
            if ('surefeedback' !== $domain) {
                return $translated_text;
            }
            
            switch ($untranslated_text) {
                case 'SureFeedback Client':
                    $name = get_option('surefeedback_plugin_name');
                    return $name ?: $translated_text;
                    
                case 'Collect note-style feedback from your client\'s websites and sync them with your SureFeedback parent project.':
                    $description = get_option('surefeedback_plugin_description');
                    return $description ?: $translated_text;
                    
                case 'Brainstorm Force':
                    $author = get_option('surefeedback_plugin_author');
                    return $author ?: $translated_text;
                    
                case 'https://www.brainstormforce.com':
                    $author_url = get_option('surefeedback_plugin_author_url');
                    return $author_url ?: $translated_text;
                    
                case 'http://surefeedback.com':
                    $plugin_link = get_option('surefeedback_plugin_link');
                    return $plugin_link ?: $translated_text;
            }
            
            return $translated_text;
        }, 20, 3);
    }
});

