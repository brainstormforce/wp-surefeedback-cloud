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
			$this->logError( 'Connection status error: ' . $e->getMessage() );
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
				$this->logError( 'Invalid signature during connection attempt' );
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
			$this->logError( 'Connection establishment error: ' . $e->getMessage() );
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

				if ( is_wp_error( $disconnect_result ) ) {
					$this->logError( 'Failed to notify Laravel API about disconnection: ' . $disconnect_result->get_error_message() );
				} else {
					$this->logError( 'Laravel API notified about disconnection successfully' );
				}
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
			$this->logError( 'Reset error: ' . $e->getMessage() );
			return $this->error( 'Failed to reset site connection', 500 );
		}
	}

	/**
	 * Handle webhook from SureFeedback API with dual-layer security verification
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

		// Get webhook data early to extract state
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		// SECURITY LAYER 1: State verification for connection flow
		$state                     = $data['state'] ?? null;
		$state_verification_passed = false;

		if ( ! empty( $state ) ) {
			// Verify state parameter matches stored state
			if ( $this->verifyWebhookState( $state ) ) {
				$state_verification_passed = true;
				$this->logError( 'Webhook state verification passed', array( 'state' => substr( $state, 0, 10 ) . '...' ) );
			} else {
				$this->logError( 'Webhook state verification failed', array( 'received_state' => substr( $state, 0, 10 ) . '...' ) );
			}
		} else {
			$this->logError( 'Webhook state parameter missing - will rely on HMAC signature only' );
		}

		// SECURITY LAYER 2: HMAC Signature Verification
		$raw_body                 = $request->get_body();
		$webhook_signature        = $request->get_header( 'X-SureFeedback-Signature' );
		$webhook_timestamp        = $request->get_header( 'X-SureFeedback-Timestamp' );
		$hmac_verification_passed = false;

		if ( ! empty( $webhook_signature ) && ! empty( $webhook_timestamp ) ) {
			// Verify HMAC signature
			$webhook_secret = $security_service->getWebhookSigningSecret();

			if ( ! empty( $webhook_secret ) && $security_service->verifyWebhookSignature( $raw_body, $webhook_signature, $webhook_timestamp, $webhook_secret ) ) {
				$hmac_verification_passed = true;
				$this->logError(
					'Webhook HMAC signature verification passed',
					array(
						'timestamp'          => $webhook_timestamp,
						'signature_method'   => 'hmac_sha256',
						'has_webhook_secret' => true,
					)
				);
			} else {
				$this->logError(
					'Webhook HMAC signature verification failed',
					array(
						'has_signature'      => ! empty( $webhook_signature ),
						'has_timestamp'      => ! empty( $webhook_timestamp ),
						'has_webhook_secret' => ! empty( $webhook_secret ),
						'payload_length'     => strlen( $raw_body ),
					)
				);
			}
		} else {
			$this->logError( 'Webhook HMAC signature headers missing - will rely on state verification only' );
		}

		// Require at least one verification method to pass
		if ( ! $state_verification_passed && ! $hmac_verification_passed ) {
			$this->logError(
				'Webhook security verification failed - neither state nor HMAC signature verification passed',
				array(
					'state_verification' => $state_verification_passed,
					'hmac_verification'  => $hmac_verification_passed,
					'has_state'          => ! empty( $state ),
					'has_signature'      => ! empty( $webhook_signature ),
				)
			);
			return $this->error( 'Webhook authentication failed', 401 );
		}

		// Log successful verification with details
		$verification_methods = array();
		if ( $state_verification_passed ) {
			$verification_methods[] = 'state_verification';
		}
		if ( $hmac_verification_passed ) {
			$verification_methods[] = 'hmac_signature';
		}

		// Get current security status for detailed logging
		$security_info = $this->getWebhookSecurityInfo();

		$this->logError(
			'Webhook security verification successful',
			array(
				'verification_methods' => implode( ' + ', $verification_methods ),
				'security_level'       => count( $verification_methods ) > 1 ? 'dual_layer' : 'single_layer',
				'available_security'   => $security_info['security_methods'] ?? array(),
				'overall_security'     => $security_info['security_level'] ?? 'unknown',
			)
		);

		try {
			// Data already extracted earlier for state verification

			$siteData = isset( $data['data'] ) ? $data['data'] : $data;

			$siteId   = $siteData['id'] ?? $data['site_id'] ?? null;
			$apiToken = $siteData['api_token'] ?? $data['site_token'] ?? null;

			if ( empty( $data['success'] ) || empty( $apiToken ) || empty( $siteId ) ) {
				$this->logError( 'Webhook missing required fields', array( 'received_data' => $data ) );
				return $this->error( 'Missing required webhook data', 400 );
			}

			if ( $data['success'] === '1' || $data['success'] === 1 || $data['success'] === true ) {
				$this->connection_repository->setSiteId( sanitize_text_field( $siteId ) );
				$this->connection_repository->setAccessToken( sanitize_text_field( $apiToken ) );
				$this->connection_repository->setConnectionStatus( 'connected' );

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
					$isActive = (bool) $siteData['is_active'];
					$this->connection_repository->setIsActive( $isActive );
				}

				if ( ! empty( $siteData['created_at'] ) ) {
					$this->connection_repository->setCreatedAt( sanitize_text_field( $siteData['created_at'] ) );
				}

				if ( ! empty( $data['parent_url'] ) ) {
					$this->connection_repository->setParentUrl( esc_url_raw( $data['parent_url'] ) );
				}

				// Store JWT token for authenticated API calls (e.g., disconnect)
				if ( ! empty( $data['user_token'] ) ) {
					$this->connection_repository->setUserToken( sanitize_text_field( $data['user_token'] ) );
				}

				// Store webhook signing secret for HMAC signature verification
				if ( ! empty( $data['webhook_signing_secret'] ) ) {
					$security_service = new \SureFeedback\Services\SecurityService();
					$secret_stored    = $security_service->storeWebhookSigningSecret( $data['webhook_signing_secret'] );

					if ( $secret_stored ) {
						$this->logError(
							'Webhook signing secret stored successfully for enhanced security',
							array(
								'site_id' => $siteId,
							)
						);
					} else {
						$this->logError(
							'Failed to store webhook signing secret',
							array(
								'site_id' => $siteId,
							)
						);
					}
				}

				// Save site_connected field
				$site_connected_value = isset( $data['site_connected'] )
					? (bool) $data['site_connected']
					: true;

				$this->connection_repository->setSiteConnected( $site_connected_value );

				// Set verification fields from webhook data or use initial state
				// WordPress doesn't store null values, so we use empty string for last_verification
				$last_verification_value = $data['surefeedback_last_verification'] ?? '';
				$is_fully_verified_value = isset( $data['is_fully_verified'] )
					? (int) $data['is_fully_verified']
					: VerificationStatus::PENDING;

				if ( ! empty( $last_verification_value ) ) {
					$this->connection_repository->setLastVerification( $last_verification_value );
				}
				$this->connection_repository->setIsFullyVerified( $is_fully_verified_value );

				wp_cache_delete( 'surefeedback_settings' );
				delete_transient( 'surefeedback_connection_check' );

				return $this->success(
					array(
						'message'      => 'Webhook processed successfully',
						'connected'    => true,
						'site_id'      => $siteId,
						'processed_at' => current_time( 'mysql' ),
					)
				);
			}

			$this->logError( 'Webhook indicated failure', array( 'webhook_data' => $data ) );
			return $this->error( 'Webhook indicated connection failure', 400 );

		} catch ( \Exception $e ) {
			$this->logError( 'Webhook processing error: ' . $e->getMessage() );
			return $this->error( 'Failed to process webhook', 500 );
		}
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

			$this->logError( 'Storing state for webhook verification', array( 'state' => $state ) );

			$success = $this->storeWebhookState( $state );

			if ( $success ) {
				$this->logError( 'State stored successfully', array( 'state' => $state ) );
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
			$this->logError( 'Store state error: ' . $e->getMessage() );
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
				$this->logError(
					'Disconnect webhook missing or invalid secret',
					array(
						'has_secret'        => ! empty( $webhook_secret ),
						'has_stored_secret' => ! empty( $stored_webhook_secret ),
					)
				);
				return $this->error( 'Invalid or missing webhook secret', 401 );
			}

			if ( ! hash_equals( $stored_webhook_secret, $webhook_secret ) ) {
				$this->logError( 'Disconnect webhook secret mismatch' );
				return $this->error( 'Webhook secret validation failed', 401 );
			}

			// Get disconnect data from request
			$data = $request->get_json_params();
			if ( empty( $data ) ) {
				$data = $request->get_params();
			}

			$site_id = $data['site_id'] ?? null;
			$force   = $data['force'] ?? false;

			$this->logInfo(
				'Disconnect webhook received',
				array(
					'site_id'         => $site_id,
					'force'           => $force,
					'disconnected_by' => $data['disconnected_by'] ?? 'unknown',
				)
			);

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

			$this->logInfo(
				'Site disconnected successfully via webhook',
				array(
					'site_id'         => $site_id,
					'disconnected_at' => current_time( 'mysql' ),
				)
			);

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
			$this->logError( 'Disconnect webhook error: ' . $e->getMessage() );
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
			$security_service = new \SureFeedback\Services\SecurityService();
			$stored_secret    = get_option( 'surefeedback_webhook_signing_secret' );
			$stored_state     = get_option( 'surefeedback_webhook_state' );

			// Determine available security methods
			$security_methods = array();
			if ( ! empty( $stored_secret ) ) {
				$security_methods[] = 'hmac_signature';
			}
			if ( ! empty( $stored_state ) && is_array( $stored_state ) ) {
				if ( time() <= $stored_state['expiry'] ) {
					$security_methods[] = 'state_verification';
				}
			}

			$security_level = 'none';
			if ( count( $security_methods ) === 1 ) {
				$security_level = 'single_layer';
			} elseif ( count( $security_methods ) > 1 ) {
				$security_level = 'dual_layer';
			}

			return array(
				'has_webhook_secret'   => ! empty( $stored_secret ),
				'secret_length'        => ! empty( $stored_secret ) ? strlen( $stored_secret ) : 0,
				'secret_preview'       => ! empty( $stored_secret ) ? substr( $stored_secret, 0, 8 ) . '...' : null,
				'has_stored_state'     => ! empty( $stored_state ),
				'state_valid'          => ! empty( $stored_state ) && is_array( $stored_state ) && time() <= $stored_state['expiry'],
				'connection_status'    => $this->isConnected() ? 'connected' : 'disconnected',
				'security_methods'     => $security_methods,
				'security_level'       => $security_level,
				'security_description' => $this->getSecurityDescription( $security_level, $security_methods ),
			);

		} catch ( \Exception $e ) {
			$this->logError( 'Webhook secret info error: ' . $e->getMessage() );
			return array(
				'has_webhook_secret' => false,
				'security_level'     => 'none',
				'error'              => 'Failed to get webhook security info',
			);
		}
	}

	/**
	 * Get security description based on available methods
	 *
	 * @param string $level Security level
	 * @param array $methods Available security methods
	 * @return string Security description
	 */
	private function getSecurityDescription( string $level, array $methods ): string {
		switch ( $level ) {
			case 'dual_layer':
				return 'Enhanced security with both HMAC signature and state verification';
			case 'single_layer':
				if ( in_array( 'hmac_signature', $methods, true ) ) {
					return 'HMAC signature verification enabled (recommended)';
				} elseif ( in_array( 'state_verification', $methods, true ) ) {
					return 'State verification enabled (fallback method)';
				}
				return 'Single layer security enabled';
			default:
				return 'No security methods available (connection required)';
		}
	}

	/**
	 * Store webhook state for verification
	 *
	 * @param string $state The state to store
	 * @return bool Success status
	 */
	private function storeWebhookState( string $state ): bool {
		// Store state with expiration (15 minutes)
		$expiry = time() + ( 15 * MINUTE_IN_SECONDS );
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

		// Check if state data exists
		if ( ! $stored_data || ! is_array( $stored_data ) ) {
			$this->logError( 'No webhook state found in storage' );
			return false;
		}

		// Check if state has expired
		if ( time() > $stored_data['expiry'] ) {
			$this->logError( 'Webhook state has expired' );
			delete_option( 'surefeedback_webhook_state' ); // Clean up expired state
			return false;
		}

		// Verify state matches
		$stored_state = $stored_data['state'];
		if ( ! hash_equals( $stored_state, $received_state ) ) {
			$this->logError(
				'Webhook state mismatch',
				array(
					'stored_state'   => substr( $stored_state, 0, 10 ) . '...',
					'received_state' => substr( $received_state, 0, 10 ) . '...',
				)
			);
			return false;
		}

		// State verification successful - remove it to prevent replay
		delete_option( 'surefeedback_webhook_state' );
		$this->logError( 'Webhook state verification successful' );

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
