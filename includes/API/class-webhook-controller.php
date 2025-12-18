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
	}

	/**
	 * Register routes
	 *
	 * @since 0.0.1
	 */
	public function register_routes() {
		/*
		 * Connection webhook - called by our SaaS platform during OAuth flow.
		 *
		 * Note: Using __return_true here because this isn't called by WP users - it's an external webhook.
		 * Security works like OAuth: the admin generates a random state token, user gives it to our SaaS,
		 * then SaaS sends it back here. We validate the state inside handle_webhook() - it's time-limited
		 * (60 mins), single-use, and checked with hash_equals() to prevent timing attacks.
		 */
		register_rest_route(
			$this->namespace,
			'/webhook',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		/*
		 * Disconnect webhook - lets our SaaS platform remotely disconnect a site.
		 *
		 * Note: Using __return_true because external webhook, not WP user action.
		 * Auth is done inside handle_disconnect_webhook() via X-Webhook-Secret header.
		 */
		register_rest_route(
			$this->namespace,
			'/webhook/disconnect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_disconnect_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/webhook/sync',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_sync_webhook' ),
					'permission_callback' => array( $this, 'verify_bearer_token' ),
				),
			)
		);
	}

	/**
	 * Handle webhook from Laravel for automatic connection
	 *
	 * OAuth-style flow: validates the state token before connecting.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function handle_webhook( $request ) {

		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			return new WP_Error(
				'rest_invalid_data',
				__( 'Invalid webhook data.', 'surefeedback-cloud' ),
				array( 'status' => 400 )
			);
		}

		// Get state from body or header
		$state        = $body['state'] ?? '';
		$state_header = $request->get_header( 'X-SureFeedback-State' );

		$provided_state = ! empty( $state ) ? $state : $state_header;

		if ( empty( $provided_state ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Missing state parameter.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Check if we have a stored state to compare against
		$stored_state_data = get_option( 'surefeedback_webhook_state', false );

		if ( ! $stored_state_data || ! is_array( $stored_state_data ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid state: no stored state found.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		$stored_state = $stored_state_data['state'] ?? '';
		$state_expiry = $stored_state_data['expiry'] ?? 0;

		// States expire after 60 minutes
		if ( time() > $state_expiry ) {
			delete_option( 'surefeedback_webhook_state' );
			return new WP_Error(
				'rest_unauthorized',
				__( 'State has expired.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Compare states using hash_equals to prevent timing attacks
		if ( ! hash_equals( $stored_state, $provided_state ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid state: state mismatch.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// All good! State is valid, let's connect
		$success = isset( $body['success'] ) && ( '1' === $body['success'] || true === $body['success'] || 1 === $body['success'] );

		if ( ! $success ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Webhook indicates failure',
				)
			);
		}

		$auth_manager = new \SureFeedback\Auth_Manager();

		if ( ! empty( $body['user_token'] ) ) {
			$auth_manager->store_bearer_token( sanitize_text_field( $body['user_token'] ) );
		}

		$options_stored = array();

		if ( ! empty( $body['site_id'] ) ) {
			$options_stored[] = 'site_id';
			update_option( 'surefeedback_site_id', sanitize_text_field( $body['site_id'] ) );
		}

		if ( ! empty( $body['organization_id'] ) ) {
			$options_stored[] = 'organization_id';
			update_option( 'surefeedback_organization_id', sanitize_text_field( $body['organization_id'] ) );
		}

		if ( ! empty( $body['script_token'] ) ) {
			$options_stored[] = 'access_token';
			$options_stored[] = 'site_token';
			update_option( 'surefeedback_access_token', sanitize_text_field( $body['script_token'] ) );
			update_option( 'surefeedback_site_token', sanitize_text_field( $body['script_token'] ) );
		} elseif ( ! empty( $body['site_token'] ) ) {
			$options_stored[] = 'access_token';
			$options_stored[] = 'site_token';
			update_option( 'surefeedback_access_token', sanitize_text_field( $body['site_token'] ) );
			update_option( 'surefeedback_site_token', sanitize_text_field( $body['site_token'] ) );
		}

		if ( ! empty( $body['parent_url'] ) ) {
			$options_stored[] = 'parent_url';
			update_option( 'surefeedback_parent_url', esc_url_raw( $body['parent_url'] ) );
		}

		if ( ! empty( $body['widget_script_url'] ) ) {
			$options_stored[] = 'widget_script_url';
			update_option( 'surefeedback_widget_script_url', esc_url_raw( $body['widget_script_url'] ) );
		}

		if ( ! empty( $body['connection_id'] ) ) {
			$options_stored[] = 'connection_id';
			update_option( 'surefeedback_connection_id', sanitize_text_field( $body['connection_id'] ) );
		}

		if ( isset( $body['is_fully_verified'] ) ) {
			$options_stored[] = 'is_fully_verified';
			update_option( 'surefeedback_is_fully_verified', intval( $body['is_fully_verified'] ) );
		}

		if ( ! empty( $body['surefeedback_last_verification'] ) ) {
			$options_stored[] = 'surefeedback_last_verification';
			update_option( 'surefeedback_last_verification', sanitize_text_field( $body['surefeedback_last_verification'] ) );
		}

		// Clean up the state - it's single-use only
		delete_option( 'surefeedback_webhook_state' );

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Connection established via webhook',
				'connected' => true,
			)
		);
	}

	/**
	 * Handle disconnect webhook from Laravel
	 *
	 * Validates the webhook secret before disconnecting.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function handle_disconnect_webhook( $request ) {
		$body = $request->get_json_params();

		$webhook_secret    = $request->get_header( 'X-Webhook-Secret' );
		$stored_site_token = get_option( 'surefeedback_site_token', '' );

		// Must have the secret header
		if ( empty( $webhook_secret ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Missing webhook secret header.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Must have a token configured
		if ( empty( $stored_site_token ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'No site token configured.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Validate secret using hash_equals (prevents timing attacks)
		if ( ! hash_equals( $stored_site_token, $webhook_secret ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid webhook secret.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Double-check site_id if they sent one
		if ( ! empty( $body['site_id'] ) ) {
			$stored_site_id = get_option( 'surefeedback_site_id', '' );
			if ( ! empty( $stored_site_id ) && $body['site_id'] !== $stored_site_id ) {
				return new WP_Error(
					'rest_unauthorized',
					__( 'Invalid site ID.', 'surefeedback-cloud' ),
					array( 'status' => 401 )
				);
			}
		}

		// Secret checks out, proceed with disconnect
		$secure_cookie_manager = \SureFeedback\Secure_Cookie_Manager::get_instance();
		$secure_cookie_manager->delete_secure_cookie( 'auth_token' );

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

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Disconnected successfully via webhook',
				'connected' => false,
			)
		);
	}

	/**
	 * Handle sync webhook from Laravel
	 * Triggered when the SaaS platform wants to sync data with WordPress
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function handle_sync_webhook( $request ) {
		// Update last sync timestamp.
		update_option( 'surefeedback_last_sync', current_time( 'mysql' ) );

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Sync webhook received successfully',
				'synced_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Verify bearer token for sync webhook
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_bearer_token( $request ) {
		$auth_header = $request->get_header( 'authorization' );

		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Missing authorization header.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		// Extract bearer token from "Bearer {token}" format.
		if ( ! preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid authorization header format.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		$provided_token = trim( $matches[1] );
		$stored_token   = get_option( 'surefeedback_access_token', '' );

		if ( empty( $stored_token ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'No access token configured.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		if ( ! hash_equals( $stored_token, $provided_token ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid bearer token.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}
}
