<?php

/**
 * Webhook Controller class
 *
 * Handles webhook requests from Laravel for automatic connection and disconnection.
 *
 * @package SureFeedback
 */

namespace SureFeedback\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook Controller class
 *
 * Provides webhook endpoints for:
 * - Automatic connection via webhook
 * - Automatic disconnection via webhook
 */
class WebhookController extends WP_REST_Controller {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'surefeedback/v1';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		// No initialization needed
	}

	/**
	 * Register routes
	 *
	 * @since 0.0.1
	 */
	public function register_routes() {
		// Webhook endpoint for automatic connection (from Laravel)
		register_rest_route(
			$this->namespace,
			'/webhook',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true', // Public endpoint, authenticated via state
				),
			)
		);

		// Webhook endpoint for disconnect (from Laravel)
		register_rest_route(
			$this->namespace,
			'/webhook/disconnect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_disconnect_webhook' ),
					'permission_callback' => '__return_true', // Public endpoint, authenticated via secret
				),
			)
		);
	}

	/**
	 * Handle webhook from Laravel for automatic connection
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function handle_webhook( $request ) {
		error_log( 'SureFeedback: Webhook received from Laravel' );

		// Get JSON body
		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			error_log( 'SureFeedback: Webhook body is empty' );
			return new WP_Error(
				'rest_invalid_data',
				__( 'Invalid webhook data.', 'surefeedback-cloud' ),
				array( 'status' => 400 )
			);
		}

		// Verify state authentication
		$state = $body['state'] ?? '';
		$state_header = $request->get_header( 'X-SureFeedback-State' );

		// Check state from both body and header
		$provided_state = ! empty( $state ) ? $state : $state_header;

		if ( empty( $provided_state ) ) {
			error_log( 'SureFeedback: Webhook missing state parameter' );
			return new WP_Error(
				'rest_unauthorized',
				__( 'Missing state parameter.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Verify state matches stored state
		$stored_state_data = get_option( 'surefeedback_webhook_state', false );

		if ( ! $stored_state_data || ! is_array( $stored_state_data ) ) {
			error_log( 'SureFeedback: No stored webhook state found' );
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid state: no stored state found.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		$stored_state = $stored_state_data['state'] ?? '';
		$state_expiry = $stored_state_data['expiry'] ?? 0;

		// Check if state has expired
		if ( time() > $state_expiry ) {
			error_log( 'SureFeedback: Webhook state has expired' );
			// Clean up expired state
			delete_option( 'surefeedback_webhook_state' );
			return new WP_Error(
				'rest_unauthorized',
				__( 'State has expired.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Verify state matches
		if ( $provided_state !== $stored_state ) {
			error_log( 'SureFeedback: Webhook state mismatch - provided: ' . substr( $provided_state, 0, 8 ) . '..., stored: ' . substr( $stored_state, 0, 8 ) . '...' );
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid state: state mismatch.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		error_log( 'SureFeedback: Webhook state verified successfully' );

		// Check if webhook indicates success
		$success = isset( $body['success'] ) && ( $body['success'] === '1' || $body['success'] === true || $body['success'] === 1 );

		if ( ! $success ) {
			error_log( 'SureFeedback: Webhook indicates failure' );
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Webhook indicates failure',
				)
			);
		}

		// Store connection data (same as OAuth flow in Auth_Manager::exchange_token)
		$auth_manager = new \SureFeedback\Auth_Manager();

		// Store bearer token (user_token from webhook)
		if ( ! empty( $body['user_token'] ) ) {
			$token_stored = $auth_manager->store_bearer_token( sanitize_text_field( $body['user_token'] ) );
			if ( $token_stored ) {
				error_log( 'SureFeedback: Bearer token stored from webhook' );
			} else {
				error_log( 'SureFeedback: Failed to store bearer token from webhook' );
			}
		}

		// Store connection metadata
		$options_stored = array();

		if ( ! empty( $body['site_id'] ) ) {
			$options_stored[] = 'site_id';
			update_option( 'surefeedback_site_id', sanitize_text_field( $body['site_id'] ) );
		}

		if ( ! empty( $body['organization_id'] ) ) {
			$options_stored[] = 'organization_id';
			update_option( 'surefeedback_organization_id', sanitize_text_field( $body['organization_id'] ) );
		}

		// Store script token (site token) for verification
		if ( ! empty( $body['script_token'] ) ) {
			$options_stored[] = 'access_token';
			$options_stored[] = 'site_token';
			update_option( 'surefeedback_access_token', sanitize_text_field( $body['script_token'] ) );
			update_option( 'surefeedback_site_token', sanitize_text_field( $body['script_token'] ) );
		} elseif ( ! empty( $body['site_token'] ) ) {
			// Fallback to site_token if script_token not provided
			$options_stored[] = 'access_token';
			$options_stored[] = 'site_token';
			update_option( 'surefeedback_access_token', sanitize_text_field( $body['site_token'] ) );
			update_option( 'surefeedback_site_token', sanitize_text_field( $body['site_token'] ) );
		}

		// Store additional metadata
		if ( ! empty( $body['parent_url'] ) ) {
			$options_stored[] = 'parent_url';
			update_option( 'surefeedback_parent_url', esc_url_raw( $body['parent_url'] ) );
		}

		if ( ! empty( $body['widget_script_url'] ) ) {
			$options_stored[] = 'widget_script_url';
			update_option( 'surefeedback_widget_script_url', esc_url_raw( $body['widget_script_url'] ) );
		}

		// Store connection ID if provided
		if ( ! empty( $body['connection_id'] ) ) {
			$options_stored[] = 'connection_id';
			update_option( 'surefeedback_connection_id', sanitize_text_field( $body['connection_id'] ) );
		}

		// Store verification status
		if ( isset( $body['is_fully_verified'] ) ) {
			$options_stored[] = 'is_fully_verified';
			update_option( 'surefeedback_is_fully_verified', intval( $body['is_fully_verified'] ) );
		}

		if ( ! empty( $body['surefeedback_last_verification'] ) ) {
			$options_stored[] = 'surefeedback_last_verification';
			update_option( 'surefeedback_last_verification', sanitize_text_field( $body['surefeedback_last_verification'] ) );
		}

		error_log( 'SureFeedback: Webhook processed successfully - stored options: ' . implode( ', ', $options_stored ) );

		// Clean up used state
		delete_option( 'surefeedback_webhook_state' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Connection established via webhook',
				'connected' => true,
			)
		);
	}

	/**
	 * Handle disconnect webhook from Laravel
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function handle_disconnect_webhook( $request ) {
		error_log( 'SureFeedback: Disconnect webhook received from Laravel' );

		// Get JSON body
		$body = $request->get_json_params();

		// Verify webhook secret (site token)
		$webhook_secret = $request->get_header( 'X-Webhook-Secret' );
		$stored_site_token = get_option( 'surefeedback_site_token', '' );

		if ( ! empty( $webhook_secret ) && ! empty( $stored_site_token ) ) {
			if ( $webhook_secret !== $stored_site_token ) {
				error_log( 'SureFeedback: Disconnect webhook secret mismatch' );
				return new WP_Error(
					'rest_unauthorized',
					__( 'Invalid webhook secret.', 'surefeedback-cloud' ),
					array( 'status' => 401 )
				);
			}
		}

		// Verify site_id matches if provided
		if ( ! empty( $body['site_id'] ) ) {
			$stored_site_id = get_option( 'surefeedback_site_id', '' );
			if ( ! empty( $stored_site_id ) && $body['site_id'] !== $stored_site_id ) {
				error_log( 'SureFeedback: Disconnect webhook site_id mismatch' );
				return new WP_Error(
					'rest_unauthorized',
					__( 'Invalid site ID.', 'surefeedback-cloud' ),
					array( 'status' => 401 )
				);
			}
		}

		error_log( 'SureFeedback: Disconnect webhook verified, clearing connection data' );

		// Clear bearer token
		$secure_cookie_manager = \SureFeedback\Secure_Cookie_Manager::get_instance();
		$secure_cookie_manager->delete_secure_cookie( 'auth_token' );

		// Clear all database options
		delete_option( 'surefeedback_bearer_token' );
		delete_option( 'surefeedback_connection_id' );
		delete_option( 'surefeedback_site_id' );
		delete_option( 'surefeedback_organization_id' );
		delete_option( 'surefeedback_access_token' );
		delete_option( 'surefeedback_site_token' );
		delete_option( 'surefeedback_parent_url' );
		delete_option( 'surefeedback_widget_script_url' );
		delete_option( 'surefeedback_is_fully_verified' );
		delete_option( 'surefeedback_last_verification' );
		delete_option( 'surefeedback_webhook_state' );

		error_log( 'SureFeedback: Connection data cleared via disconnect webhook' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Disconnected successfully via webhook',
				'connected' => false,
			)
		);
	}
}
