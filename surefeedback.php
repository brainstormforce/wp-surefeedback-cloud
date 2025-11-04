<?php
/**
 * Plugin Name: SureFeedback Client
 * Plugin URI: http://surefeedback.com
 * Description: Collect note-style feedback from your client's websites and sync them with your SureFeedback parent project.
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * Version: 0.0.1
 * Developer: Anurag Singh <anurags@bsf.io>
 *
 * Requires at least: 4.7
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
	define( 'SUREFEEDBACK_VERSION', '0.0.1' );
}

// Plugin Basename.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_BASENAME' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * SureFeedback API Base URL constant
 *
 * Defaults to production API URL.
 * Can be overridden in wp-config.php for different environments.
 *
 * @since 0.0.1
 */
if ( ! defined( 'SUREFEEDBACK_API_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_API_BASE_URL', 'https://api.surefeedback.com' );
}

/**
 * SureFeedback App Base URL constant
 *
 * Defaults to production app URL.
 * Can be overridden in wp-config.php for different environments.
 *
 * @since 0.0.1
 */
if ( ! defined( 'SUREFEEDBACK_APP_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_APP_BASE_URL', 'https://app.surefeedback.com' );
}

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
add_action(
	'plugins_loaded',
	function () use ( $app ) {
		$app->boot();
	}
);

/**
 * Plugin activation hook
 */
register_activation_hook(
	SUREFEEDBACK_PLUGIN_FILE,
	function () {
		// Set a flag to redirect to setup on first activation
		set_transient( 'surefeedback_activation_redirect', true, 30 * MINUTE_IN_SECONDS );
	}
);

/**
 * Admin init - redirect to setup page after plugin activation
 */
add_action(
	'admin_init',
	function () {
		// Only for admin users
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for activation redirect transient
		if ( get_transient( 'surefeedback_activation_redirect' ) ) {
			delete_transient( 'surefeedback_activation_redirect' );

			// Redirect to get started screen (Welcome page)
			wp_redirect( admin_url( 'admin.php?page=surefeedback-connection#setup' ) );
			exit;
		}
	},
	20
); // Priority 20 to ensure plugins are loaded


/**
 * Plugin deactivation hook
 */
register_deactivation_hook(
	SUREFEEDBACK_PLUGIN_FILE,
	function () {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
		wp_clear_scheduled_hook( 'surefeedback_hourly_verify' );
	}
);

/**
 * Load plugin text domain for internationalization
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain(
			'surefeedback',
			false,
			dirname( plugin_basename( SUREFEEDBACK_PLUGIN_FILE ) ) . '/languages/'
		);
	}
);

/**
 * Add settings link to plugin list table
 */
add_filter(
	'plugin_action_links_' . SUREFEEDBACK_PLUGIN_BASENAME,
	function ( $links ) {
		$dashboard_link = '<a href="' . admin_url( 'admin.php?page=surefeedback-connection' ) . '">' . __( 'Dashboard', 'surefeedback' ) . '</a>';
		$settings_link  = '<a href="' . admin_url( 'admin.php?page=surefeedback-settings' ) . '">' . __( 'Settings', 'surefeedback' ) . '</a>';
		array_unshift( $links, $dashboard_link, $settings_link );
		return $links;
	}
);
