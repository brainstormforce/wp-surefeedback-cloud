<?php

namespace SureFeedback\Services;

defined( 'ABSPATH' ) || exit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;
use Exception;

/**
 * JWT Service
 *
 * Handles JWT token validation and verification for API endpoints
 *
 * @package SureFeedback\Services
 * @author Anurag Singh <anurags@bsf.io>
 */
class JWTService {

	/**
	 * JWT Secret Key
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * JWT Algorithm
	 *
	 * @var string
	 */
	private $algorithm = 'HS256';

	/**
	 * Connection Repository
	 *
	 * @var \SureFeedback\Repositories\ConnectionRepository
	 */
	private $connection_repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->connection_repository = new \SureFeedback\Repositories\ConnectionRepository();
		$this->secret_key            = $this->connection_repository->getJwtSecret();
	}

	/**
	 * Validate JWT token from request headers
	 *
	 * @param \WP_REST_Request $request The REST request object
	 * @return array|false Returns decoded token data or false if invalid
	 */
	public function validate_token_from_request( $request ) {
		// Get token from Authorization header
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! $auth_header ) {
			return false;
		}

		// Extract token from "Bearer <token>" format
		if ( strpos( $auth_header, 'Bearer ' ) === 0 ) {
			$token = substr( $auth_header, 7 );
		} else {
			$token = $auth_header;
		}

		return $this->validate_token( $token );
	}

	/**
	 * Validate JWT token
	 *
	 * @param string $token The JWT token to validate
	 * @return array|false Returns decoded token data or false if invalid
	 */
	public function validate_token( $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		try {
			$decoded = JWT::decode( $token, new Key( $this->secret_key, $this->algorithm ) );

			// Convert stdClass to array for easier handling
			return json_decode( json_encode( $decoded ), true );

		} catch ( ExpiredException $e ) {
			return false;
		} catch ( BeforeValidException $e ) {
			return false;
		} catch ( SignatureInvalidException $e ) {
			return false;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Generate JWT token (for testing purposes)
	 *
	 * @param array $payload Token payload
	 * @param int   $expiration_time Expiration time (timestamp)
	 * @return string JWT token
	 */
	public function generate_token( $payload = array(), $expiration_time = null ) {
		if ( $expiration_time === null ) {
			$expiration_time = time() + ( 24 * 60 * 60 ); // 24 hours default
		}

		$default_payload = array(
			'iat' => time(), // Issued at
			'exp' => $expiration_time, // Expiration
			'iss' => get_site_url(), // Issuer
		);

		$token_payload = array_merge( $default_payload, $payload );

		return JWT::encode( $token_payload, $this->secret_key, $this->algorithm );
	}

	/**
	 * Check if user has required permissions
	 *
	 * @param array  $token_data Decoded token data
	 * @param string $required_permission Required permission
	 * @return bool
	 */
	public function check_permission( $token_data, $required_permission = 'manage_options' ) {
		// Check if token has specific permissions array
		if ( isset( $token_data['permissions'] ) ) {
			return in_array( $required_permission, $token_data['permissions'] );
		}

		// Check if token has role field (from SureFeedback NextJS app)
		if ( isset( $token_data['role'] ) ) {
			$role = strtoupper( $token_data['role'] );

			// If role is ADMIN, grant all permissions
			if ( $role === 'ADMIN' || $role === 'ADMINISTRATOR' ) {
				return true;
			}

			// Map roles to WordPress capabilities
			$role_permissions = array(
				'ADMIN'         => array( 'manage_options', 'administrator' ),
				'ADMINISTRATOR' => array( 'manage_options', 'administrator' ),
				'EDITOR'        => array( 'edit_posts', 'edit_pages' ),
				'AUTHOR'        => array( 'edit_posts' ),
				'CONTRIBUTOR'   => array( 'edit_posts' ),
				'SUBSCRIBER'    => array( 'read' ),
			);

			if ( isset( $role_permissions[ $role ] ) ) {
				return in_array( $required_permission, $role_permissions[ $role ] );
			}
		}

		// Check WordPress user capabilities if user_id is present
		if ( isset( $token_data['user_id'] ) ) {
			$user = get_user_by( 'id', $token_data['user_id'] );
			if ( $user ) {
				return user_can( $user, $required_permission );
			}
		}

		if ( isset( $token_data['userId'] ) ) {
			if ( isset( $token_data['role'] ) ) {
				$role = strtoupper( $token_data['role'] );
				return in_array( $role, array( 'ADMIN', 'ADMINISTRATOR' ) );
			}
		}

		// Default to false for security
		return false;
	}

	/**
	 * Create middleware function for WordPress REST API
	 *
	 * @param string $required_permission Required permission
	 * @return callable
	 */
	public function create_middleware( $required_permission = 'manage_options' ) {
		return function ( $request ) use ( $required_permission ) {
			$token_data = $this->validate_token_from_request( $request );

			if ( ! $token_data ) {
				return new \WP_Error(
					'jwt_auth_invalid_token',
					__( 'Invalid or missing JWT token.', 'surefeedback' ),
					array( 'status' => 401 )
				);
			}

			if ( ! $this->check_permission( $token_data, $required_permission ) ) {
				return new \WP_Error(
					'jwt_auth_insufficient_permissions',
					__( 'Insufficient permissions.', 'surefeedback' ),
					array( 'status' => 403 )
				);
			}

			// Store token data in request for use in controller
			$request->set_param( '_jwt_token_data', $token_data );

			return true;
		};
	}
}
