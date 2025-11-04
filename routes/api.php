<?php

/**
 * API Routes
 *
 * Here is where you can register API routes for your plugin. These
 * routes are loaded by the RouteServiceProvider and all of them will
 * be assigned to the "api" middleware group. Make something great!
 *
 * @package SureFeedback
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your plugin. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use SureFeedback\Http\Controllers\Api\ConnectionController;
use SureFeedback\Http\Controllers\Api\SettingsController;
use SureFeedback\Http\Controllers\Api\VerificationController;
use SureFeedback\Http\Controllers\Api\PageSettingsController;

// Connection management endpoints
$router->group(
	array(
		'prefix'    => 'connection',
		'namespace' => 'Api',
	),
	function ( $router ) {
		$router->get( 'status', array( ConnectionController::class, 'status' ) );
		$router->post( 'connect', array( ConnectionController::class, 'connect' ) );
		$router->post( 'reset', array( ConnectionController::class, 'reset' ) );
		$router->get( 'health', array( ConnectionController::class, 'health' ) );
	}
);

$router->group(
	array(
		'prefix'    => 'remote',
		'namespace' => 'Api',
	),
	function ( $router ) {
		$router->getJWT( 'validate-token', array( ConnectionController::class, 'validate_token' ) );
	}
);

// Webhook endpoint for SureFeedback API callbacks
$router->post( 'webhook', array( ConnectionController::class, 'webhook' ) );

// Secure disconnect webhook endpoint (Webhook Secret protected)
$router->post( 'webhook/disconnect', array( ConnectionController::class, 'disconnect_webhook' ) );

// Plugin activation endpoint (for SaaS auto-installation)
$router->post(
	'plugin/activate',
	function () {
		// Check if user is authenticated and has admin capabilities
		if ( ! is_user_logged_in() || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to activate plugins.', 'surefeedback' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		try {
			$plugin_file = SUREFEEDBACK_PLUGIN_BASENAME;

			// Check if plugin is already active
			if ( is_plugin_active( $plugin_file ) ) {
				return rest_ensure_response(
					array(
						'success'        => true,
						'already_active' => true,
						'message'        => __( 'Plugin is already active.', 'surefeedback' ),
						'plugin'         => $plugin_file,
						'status'         => 'active',
					)
				);
			}

			// Activate the plugin
			$result = activate_plugin( $plugin_file, '', false, true );

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'activation_failed',
					$result->get_error_message(),
					array( 'status' => 500 )
				);
			}

			// Run activation hook manually if needed
			do_action( 'activate_' . $plugin_file );

			return rest_ensure_response(
				array(
					'success'      => true,
					'message'      => __( 'Plugin activated successfully.', 'surefeedback' ),
					'plugin'       => $plugin_file,
					'status'       => 'active',
					'activated_at' => current_time( 'mysql' ),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'activation_exception',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
);

// Plugin status endpoint
$router->get(
	'plugin/status',
	function () {
		$plugin_file = SUREFEEDBACK_PLUGIN_BASENAME;

		return rest_ensure_response(
			array(
				'plugin'    => $plugin_file,
				'is_active' => is_plugin_active( $plugin_file ),
				'version'   => SUREFEEDBACK_VERSION,
				'name'      => 'SureFeedback Client',
				'status'    => is_plugin_active( $plugin_file ) ? 'active' : 'inactive',
			)
		);
	}
);

// Verification endpoints
$router->group(
	array( 'prefix' => 'verification' ),
	function ( $router ) {
		$router->post( 'verify', array( VerificationController::class, 'verify_connection' ) );
	}
);

// Settings management endpoints
$router->group(
	array(
		'prefix'    => 'settings',
		'namespace' => 'Api',
	),
	function ( $router ) {
		$router->get( '/', array( SettingsController::class, 'index' ) );
		$router->post( '/', array( SettingsController::class, 'update' ) );
		$router->get( 'general', array( SettingsController::class, 'general' ) );
		$router->post( 'general', array( SettingsController::class, 'updateGeneral' ) );
	}
);

// Page settings endpoints
$router->group(
	array( 'prefix' => 'page-settings' ),
	function ( $router ) {
		$router->get( '/', array( PageSettingsController::class, 'index' ) );
		$router->post( '/', array( PageSettingsController::class, 'update' ) );
		$router->post( 'enable', array( PageSettingsController::class, 'enablePage' ) );
		$router->post( 'disable', array( PageSettingsController::class, 'disablePage' ) );
		$router->post( 'enable-all', array( PageSettingsController::class, 'enableAll' ) );
		$router->post( 'disable-all', array( PageSettingsController::class, 'disableAll' ) );
	}
);

// Legacy compatibility routes
$router->get(
	'pages',
	function () {
		// Legacy route for backward compatibility
		return rest_ensure_response(
			array(
				'pages' => get_pages(
					array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'numberposts' => -1,
					)
				),
			)
		);
	}
);

// Health check endpoint
$router->get(
	'health',
	function () {
		return rest_ensure_response(
			array(
				'status'    => 'ok',
				'version'   => SUREFEEDBACK_VERSION,
				'wordpress' => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'timestamp' => current_time( 'timestamp' ),
			)
		);
	}
);
