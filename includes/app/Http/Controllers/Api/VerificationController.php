<?php

namespace SureFeedback\Http\Controllers\Api;

defined( 'ABSPATH' ) || exit;

use SureFeedback\Services\ApiGatewayService;
use SureFeedback\Repositories\ConnectionRepository;
use SureFeedback\Constants\VerificationStatus;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Verification Controller
 *
 * Handles verification requests to the SureFeedback Laravel API
 *
 * @package SureFeedback\Http\Controllers\Api
 * @author Anurag Singh <anurags@bsf.io>
 */
class VerificationController {

	/**
	 * API Gateway Service
	 *
	 * @var ApiGatewayService
	 */
	private $api_gateway;

	/**
	 * Connection Repository
	 *
	 * @var ConnectionRepository
	 */
	private $connection_repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_gateway          = new ApiGatewayService();
		$this->connection_repository = new ConnectionRepository();
	}

	/**
	 * Verify connection with SureFeedback Laravel API
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_connection( WP_REST_Request $request ) {
		try {
			// Get site token from request body first, fallback to database
			$site_token = $request->get_param( 'site_token' );
			
			if ( empty( $site_token ) ) {
				// Fallback to database value using repository
				$site_token = $this->connection_repository->getAccessToken();
			}

			if ( empty( $site_token ) ) {
				return new WP_Error(
					'no_site_token',
					'Site token not found. Please reconnect your site.',
					array( 'status' => 400 )
				);
			}

			// Get stored JWT token for API authentication using repository
			$jwt_token = $this->connection_repository->getUserToken();

			// Validate JWT token - must be non-empty and not just whitespace
			// JWT token is required for verification endpoint
			if ( empty( $jwt_token ) || trim( $jwt_token ) === '' ) {
				return new WP_Error(
					'jwt_token_missing',
					'Authentication token not found. Please reconnect your site to refresh the authentication token.',
					array( 
						'status' => 401,
						'requires_reconnection' => true,
						'debug' => array(
							'has_site_token' => ! empty( $site_token ),
							'has_jwt_token' => false,
							'connection_status' => $this->connection_repository->getConnectionStatus(),
							'site_id' => $this->connection_repository->getSiteId(),
						)
					)
				);
			}

			// Trim whitespace from token
			$jwt_token = trim( $jwt_token );

			// Call Laravel API via Gateway Service
			$response = $this->api_gateway->verifyIntegration( $site_token, $jwt_token );

			// Handle API errors
			if ( is_wp_error( $response ) ) {
				$error_data = $response->get_error_data();
				$error_message = $response->get_error_message();
				$error_code = $response->get_error_code();
				
				// Check if it's a "Token not provided" error from Laravel API
				// Check multiple possible error message formats
				$is_token_error = false;
				
				// Check error message directly
				if ( stripos( $error_message, 'token not provided' ) !== false || 
					 stripos( $error_message, 'token not found' ) !== false ||
					 stripos( $error_message, 'unauthorized' ) !== false ) {
					$is_token_error = true;
				}
				
				// Check error data structure
				if ( isset( $error_data['response'] ) ) {
					// Check both 'message' and 'error' fields in response
					$response_message = $error_data['response']['message'] ?? '';
					$response_error = $error_data['response']['error'] ?? '';
					$response_status = $error_data['response']['status'] ?? '';
					
					// Check for token-related errors in various fields
					$token_error_patterns = array(
						'token not provided',
						'token not found',
						'token_not_found',
						'TOKEN_NOT_FOUND',
						'unauthorized',
						'script token not found'
					);
					
					$combined_message = strtolower( $response_message . ' ' . $response_error . ' ' . $response_status );
					foreach ( $token_error_patterns as $pattern ) {
						if ( stripos( $combined_message, $pattern ) !== false ) {
							$is_token_error = true;
							break;
						}
					}
				}
				
				// Check status code (401 or 404 can indicate authentication/token issues)
				if ( isset( $error_data['status'] ) ) {
					$status_code = $error_data['status'];
					if ( $status_code === 401 ) {
						$is_token_error = true;
					} elseif ( $status_code === 404 ) {
						// 404 might also indicate token not found
						if ( isset( $error_data['response']['status'] ) && 
							 stripos( $error_data['response']['status'], 'TOKEN' ) !== false ) {
							$is_token_error = true;
						}
					}
				}
				
				if ( $is_token_error ) {
					// Determine the specific error message based on the response
					$user_message = 'Authentication or connection issue detected. ';
					if ( isset( $error_data['response']['error'] ) ) {
						$laravel_error = $error_data['response']['error'];
						if ( stripos( $laravel_error, 'script token not found' ) !== false || 
							 stripos( $laravel_error, 'site is inactive' ) !== false ) {
							$user_message = 'Site token not found in the system or site is inactive. Please reconnect your site.';
						} elseif ( stripos( $laravel_error, 'token not provided' ) !== false ) {
							$user_message = 'Authentication token not found or expired. Please reconnect your site to refresh the authentication token.';
						} else {
							$user_message = $laravel_error . ' Please reconnect your site.';
						}
					} else {
						$user_message = 'Authentication token not found or expired. Please reconnect your site to refresh the authentication token.';
					}
					
					return new WP_Error(
						'jwt_token_missing',
						$user_message,
						array( 
							'status' => isset( $error_data['status'] ) ? $error_data['status'] : 401,
							'requires_reconnection' => true,
							'original_error' => $error_message,
							'laravel_error' => $error_data['response']['error'] ?? '',
							'debug_info' => array(
								'has_jwt_token' => ! empty( $jwt_token ),
								'jwt_token_length' => strlen( $jwt_token ?? '' ),
								'site_token_length' => strlen( $site_token ?? '' ),
								'error_code' => $error_code,
								'http_status' => $error_data['status'] ?? 'unknown',
							)
						)
					);
				}
				
				return $response;
			}

			// Extract response data
			$decoded     = $response['data'] ?? array();
			$status_code = $response['status_code'] ?? 200;

			// Determine verification state from decoded response
			$is_fully_verified    = isset( $decoded['verification'] );
			$is_script_not_loaded = isset( $decoded['data']['integrated'] ) && ! $decoded['data']['integrated'];

			// Update DB based on verification state
			if ( $is_fully_verified ) {
				update_option( 'surefeedback_last_verification', current_time( 'mysql' ) );
				update_option( 'surefeedback_is_fully_verified', VerificationStatus::CONNECTED );
				if ( isset( $decoded['site']['id'] ) ) {
					update_option( 'surefeedback_site_id', $decoded['site']['id'] );
				}
			} elseif ( $is_script_not_loaded ) {
				update_option( 'surefeedback_is_fully_verified', VerificationStatus::PENDING );
				if ( isset( $decoded['data']['site']['id'] ) ) {
					update_option( 'surefeedback_site_id', $decoded['data']['site']['id'] );
				}
			} else {
				update_option( 'surefeedback_is_fully_verified', VerificationStatus::NOT_VERIFIED );
			}

			$verification_status = $is_fully_verified ? 'verified' : ( $is_script_not_loaded ? 'pending' : 'not_verified' );

			return new WP_REST_Response(
				array(
					'success'                  => $decoded['success'] ?? false,
					'message'                  => $decoded['message'] ?? 'Verification completed',
					'data'                     => $decoded,
					'verification_status'      => $verification_status,
					'is_fully_verified'        => $is_fully_verified,
					'is_script_pending'        => $is_script_not_loaded,
					'integration_instructions' => $is_script_not_loaded ? ( $decoded['data']['instructions'] ?? null ) : null,
				),
				$status_code
			);

		} catch ( \Exception $e ) {
			return new WP_Error( 'verification_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}
