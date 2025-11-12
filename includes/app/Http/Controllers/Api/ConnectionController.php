<?php

namespace SureFeedback\Http\Controllers\Api;

defined( 'ABSPATH' ) || exit;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Services\JWTService;
use SureFeedback\Constants\VerificationStatus;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class ConnectionController extends Controller {

	protected $jwtService;
	protected $connection_repository;

	public function __construct() {
		parent::__construct();
		$this->jwtService            = new JWTService();
		$this->connection_repository = new \SureFeedback\Repositories\ConnectionRepository();
	}

	public function status( WP_REST_Request $request ) {
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
	}

	public function connect( WP_REST_Request $request ) {
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

		if ( ! $this->verifySignature( $site_token, $signature ) ) {
			return $this->error( 'Invalid signature', 403 );
		}

		$access_token = wp_generate_password( 32, false );

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
	}

	public function reset( WP_REST_Request $request ) {
		$nonce_result = $this->validateNonce( $request );
		if ( is_wp_error( $nonce_result ) ) {
			return $nonce_result;
		}

		$capability_result = $this->validateCapability( 'manage_options' );
		if ( is_wp_error( $capability_result ) ) {
			return $capability_result;
		}

		$site_id    = $this->connection_repository->getSiteId();
		$site_token = $this->connection_repository->getAccessToken();
		$domain     = $this->connection_repository->getDomain();
		$jwt_token  = $this->connection_repository->getUserToken();

		if ( ! empty( $site_id ) && ! empty( $jwt_token ) ) {
			$api_gateway = new \SureFeedback\Services\ApiGatewayService();
			$api_gateway->disconnectSite( $site_id, $site_token, $domain, $jwt_token );
		}

		$this->connection_repository->clearAllConnectionData();

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
	}

	public function webhook( WP_REST_Request $request ) {
		$security_service = new \SureFeedback\Services\SecurityService();
		$client_ip        = $security_service->getClientIp();

		if ( ! $security_service->checkRateLimit( 'webhook_' . $client_ip, 10, 300 ) ) {
			return $this->error( 'Rate limit exceeded. Try again later.', 429 );
		}

		$data        = $request->get_json_params() ?: $request->get_params();
		$auth_result = $this->authenticateWebhook( $request, $data, $security_service );

		if ( is_wp_error( $auth_result ) ) {
			return $auth_result;
		}

		return $this->processWebhookData( $data );
	}

	public function store_state( WP_REST_Request $request ) {
		$this->cleanupExpiredStates();

		$nonce_result = $this->validateNonce( $request );
		if ( is_wp_error( $nonce_result ) ) {
			return $nonce_result;
		}

		$capability_result = $this->validateCapability( 'manage_options' );
		if ( is_wp_error( $capability_result ) ) {
			return $capability_result;
		}

		$validation = $this->validate( $request, array( 'state' => 'required|string' ) );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$state   = sanitize_text_field( $request->get_param( 'state' ) );
		$success = $this->storeWebhookState( $state );

		if ( $success ) {
			return $this->success(
				array(
					'message'   => 'State stored successfully',
					'state'     => $state,
					'stored_at' => current_time( 'mysql' ),
					'expiry'    => gmdate( 'Y-m-d H:i:s', time() + ( 60 * MINUTE_IN_SECONDS ) ),
				)
			);
		}

		return $this->error( 'Failed to store state', 500 );
	}

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

	public function disconnect_webhook( WP_REST_Request $request ) {
		$security_service = new \SureFeedback\Services\SecurityService();
		$client_ip        = $security_service->getClientIp();

		if ( ! $security_service->checkRateLimit( 'disconnect_webhook_' . $client_ip, 5, 600 ) ) {
			return $this->error( 'Rate limit exceeded. Try again later.', 429 );
		}

		$webhook_secret        = $request->get_header( 'X-Webhook-Secret' );
		$stored_webhook_secret = $this->getDisconnectWebhookSecret();

		if ( empty( $webhook_secret ) || empty( $stored_webhook_secret ) ) {
			return $this->error( 'Invalid or missing webhook secret', 401 );
		}

		if ( ! hash_equals( $stored_webhook_secret, $webhook_secret ) ) {
			return $this->error( 'Webhook secret validation failed', 401 );
		}

		$data = $request->get_json_params() ?: $request->get_params();
		$this->clearAllSureFeedbackOptions();

		wp_cache_delete( 'surefeedback_settings' );
		delete_transient( 'surefeedback_connection_check' );

		return $this->success(
			array(
				'message'         => 'Site disconnected successfully',
				'disconnected'    => true,
				'site_id'         => $data['site_id'] ?? null,
				'disconnected_at' => current_time( 'mysql' ),
				'status'          => 'disconnected',
			)
		);
	}

	private function isConnected(): bool {
		return $this->connection_repository->isConnected();
	}

	private function getConnectionSignatureSecret(): string {
		$secret = get_option( 'surefeedback_connection_signature_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, false );
			update_option( 'surefeedback_connection_signature_secret', $secret );
		}
		return $secret;
	}

	private function verifySignature( string $site_token, string $signature ): bool {
		$secret             = $this->getConnectionSignatureSecret();
		$expected_signature = hash_hmac( 'sha256', $site_token, $secret );
		return hash_equals( $expected_signature, $signature );
	}

	private function processWebhookData( array $data ) {
		$siteData = $data['data'] ?? $data;
		$siteId   = $siteData['id'] ?? $data['site_id'] ?? null;
		$apiToken = $siteData['api_token'] ?? $data['site_token'] ?? null;

		if ( empty( $data['success'] ) || empty( $apiToken ) || empty( $siteId ) ) {
			return $this->error( 'Missing required webhook data', 400 );
		}

		if ( $data['success'] === '1' || $data['success'] === 1 || $data['success'] === true ) {
			$this->updateConnectionFromWebhook( $siteData, $data, $siteId, $apiToken );

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
				),
				200
			);
		}

		return $this->error( 'Webhook indicated connection failure', 400 );
	}

	private function updateConnectionFromWebhook( array $siteData, array $data, string $siteId, string $apiToken ): void {
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
			$this->connection_repository->setIsActive( (bool) $siteData['is_active'] );
		}

		if ( ! empty( $siteData['created_at'] ) ) {
			$this->connection_repository->setCreatedAt( sanitize_text_field( $siteData['created_at'] ) );
		}

		if ( ! empty( $data['parent_url'] ) ) {
			$this->connection_repository->setParentUrl( esc_url_raw( $data['parent_url'] ) );
		}

		if ( ! empty( $data['user_token'] ) ) {
			$this->connection_repository->setUserToken( sanitize_text_field( $data['user_token'] ) );
		}

		$this->connection_repository->setSiteConnected( isset( $data['site_connected'] ) ? (bool) $data['site_connected'] : true );
		$this->connection_repository->setIsFullyVerified( $data['is_fully_verified'] ?? VerificationStatus::PENDING );
	}

	private function getDisconnectWebhookSecret(): string {
		$secret = get_option( 'surefeedback_disconnect_webhook_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, false );
			update_option( 'surefeedback_disconnect_webhook_secret', $secret );
		}
		return $secret;
	}

	private function authenticateWebhook( WP_REST_Request $request, array $data, $security_service ) {
		$webhook_state   = $request->get_header( 'X-SureFeedback-State' );
		$state_from_data = $data['state'] ?? null;
		$state           = ! empty( $webhook_state ) ? $webhook_state : $state_from_data;

		if ( empty( $state ) ) {
			return $this->error( 'Missing webhook state', 400 );
		}

		if ( ! $this->verifyWebhookState( $state ) ) {
			$client_ip = $this->getClientIp( $request );
			$security_service->checkRateLimit( 'webhook_state_fail_' . $client_ip, 3, 600 );
			return $this->error( 'Invalid webhook state', 401 );
		}

		return true;
	}

	private function storeWebhookState( string $state ): bool {
		$expiry = time() + ( 60 * MINUTE_IN_SECONDS );
		return update_option(
			'surefeedback_webhook_state',
			array(
				'state'      => $state,
				'expiry'     => $expiry,
				'created_at' => time(),
			)
		);
	}

	private function verifyWebhookState( string $received_state ): bool {
		$stored_data = get_option( 'surefeedback_webhook_state' );

		if ( ! $stored_data || ! is_array( $stored_data ) ) {
			return false;
		}

		if ( time() > $stored_data['expiry'] ) {
			delete_option( 'surefeedback_webhook_state' );
			return false;
		}

		return hash_equals( $stored_data['state'], $received_state );
	}

	private function cleanupExpiredStates(): bool {
		$stored_data = get_option( 'surefeedback_webhook_state' );
		if ( ! $stored_data || ! is_array( $stored_data ) ) {
			return false;
		}
		if ( time() > $stored_data['expiry'] ) {
			delete_option( 'surefeedback_webhook_state' );
			return true;
		}
		return false;
	}

	private function getClientIp( WP_REST_Request $request ): string {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
				return $remote_addr;
			}
		}
		return '0.0.0.0';
	}

	private function clearAllSureFeedbackOptions(): void {
		$options = array(
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

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}
}
