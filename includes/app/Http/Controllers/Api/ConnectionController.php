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
	 * Verify signature
	 *
	 * @param string $site_token
	 * @param string $signature
	 * @return bool
	 */
	private function verifySignature( string $site_token, string $signature ): bool {
		$expected_signature = hash_hmac( 'sha256', $site_token, SECURE_AUTH_KEY );
		return hash_equals( $expected_signature, $signature );
	}

	/**
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
	 * Handle webhook from SureFeedback API
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function webhook( WP_REST_Request $request ): WP_REST_Response {
		try {
			$data = $request->get_json_params();
			if ( empty( $data ) ) {
				$data = $request->get_params();
			}

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
					error_log( 'SureFeedback: User token stored successfully during webhook' );
				} else {
					error_log( 'SureFeedback: WARNING - user_token not provided in webhook payload. This may cause authentication issues later.' );
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
					'issued_at'  => isset( $token_data['iat'] ) ? date( 'Y-m-d H:i:s', $token_data['iat'] ) : null,
					'expires_at' => isset( $token_data['exp'] ) ? date( 'Y-m-d H:i:s', $token_data['exp'] ) : null,
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
	 * Handle disconnect webhook from SureFeedback API (Webhook Secret protected)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect_webhook( WP_REST_Request $request ) {
		try {
			// Verify webhook secret key from X-Webhook-Secret header
			$webhook_secret      = $request->get_header( 'X-Webhook-Secret' );
			$stored_access_token = get_option( 'surefeedback_access_token' );

			// Validate webhook secret matches the stored API token
			if ( empty( $webhook_secret ) || empty( $stored_access_token ) ) {
				$this->logError(
					'Disconnect webhook missing or invalid secret',
					array(
						'has_secret'       => ! empty( $webhook_secret ),
						'has_stored_token' => ! empty( $stored_access_token ),
					)
				);
				return $this->error( 'Invalid or missing webhook secret', 401 );
			}

			if ( ! hash_equals( $stored_access_token, $webhook_secret ) ) {
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
	 * Get client IP address
	 *
	 * @param WP_REST_Request $request
	 * @return string
	 */
	private function getClientIp( WP_REST_Request $request ): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ips = explode( ',', $_SERVER[ $header ] );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}
}
