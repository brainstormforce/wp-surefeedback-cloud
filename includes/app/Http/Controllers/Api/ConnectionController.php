<?php

namespace SureFeedback\Http\Controllers\Api;

defined( 'ABSPATH' ) || exit;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Http\Requests\ConnectionRequest;
use SureFeedback\Http\Requests\VerifyConnectionRequest;
use SureFeedback\Services\JWTService;
use SureFeedback\Constants\VerificationStatus;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Connection Controller
 *
 * Handles all connection-related API endpoints including
 * status checks, verification, and connection management.
 *
 * @package SureFeedback\App\Http\Controllers\Api
 * @author Anurag
 */
class ConnectionController extends Controller {

	/**
	 * JWT Service
	 *
	 * @var JWTService
	 */
	protected $jwtService;

	/**
	 * Connection Repository
	 *
	 * @var \SureFeedback\Repositories\ConnectionRepository
	 */
	protected $connection_repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->jwtService            = new JWTService();
		$this->connection_repository = new \SureFeedback\Repositories\ConnectionRepository();
	}

	/**
	 * Get connection status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function status( WP_REST_Request $request ) {
		try {
			$nonce_result = $this->validateNonce( $request );
			if ( is_wp_error( $nonce_result ) ) {
				return $nonce_result;
			}

			$connection_data = array(
				'connected'                 => $this->isConnected(),
				'parent_url'                => $this->connection_repository->getParentUrl() ?: '',
				'access_token'              => ! empty( $this->connection_repository->getAccessToken() ),
				'last_check'                => $this->connection_repository->getLastVerification() ?: '',
				'is_fully_verified'         => $this->connection_repository->getIsFullyVerified( VerificationStatus::PENDING ),
				'verification_status_label' => VerificationStatus::getLabel( $this->connection_repository->getIsFullyVerified( VerificationStatus::PENDING ) ),
				'site_connected'            => $this->connection_repository->getSiteConnected(),
				'status'                    => $this->isConnected() ? 'connected' : 'disconnected',
			);

			return $this->success( $connection_data );

		} catch ( \Exception $e ) {
			return $this->error( 'Failed to get connection status', 500 );
		}
	}

	/**
	 * Establish connection
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function connect( WP_REST_Request $request ) {
		try {
			$nonce_result = $this->validateNonce( $request );
			if ( is_wp_error( $nonce_result ) ) {
				return $nonce_result;
			}

			$capability_result = $this->validateCapability( 'manage_options' );
			if ( is_wp_error( $capability_result ) ) {
				return $capability_result;
			}

			$validation = $this->validate(
				$request,
				array(
					'parent_url' => 'required|url',
					'site_token' => 'required|string',
					'signature'  => 'required|string',
				)
			);

			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$parent_url = sanitize_url( $request->get_param( 'parent_url' ) );
			$site_token = sanitize_text_field( $request->get_param( 'site_token' ) );
			$signature  = sanitize_text_field( $request->get_param( 'signature' ) );

			// Verify signature
			if ( ! $this->verifySignature( $site_token, $signature ) ) {
				return $this->error( 'Invalid signature', 403 );
			}

			// Generate access token
			$access_token = wp_generate_password( 32, false );

			// Save connection data
			$this->connection_repository->setParentUrl( $parent_url );
			$this->connection_repository->setAccessToken( $access_token );
			$this->connection_repository->setConnectionStatus( 'connected' );

			return $this->success(
				array(
					'message'      => 'Connection established successfully',
					'access_token' => $access_token,
					'connected'    => true,
					'connected_at' => current_time( 'mysql' ),
				)
			);

		} catch ( \Exception $e ) {
			return $this->error( 'Failed to establish connection', 500 );
		}
	}

	/**
	 * Check if site is connected
	 *
	 * @return bool
	 */
	private function isConnected(): bool {
		return $this->connection_repository->isConnected();
	}

	/**
	 * Get or generate connection signature secret
	 *
	 * @return string
	 */
	private function getConnectionSignatureSecret(): string {
		$secret = get_option( 'surefeedback_connection_signature_secret' );
		if ( ! $secret ) {
			// Generate dedicated secret for connection signatures
			$secret = wp_generate_password( 64, false );
			update_option( 'surefeedback_connection_signature_secret', $secret );
		}
		return $secret;
	}

	/**
	 * Verify HMAC signature for connection requests (using dedicated secret)
	 *
	 * @param string $site_token
	 * @param string $signature
	 * @return bool
	 */
	private function verifySignature( string $site_token, string $signature ): bool {
		$secret             = $this->getConnectionSignatureSecret();
		$expected_signature = hash_hmac( 'sha256', $site_token, $secret );
		return hash_equals( $expected_signature, $signature );
	}   /**
		 * Reset site connection completely
		 *
		 * @param WP_REST_Request $request
		 * @return WP_REST_Response|WP_Error
		 */
	public function reset( WP_REST_Request $request ) {
		try {
			$nonce_result = $this->validateNonce( $request );
			if ( is_wp_error( $nonce_result ) ) {
				return $nonce_result;
			}

			$capability_result = $this->validateCapability( 'manage_options' );
			if ( is_wp_error( $capability_result ) ) {
				return $capability_result;
			}

			// Get connection data before deleting
			$site_id    = $this->connection_repository->getSiteId();
			$site_token = $this->connection_repository->getAccessToken();
			$domain     = $this->connection_repository->getDomain();

			// Get stored JWT token for API authentication (if exists)
			$jwt_token = $this->connection_repository->getUserToken();

			// Notify Laravel API to disconnect site on their side
			if ( ! empty( $site_id ) && ! empty( $jwt_token ) ) {
				$api_gateway       = new \SureFeedback\Services\ApiGatewayService();
				$disconnect_result = $api_gateway->disconnectSite( $site_id, $site_token, $domain, $jwt_token );
			}

			// Delete all SureFeedback connection data
			$this->connection_repository->clearAllConnectionData();

			// Clear settings repository data
			$settings_repository = new \SureFeedback\Repositories\SettingsRepository();
			$settings_repository->clearCache();

			wp_cache_delete( 'surefeedback_settings' );
			delete_transient( 'surefeedback_connection_check' );

			return $this->success(
				array(
					'message'   => 'Site connection reset successfully',
					'connected' => false,
					'reset_at'  => current_time( 'mysql' ),
					'status'    => 'reset',
				)
			);

		} catch ( \Exception $e ) {
			return $this->error( 'Failed to reset site connection', 500 );
		}
	}

	/**
	 * Handle webhook from SureFeedback API with robust security verification
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		// Security: Rate limiting for webhook endpoint
		$security_service = new \SureFeedback\Services\SecurityService();
		$client_ip        = $security_service->getClientIp();

		if ( ! $security_service->checkRateLimit( 'webhook_' . $client_ip, 10, 300 ) ) {
			return $this->error( 'Rate limit exceeded. Try again later.', 429 );
		}

		// Get webhook data
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		// Perform webhook authentication - handles both initial setup and regular webhooks
		$auth_result = $this->authenticateWebhook( $request, $data, $security_service );
		
		if ( is_wp_error( $auth_result ) ) {
			return $auth_result;
		}

		try {
			// Process webhook data
			return $this->processWebhookData( $data );

		} catch ( \Exception $e ) {
			return $this->error( 
				array(
					'message'      => 'Failed to process webhook',
					'success'      => false,
					'connected'    => false,
					'error'        => $e->getMessage(),
					'processed_at' => current_time( 'mysql' ),
				), 
				500 
			);
		}
	}

	/**
	 * Process webhook data and update connection
	 *
	 * @param array $data Webhook data
	 * @return WP_REST_Response|WP_Error
	 */
	private function processWebhookData( array $data ) {
		// Extract site data
		$siteData = isset( $data['data'] ) ? $data['data'] : $data;
		$siteId   = $siteData['id'] ?? $data['site_id'] ?? null;
		$apiToken = $siteData['api_token'] ?? $data['site_token'] ?? null;

		// Validate required fields
		if ( empty( $data['success'] ) || empty( $apiToken ) || empty( $siteId ) ) {
			return $this->error( 
				array(
					'message'        => 'Missing required webhook data',
					'success'        => false,
					'connected'      => false,
					'missing_fields' => array(
						'success'   => empty( $data['success'] ),
						'api_token' => empty( $apiToken ),
						'site_id'   => empty( $siteId ),
					),
				), 
				400 
			);
		}

		// Check if connection was successful
		if ( $data['success'] === '1' || $data['success'] === 1 || $data['success'] === true ) {
			// Update connection data
			$this->updateConnectionFromWebhook( $siteData, $data, $siteId, $apiToken );
			
			// Clear caches
			wp_cache_delete( 'surefeedback_settings' );
			delete_transient( 'surefeedback_connection_check' );

			return $this->success(
				array(
					'success'           => true,
					'message'           => 'Site connected successfully via webhook',
					'connected'         => true,
					'site_connected'    => true,
					'site_id'           => $siteId,
					'connection_status' => 'connected',
					'processed_at'      => current_time( 'mysql' ),
					'plugin_status'     => 'active',
				),
				200
			);
		}
		
		return $this->error( 
			array(
				'message'        => 'Webhook indicated connection failure',
				'success'        => false,
				'connected'      => false,
				'processed_at'   => current_time( 'mysql' ),
			), 
			400 
		);
	}

	/**
	 * Update connection data from webhook
	 *
	 * @param array $siteData Site data from webhook
	 * @param array $data Full webhook data
	 * @param string $siteId Site ID
	 * @param string $apiToken API token
	 * @return void
	 */
	private function updateConnectionFromWebhook( array $siteData, array $data, string $siteId, string $apiToken ): void {
		// Core connection data
		$this->connection_repository->setSiteId( sanitize_text_field( $siteId ) );
		$this->connection_repository->setAccessToken( sanitize_text_field( $apiToken ) );
		$this->connection_repository->setConnectionStatus( 'connected' );

		// Optional site data
		if ( ! empty( $siteData['site_name'] ) ) {
			$this->connection_repository->setSiteName( sanitize_text_field( $siteData['site_name'] ) );
		}

		if ( ! empty( $siteData['domain'] ) ) {
			$this->connection_repository->setDomain( esc_url_raw( $siteData['domain'] ) );
		}

		if ( ! empty( $siteData['organization_id'] ) ) {
			$this->connection_repository->setOrganizationId( sanitize_text_field( $siteData['organization_id'] ) );
		}

		if ( isset( $siteData['is_active'] ) ) {
			$this->connection_repository->setIsActive( (bool) $siteData['is_active'] );
		}

		if ( ! empty( $siteData['created_at'] ) ) {
			$this->connection_repository->setCreatedAt( sanitize_text_field( $siteData['created_at'] ) );
		}

		if ( ! empty( $data['parent_url'] ) ) {
			$this->connection_repository->setParentUrl( esc_url_raw( $data['parent_url'] ) );
		}

		// JWT token for API authentication
		if ( ! empty( $data['user_token'] ) ) {
			$this->connection_repository->setUserToken( sanitize_text_field( $data['user_token'] ) );
		}

		// Site connection status
		$site_connected_value = isset( $data['site_connected'] ) ? (bool) $data['site_connected'] : true;
		$this->connection_repository->setSiteConnected( $site_connected_value );

		// Verification status
		$last_verification_value = $data['surefeedback_last_verification'] ?? '';
		$is_fully_verified_value = isset( $data['is_fully_verified'] ) 
			? (int) $data['is_fully_verified'] 
			: VerificationStatus::PENDING;

		if ( ! empty( $last_verification_value ) ) {
			$this->connection_repository->setLastVerification( $last_verification_value );
		}
		$this->connection_repository->setIsFullyVerified( $is_fully_verified_value );
	}

	/**
	 * Store state for webhook verification (REST API endpoint)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function store_state( WP_REST_Request $request ) {
		try {
			$nonce_result = $this->validateNonce( $request );
			if ( is_wp_error( $nonce_result ) ) {
				return $nonce_result;
			}

			$capability_result = $this->validateCapability( 'manage_options' );
			if ( is_wp_error( $capability_result ) ) {
				return $capability_result;
			}

			$validation = $this->validate(
				$request,
				array(
					'state' => 'required|string',
				)
			);

			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$state = sanitize_text_field( $request->get_param( 'state' ) );

			$success = $this->storeWebhookState( $state );

			if ( $success ) {
				return $this->success(
					array(
						'message' => 'State stored successfully',
						'state'   => $state,
					)
				);
			} else {
				return $this->error( 'Failed to store state', 500 );
			}
		} catch ( \Exception $e ) {
			return $this->error( 'Failed to store state', 500 );
		}
	}

	/**
	 * JWT token validation endpoint
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_token( WP_REST_Request $request ) {
		$token_data = $request->get_param( '_jwt_token_data' );

		if ( ! $token_data ) {
			return $this->error( 'Token is invalid', 401 );
		}

		$has_admin_permission = $this->jwtService->check_permission( $token_data, 'manage_options' );

		return $this->success(
			array(
				'message'          => 'Token is valid',
				'token_info'       => array(
					'user_id'    => $token_data['user_id'] ?? null,
					'email'      => $token_data['email'] ?? null,
					'role'       => $token_data['role'] ?? null,
					'issued_at'  => isset( $token_data['iat'] ) ? gmdate( 'Y-m-d H:i:s', $token_data['iat'] ) : null,
					'expires_at' => isset( $token_data['exp'] ) ? gmdate( 'Y-m-d H:i:s', $token_data['exp'] ) : null,
				),
				'permission_check' => array(
					'has_admin_permission' => $has_admin_permission,
					'required_permission'  => 'manage_options',
				),
				'validated_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Handle disconnect webhook from SureFeedback API
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect_webhook( WP_REST_Request $request ) {
		// Security: Rate limiting for disconnect webhook endpoint
		$security_service = new \SureFeedback\Services\SecurityService();
		$client_ip        = $security_service->getClientIp();

		if ( ! $security_service->checkRateLimit( 'disconnect_webhook_' . $client_ip, 5, 600 ) ) {
			return $this->error( 'Rate limit exceeded. Try again later.', 429 );
		}

		try {
			// Verify webhook secret key from X-Webhook-Secret header
			$webhook_secret        = $request->get_header( 'X-Webhook-Secret' );
			$stored_webhook_secret = $this->getDisconnectWebhookSecret();

			// Validate webhook secret matches the dedicated disconnect secret
			if ( empty( $webhook_secret ) || empty( $stored_webhook_secret ) ) {
				return $this->error( 'Invalid or missing webhook secret', 401 );
			}

			if ( ! hash_equals( $stored_webhook_secret, $webhook_secret ) ) {
				return $this->error( 'Webhook secret validation failed', 401 );
			}

			// Get disconnect data from request
			$data = $request->get_json_params();
			if ( empty( $data ) ) {
				$data = $request->get_params();
			}

			$site_id = $data['site_id'] ?? null;
			$force   = $data['force'] ?? false;

			// Delete all SureFeedback options
			$surefeedback_options = array(
				'surefeedback_access_token',
				'surefeedback_parent_url',
				'surefeedback_site_id',
				'surefeedback_last_verification',
				'surefeedback_site_name',
				'surefeedback_domain',
				'surefeedback_organization_id',
				'surefeedback_is_active',
				'surefeedback_created_at',
				'surefeedback_site_connected',
				'surefeedback_is_fully_verified',
				'surefeedback_user_token',
				'surefeedback_roles',
			);

			foreach ( $surefeedback_options as $option ) {
				delete_option( $option );
			}

			// Clear caches
			wp_cache_delete( 'surefeedback_settings' );
			delete_transient( 'surefeedback_connection_check' );

			return $this->success(
				array(
					'message'         => 'Site disconnected successfully',
					'disconnected'    => true,
					'site_id'         => $site_id,
					'disconnected_at' => current_time( 'mysql' ),
					'status'          => 'disconnected',
				)
			);

		} catch ( \Exception $e ) {
			return $this->error( 'Failed to process disconnect webhook', 500 );
		}
	}

	/**
	 * Generate webhook secret for signature verification
	 *
	 * @return string
	 */
	private function getWebhookSecret(): string {
		$secret = get_option( 'surefeedback_webhook_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, false );
			update_option( 'surefeedback_webhook_secret', $secret );
		}
		return $secret;
	}

	/**
	 * Get or generate disconnect webhook secret (separate from access token)
	 *
	 * @return string
	 */
	private function getDisconnectWebhookSecret(): string {
		$secret = get_option( 'surefeedback_disconnect_webhook_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, false );
			update_option( 'surefeedback_disconnect_webhook_secret', $secret );
		}
		return $secret;
	}





	/**
	 * Get webhook security information (internal method for backend use only)
	 *
	 * @return array
	 */
	private function getWebhookSecurityInfo(): array {
		try {
			$stored_state = get_option( 'surefeedback_webhook_state' );

			$has_state = ! empty( $stored_state ) && is_array( $stored_state );
			$state_valid = $has_state && time() <= $stored_state['expiry'];

			return array(
				'has_stored_state'     => $has_state,
				'state_valid'          => $state_valid,
				'connection_status'    => $this->isConnected() ? 'connected' : 'disconnected',
				'security_method'      => 'state_verification',
				'security_level'       => $state_valid ? 'active' : 'none',
				'security_description' => $state_valid ? 'State verification active' : 'No active security state',
			);

		} catch ( \Exception $e ) {
			return array(
				'security_level' => 'none',
				'error'          => 'Failed to get webhook security info',
			);
		}
	}



	/**
	 * Authenticate webhook request with state-based authentication
	 *
	 * @param WP_REST_Request $request
	 * @param array $data
	 * @param \SureFeedback\Services\SecurityService $security_service
	 * @return WP_Error|bool Returns WP_Error on failure, true on success
	 */
	private function authenticateWebhook( WP_REST_Request $request, array $data, $security_service ) {
		$webhook_state = $request->get_header( 'X-SureFeedback-State' );
		$state_from_data = $data['state'] ?? null;
		
		$state = ! empty( $webhook_state ) ? $webhook_state : $state_from_data;
		
		if ( empty( $state ) ) {
			return $this->error( 
				array(
					'message' => 'Missing webhook state',
					'success' => false,
					'connected' => false,
				), 
				400 
			);
		}
		
		if ( ! $this->verifyWebhookState( $state ) ) {
			return $this->error( 
				array(
					'message' => 'Invalid webhook state',
					'success' => false,
					'connected' => false,
				), 
				401 
			);
		}
		
		return true;
	}

	/**
	 * Store webhook state for verification
	 *
	 * @param string $state The state to store
	 * @return bool Success status
	 */
	private function storeWebhookState( string $state ): bool {
		// Store state with expiration (30 minutes for more flexibility)
		$expiry = time() + ( 30 * MINUTE_IN_SECONDS );
		return update_option(
			'surefeedback_webhook_state',
			array(
				'state'  => $state,
				'expiry' => $expiry,
			)
		);
	}

	/**
	 * Verify webhook state parameter
	 *
	 * @param string $received_state The state received in webhook
	 * @return bool Verification status
	 */
	private function verifyWebhookState( string $received_state ): bool {
		$stored_data = get_option( 'surefeedback_webhook_state' );

		// For initial setup, if no state is stored yet, accept any non-empty state and store it
		if ( ! $stored_data || ! is_array( $stored_data ) ) {
			if ( ! empty( $received_state ) ) {
				// Store the state for future verification
				$this->storeWebhookState( $received_state );
				return true;
			}
			return false;
		}

		// Check if state has expired (be more lenient - 30 minutes instead of 15)
		if ( time() > $stored_data['expiry'] ) {
			delete_option( 'surefeedback_webhook_state' ); // Clean up expired state
			// For expired state, store the new one if it's valid
			if ( ! empty( $received_state ) ) {
				$this->storeWebhookState( $received_state );
				return true;
			}
			return false;
		}

		// Verify state matches
		$stored_state = $stored_data['state'];
		if ( ! hash_equals( $stored_state, $received_state ) ) {
			return false;
		}

		// State verification successful - don't remove it yet to allow multiple webhook attempts
		return true;
	}

	/**
	 * Get client IP address (secure implementation)
	 *
	 * @param WP_REST_Request $request
	 * @return string
	 */
	private function getClientIp( WP_REST_Request $request ): string {
		// Use only REMOTE_ADDR for security - no proxy headers to prevent IP spoofing
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			// Validate IP format
			if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
				return $remote_addr;
			}
		}

		return '0.0.0.0';
	}
}
