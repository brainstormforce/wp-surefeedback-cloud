<?php

namespace SureFeedback\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CORS Service
 *
 * Handles Cross-Origin Resource Sharing (CORS) for SureFeedback endpoints.
 *
 * @package SureFeedback\Services
 * @author Anurag Singh <anurags@bsf.io>
 */
class CorsService {

	/**
	 * Allowed origins for CORS requests
	 *
	 * @var array
	 */
	protected $allowed_origins = array();

	/**
	 * Initialize CORS service
	 */
	public function __construct() {
		$this->allowed_origins = $this->getAllowedOrigins();
		$this->initHooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	protected function initHooks(): void {
		// Handle CORS for REST API requests
		add_filter( 'rest_pre_serve_request', array( $this, 'handleCorsHeaders' ), 10, 4 );
		
		// Handle preflight OPTIONS requests
		add_action( 'rest_api_init', array( $this, 'handlePreflightRequests' ) );
	}

	/**
	 * Get allowed origins for CORS requests
	 *
	 * @return array
	 */
	protected function getAllowedOrigins(): array {
		$origins = array();

		// Get the parent app URL from configuration
		$app_url = $this->getAppBaseUrl();
		if ( $app_url ) {
			$origins[] = rtrim( $app_url, '/' );
		}

		/**
		 * Filter allowed CORS origins
		 *
		 * @param array $origins Allowed origins
		 */
		return apply_filters( 'surefeedback_cors_allowed_origins', $origins );
	}

	/**
	 * Get app base URL from configuration
	 *
	 * @return string|null
	 */
	protected function getAppBaseUrl(): ?string {
		// Only use SUREFEEDBACK_APP_BASE_URL constant
		if ( defined( 'SUREFEEDBACK_APP_BASE_URL' ) ) {
			return SUREFEEDBACK_APP_BASE_URL;
		}

		return null;
	}

	/**
	 * Handle CORS headers for REST API responses
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  Server instance.
	 * @return bool
	 */
	public function handleCorsHeaders( $served, $result, $request, $server ) {
		$route = $request->get_route();

		// Only apply CORS headers to SureFeedback endpoints
		if ( strpos( $route, '/surefeedback/' ) !== 0 ) {
			return $served;
		}

		$origin = $this->getRequestOrigin();

		// Check if origin is allowed
		if ( ! $this->isOriginAllowed( $origin ) ) {
			return $served;
		}

		// Set CORS headers
		$this->setCorsHeaders( $origin );

		return $served;
	}

	/**
	 * Handle preflight OPTIONS requests
	 *
	 * @return void
	 */
	public function handlePreflightRequests(): void {
		if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
			$origin = $this->getRequestOrigin();
			
			if ( $this->isOriginAllowed( $origin ) ) {
				$this->setCorsHeaders( $origin );
				$this->setPreflightHeaders();
				
				// Send 200 status for preflight requests
				status_header( 200 );
				exit;
			}
		}
	}

	/**
	 * Get request origin
	 *
	 * @return string|null
	 */
	protected function getRequestOrigin(): ?string {
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			return sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		}
		
		return null;
	}

	/**
	 * Check if origin is allowed
	 *
	 * @param string|null $origin Request origin
	 * @return bool
	 */
	protected function isOriginAllowed( ?string $origin ): bool {
		if ( ! $origin ) {
			return false;
		}

		// Exact match check
		if ( in_array( $origin, $this->allowed_origins, true ) ) {
			return true;
		}

		// Check for wildcard subdomains (e.g., *.surefeedback.com)
		foreach ( $this->allowed_origins as $allowed ) {
			if ( $this->matchesWildcardOrigin( $origin, $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if origin matches wildcard pattern
	 *
	 * @param string $origin Request origin
	 * @param string $pattern Allowed origin pattern
	 * @return bool
	 */
	protected function matchesWildcardOrigin( string $origin, string $pattern ): bool {
		// Convert wildcard pattern to regex
		$regex = str_replace( '*', '.*', preg_quote( $pattern, '/' ) );
		return preg_match( '/^' . $regex . '$/', $origin );
	}

	/**
	 * Set CORS headers
	 *
	 * @param string $origin Allowed origin
	 * @return void
	 */
	protected function setCorsHeaders( string $origin ): void {
		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Vary: Origin' );
	}

	/**
	 * Set preflight headers for OPTIONS requests
	 *
	 * @return void
	 */
	protected function setPreflightHeaders(): void {
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-Requested-With' );
		header( 'Access-Control-Max-Age: 86400' ); // Cache preflight for 24 hours
	}

	/**
	 * Add allowed origin
	 *
	 * @param string $origin Origin to add
	 * @return void
	 */
	public function addAllowedOrigin( string $origin ): void {
		if ( ! in_array( $origin, $this->allowed_origins, true ) ) {
			$this->allowed_origins[] = $origin;
		}
	}

	/**
	 * Remove allowed origin
	 *
	 * @param string $origin Origin to remove
	 * @return void
	 */
	public function removeAllowedOrigin( string $origin ): void {
		$key = array_search( $origin, $this->allowed_origins, true );
		if ( $key !== false ) {
			unset( $this->allowed_origins[ $key ] );
			$this->allowed_origins = array_values( $this->allowed_origins );
		}
	}

	/**
	 * Get all allowed origins
	 *
	 * @return array
	 */
	public function getAllowedOriginsList(): array {
		return $this->allowed_origins;
	}
}