<?php

namespace SureFeedback\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Security Service
 *
 * Handles all security-related functionality including token generation,
 * signature verification, input validation, and access control.
 *
 * @package SureFeedback\App\Services
 * @author Anurag Singh <anurags@bsf.io>
 */
class SecurityService {

	/**
	 * Default token length
	 */
	const DEFAULT_TOKEN_LENGTH = 32;

	/**
	 * Default hash algorithm
	 */
	const DEFAULT_HASH_ALGO = 'sha256';

	/**
	 * Rate limit prefix
	 */
	const RATE_LIMIT_PREFIX = 'surefeedback_rate_';

	/**
	 * Constructor - Initialize security measures
	 */
	public function __construct() {
		$this->initSecurityHeaders();
		$this->initSecurityLogging();
	}

	/**
	 * Initialize security headers
	 *
	 * @return void
	 */
	protected function initSecurityHeaders(): void {
		// Apply security headers early
		add_action( 'init', array( $this, 'applySecurityHeaders' ), 1 );

		// Apply security headers for admin pages
		add_action( 'admin_init', array( $this, 'applySecurityHeaders' ), 1 );

		// Apply security headers for REST API responses
		add_filter( 'rest_pre_serve_request', array( $this, 'applySecurityHeadersToRestApi' ), 10, 4 );
	}

	/**
	 * Apply security headers to REST API responses
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  Server instance.
	 * @return bool
	 */
	public function applySecurityHeadersToRestApi( $served, $result, $request, $server ) {
		$this->applySecurityHeaders();
		return $served;
	}

	/**
	 * Initialize comprehensive security logging
	 *
	 * @return void
	 */
	protected function initSecurityLogging(): void {
		// Log authentication failures
		add_action( 'wp_login_failed', array( $this, 'logFailedLogin' ) );

		// Log successful logins
		add_action( 'wp_login', array( $this, 'logSuccessfulLogin' ), 10, 2 );

		// Log REST API authentication failures
		add_filter( 'rest_authentication_errors', array( $this, 'logRestAuthFailure' ), 100, 1 );

		// Log permission denials
		add_action( 'rest_request_after_callbacks', array( $this, 'logPermissionDenials' ), 10, 3 );
	}

	/**
	 * Log failed login attempts
	 *
	 * @param string $username Username used in failed login
	 * @return void
	 */
	public function logFailedLogin( string $username ): void {
		$this->logSecurityEvent(
			'login_failed',
			array(
				'username'   => sanitize_user( $username ),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'referer'    => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			)
		);
	}

	/**
	 * Log successful login attempts
	 *
	 * @param string  $user_login Username
	 * @param WP_User $user       User object
	 * @return void
	 */
	public function logSuccessfulLogin( string $user_login, $user ): void {
		$this->logSecurityEvent(
			'login_success',
			array(
				'user_id'    => $user->ID,
				'username'   => $user->user_login,
				'user_role'  => implode( ', ', $user->roles ),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);
	}

	/**
	 * Log REST API authentication failures
	 *
	 * @param mixed $result Current authentication result
	 * @return mixed
	 */
	public function logRestAuthFailure( $result ) {
		if ( is_wp_error( $result ) ) {
			$this->logSecurityEvent(
				'rest_auth_failed',
				array(
					'error_code'     => $result->get_error_code(),
					'error_message'  => $result->get_error_message(),
					'request_uri'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
					'request_method' => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
				)
			);
		}

		return $result;
	}

	/**
	 * Log permission denials from REST API responses
	 *
	 * @param WP_REST_Response $response Response object
	 * @param array            $handler  Route handler array
	 * @param WP_REST_Request  $request  Request object
	 * @return void
	 */
	public function logPermissionDenials( $response, $handler, $request ): void {
		if ( $response instanceof \WP_Error ) {
			$error_codes = array( 'rest_forbidden', 'rest_unauthorized', 'rest_unauthenticated' );

			if ( in_array( $response->get_error_code(), $error_codes, true ) ) {
				$this->logSecurityEvent(
					'permission_denied',
					array(
						'error_code'    => $response->get_error_code(),
						'error_message' => $response->get_error_message(),
						'route'         => $request->get_route(),
						'method'        => $request->get_method(),
					)
				);
			}
		} elseif ( $response instanceof \WP_REST_Response && $response->get_status() >= 400 ) {
			$this->logSecurityEvent(
				'http_error',
				array(
					'status_code' => $response->get_status(),
					'route'       => $request->get_route(),
					'method'      => $request->get_method(),
				)
			);
		}
	}

	/**
	 * Generate secure random token
	 *
	 * @param int $length Token length
	 * @return string
	 */
	public function generateToken( int $length = self::DEFAULT_TOKEN_LENGTH ): string {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return bin2hex( random_bytes( $length / 2 ) );
			} catch ( \Exception $e ) {
				// Fall through to next method
			}
		}

		if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$bytes = openssl_random_pseudo_bytes( $length / 2, $crypto_strong );
			if ( $crypto_strong ) {
				return bin2hex( $bytes );
			}
		}

		// Fallback to WordPress function
		return wp_generate_password( $length, false );
	}

	/**
	 * Generate HMAC signature
	 *
	 * @param string $data Data to sign
	 * @param string $secret Secret key
	 * @param string $algo Hash algorithm
	 * @return string
	 */
	public function generateSignature( string $data, string $secret, string $algo = self::DEFAULT_HASH_ALGO ): string {
		return hash_hmac( $algo, $data, $secret );
	}

	/**
	 * Verify HMAC signature
	 *
	 * @param string $data Original data
	 * @param string $signature Signature to verify
	 * @param string $secret Secret key
	 * @param string $algo Hash algorithm
	 * @return bool
	 */
	public function verifySignature( string $data, string $signature, string $secret, string $algo = self::DEFAULT_HASH_ALGO ): bool {
		$expected = $this->generateSignature( $data, $secret, $algo );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Verify webhook signature with timestamp validation
	 *
	 * @param string $payload Raw webhook payload
	 * @param string $signature Received signature (with sha256= prefix)
	 * @param string $timestamp Webhook timestamp
	 * @param string $secret Webhook signing secret
	 * @param int $tolerance Timestamp tolerance in seconds (default 300 = 5 minutes)
	 * @return bool
	 */
	public function verifyWebhookSignature( string $payload, string $signature, string $timestamp, string $secret, int $tolerance = 300 ): bool {
		// Validate timestamp to prevent replay attacks
		$current_time = time();
		$webhook_time = (int) $timestamp;

		if ( abs( $current_time - $webhook_time ) > $tolerance ) {
			return false;
		}

		// Remove sha256= prefix if present
		if ( str_starts_with( $signature, 'sha256=' ) ) {
			$signature = substr( $signature, 7 );
		}

		// Generate expected signature
		$expected = $this->generateSignature( $payload, $secret, 'sha256' );

		// Compare signatures using timing-safe comparison
		return hash_equals( $expected, $signature );
	}

	/**
	 * Get webhook signing secret (shared with Laravel API)
	 *
	 * @return string
	 */
	public function getWebhookSigningSecret(): string {
		// Try to get the secret from WordPress options first
		$stored_secret = get_option( 'surefeedback_webhook_signing_secret' );

		if ( ! empty( $stored_secret ) ) {
			return $stored_secret;
		}

		// If not stored, we need to get it from the Laravel API during connection
		// For now, return a default that will be updated during webhook setup
		$default_secret = get_option( 'surefeedback_access_token', '' );

		if ( empty( $default_secret ) ) {
			// Generate a temporary secret if nothing is available
			$default_secret = wp_generate_password( 64, false );
		}

		return $default_secret;
	}

	/**
	 * Store webhook signing secret received from Laravel API
	 *
	 * @param string $secret The webhook signing secret
	 * @return bool
	 */
	public function storeWebhookSigningSecret( string $secret ): bool {
		return update_option( 'surefeedback_webhook_signing_secret', sanitize_text_field( $secret ) );
	}

	/**
	 * Sanitize input data recursively
	 *
	 * @param mixed $data Input data
	 * @return mixed
	 */
	public function sanitizeInput( $data ) {
		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		if ( is_array( $data ) ) {
			return array_map( array( $this, 'sanitizeInput' ), $data );
		}

		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = $this->sanitizeInput( $value );
			}
			return $data;
		}

		return $data;
	}

	/**
	 * Validate nonce
	 *
	 * @param string $nonce Nonce value
	 * @param string $action Nonce action
	 * @return bool
	 */
	public function verifyNonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Check user capabilities
	 *
	 * @param string|array $capability Required capability/capabilities
	 * @param int          $user_id User ID (default: current user)
	 * @return bool
	 */
	public function userCan( $capability, int $user_id = 0 ): bool {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( is_array( $capability ) ) {
			foreach ( $capability as $cap ) {
				if ( ! user_can( $user_id, $cap ) ) {
					return false;
				}
			}
			return true;
		}

		return user_can( $user_id, $capability );
	}

	/**
	 * Rate limiting check
	 *
	 * @param string $key Rate limit key
	 * @param int    $limit Request limit
	 * @param int    $window Time window in seconds
	 * @param string $prefix Cache prefix
	 * @return bool
	 */
	public function checkRateLimit( string $key, int $limit, int $window = 3600, string $prefix = self::RATE_LIMIT_PREFIX ): bool {
		// Use WordPress options for persistent storage instead of transients
		$cache_key = $prefix . md5( $key );
		$rate_data = get_option( $cache_key, array() );

		$now          = time();
		$window_start = $now - $window;

		// Clean old entries
		if ( isset( $rate_data['attempts'] ) ) {
			$rate_data['attempts'] = array_filter(
				$rate_data['attempts'],
				function ( $timestamp ) use ( $window_start ) {
					return $timestamp > $window_start;
				}
			);
		} else {
			$rate_data['attempts'] = array();
		}

		// Check if limit exceeded
		if ( count( $rate_data['attempts'] ) >= $limit ) {
			return false;
		}

		// Add current attempt
		$rate_data['attempts'][]  = $now;
		$rate_data['last_update'] = $now;

		// Store updated data
		update_option( $cache_key, $rate_data );

		// Schedule cleanup of old rate limit data
		if ( ! wp_next_scheduled( 'surefeedback_cleanup_rate_limits' ) ) {
			wp_schedule_event( time(), 'hourly', 'surefeedback_cleanup_rate_limits' );
		}

		return true;
	}

	/**
	 * Escape output data
	 *
	 * @param mixed  $data Data to escape
	 * @param string $context Escape context
	 * @return mixed
	 */
	public function escapeOutput( $data, string $context = 'html' ) {
		if ( is_string( $data ) ) {
			switch ( $context ) {
				case 'url':
					return esc_url( $data );
				case 'attr':
					return esc_attr( $data );
				case 'js':
					return esc_js( $data );
				case 'textarea':
					return esc_textarea( $data );
				case 'html':
				default:
					return esc_html( $data );
			}
		}

		if ( is_array( $data ) ) {
			return array_map(
				function ( $item ) use ( $context ) {
					return $this->escapeOutput( $item, $context );
				},
				$data
			);
		}

		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = $this->escapeOutput( $value, $context );
			}
			return $data;
		}

		return $data;
	}

	/**
	 * Validate URL
	 *
	 * @param string $url URL to validate
	 * @param array  $allowed_schemes Allowed URL schemes
	 * @return bool
	 */
	public function validateUrl( string $url, array $allowed_schemes = array( 'http', 'https' ) ): bool {
		$parsed = wp_parse_url( $url );

		if ( ! $parsed || ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) ) {
			return false;
		}

		return in_array( $parsed['scheme'], $allowed_schemes, true );
	}

	/**
	 * Validate email address
	 *
	 * @param string $email Email to validate
	 * @return bool
	 */
	public function validateEmail( string $email ): bool {
		return is_email( $email ) !== false;
	}

	/**
	 * Validate token format
	 *
	 * @param string $token Token to validate
	 * @param int    $min_length Minimum token length
	 * @return bool
	 */
	public function validateToken( string $token, int $min_length = 16 ): bool {
		return strlen( $token ) >= $min_length &&
				preg_match( '/^[a-zA-Z0-9]+$/', $token );
	}

	/**
	 * Check if current request is from admin area
	 *
	 * @return bool
	 */
	public function isAdminRequest(): bool {
		return is_admin() && ! wp_doing_ajax() && ! wp_doing_cron();
	}

	/**
	 * Check if current request is AJAX
	 *
	 * @return bool
	 */
	public function isAjaxRequest(): bool {
		return wp_doing_ajax();
	}

	/**
	 * Check if current request is REST API
	 *
	 * @return bool
	 */
	public function isRestRequest(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Check if current request is frontend
	 *
	 * @return bool
	 */
	public function isFrontendRequest(): bool {
		return ! $this->isAdminRequest() && ! $this->isAjaxRequest() && ! $this->isRestRequest();
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	public function getClientIp(): string {
		// Use only REMOTE_ADDR for security - no proxy headers
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			// Validate IP format
			if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
				return $remote_addr;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Generate API key
	 *
	 * @return string
	 */
	public function generateApiKey(): string {
		return 'sf_' . $this->generateToken( 40 );
	}

	/**
	 * Validate API key format
	 *
	 * @param string $api_key API key to validate
	 * @return bool
	 */
	public function validateApiKey( string $api_key ): bool {
		return preg_match( '/^sf_[a-zA-Z0-9]{40}$/', $api_key );
	}

	/**
	 * Hash password using WordPress standards
	 *
	 * @param string $password Plain password
	 * @return string
	 */
	public function hashPassword( string $password ): string {
		return wp_hash_password( $password );
	}

	/**
	 * Verify password against hash
	 *
	 * @param string $password Plain password
	 * @param string $hash Password hash
	 * @return bool
	 */
	public function verifyPassword( string $password, string $hash ): bool {
		return wp_check_password( $password, $hash );
	}

	/**
	 * Generate secure filename
	 *
	 * @param string $filename Original filename
	 * @return string
	 */
	public function generateSecureFilename( string $filename ): string {
		$info      = pathinfo( $filename );
		$extension = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
		$basename  = sanitize_file_name( $info['filename'] );

		return $basename . '_' . $this->generateToken( 8 ) . $extension;
	}

	/**
	 * Check if file type is allowed
	 *
	 * @param string $filename Filename to check
	 * @param array  $allowed_types Allowed file types
	 * @return bool
	 */
	public function isAllowedFileType( string $filename, array $allowed_types = array() ): bool {
		if ( empty( $allowed_types ) ) {
			$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt' );
		}

		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return in_array( $extension, $allowed_types, true );
	}

	/**
	 * Sanitize and validate webhook URL
	 *
	 * @param string $url Webhook URL
	 * @return string|false
	 */
	public function validateWebhookUrl( string $url ) {
		$url = sanitize_url( $url );

		if ( ! $this->validateUrl( $url ) ) {
			return false;
		}

		$parsed = wp_parse_url( $url );

		// Security: Only allow HTTPS for webhooks
		if ( $parsed['scheme'] !== 'https' ) {
			return false;
		}

		// Security: Validate hostname and prevent SSRF attacks
		if ( isset( $parsed['host'] ) ) {
			$host = $parsed['host'];

			// Validate domain format
			if ( ! filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
				return false;
			}

			// Block internal/private IP addresses to prevent SSRF
			if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
				// Block private IP ranges and localhost
				if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return false;
				}

				// Additional check for cloud metadata endpoints
				if ( $host === '169.254.169.254' ) {
					return false;
				}
			}

			// Block localhost and local domains
			$blocked_hosts = array(
				'localhost',
				'127.0.0.1',
				'::1',
				'0.0.0.0',
				'[::1]',
			);

			if ( in_array( strtolower( $host ), $blocked_hosts, true ) ) {
				return false;
			}

			// Block common local TLDs
			$blocked_tlds = array( '.local', '.localhost', '.test', '.invalid' );
			foreach ( $blocked_tlds as $tld ) {
				if ( substr( $host, -strlen( $tld ) ) === $tld ) {
					return false;
				}
			}
		}

		return $url;
	}

	/**
	 * Create security headers for responses
	 *
	 * @return array
	 */
	public function getSecurityHeaders(): array {
		$headers = array(
			'X-Content-Type-Options'            => 'nosniff',
			'X-Frame-Options'                   => 'SAMEORIGIN',
			'X-XSS-Protection'                  => '1; mode=block',
			'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
			'X-Permitted-Cross-Domain-Policies' => 'none',
			'Permissions-Policy'                => 'camera=(), microphone=(), geolocation=(), payment=()',
		);

		// Add HSTS header for HTTPS sites
		if ( is_ssl() ) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
		}

		// Enhanced CSP for SureFeedback
		$app_url = defined( 'SUREFEEDBACK_APP_BASE_URL' ) ? SUREFEEDBACK_APP_BASE_URL : 'https://app.surefeedback.com';
		$api_url = defined( 'SUREFEEDBACK_API_BASE_URL' ) ? SUREFEEDBACK_API_BASE_URL : 'https://api.surefeedback.com';

		$csp_parts = array(
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline' " . esc_url( $app_url ) . ' ' . esc_url( $api_url ),
			"style-src 'self' 'unsafe-inline' " . esc_url( $app_url ),
			"img-src 'self' data: " . esc_url( $app_url ) . ' ' . esc_url( $api_url ),
			"font-src 'self' " . esc_url( $app_url ),
			"connect-src 'self' " . esc_url( $api_url ) . ' ' . esc_url( $app_url ),
			"frame-src 'none'",
			"object-src 'none'",
			"base-uri 'self'",
			"form-action 'self'",
		);

		$headers['Content-Security-Policy'] = implode( '; ', $csp_parts );

		/**
		 * Filter security headers
		 *
		 * @param array $headers Security headers
		 */
		return apply_filters( 'surefeedback_security_headers', $headers );
	}

	/**
	 * Apply security headers to the current response
	 *
	 * @return void
	 */
	public function applySecurityHeaders(): void {
		$headers = $this->getSecurityHeaders();

		foreach ( $headers as $name => $value ) {
			if ( ! headers_sent() ) {
				header( $name . ': ' . $value );
			}
		}
	}

	/**
	 * Log security event
	 *
	 * @param string $event Event type
	 * @param array  $data Event data
	 * @return void
	 */
	public function logSecurityEvent( string $event, array $data = array() ): void {
		$log_data = array(
			'event'     => $event,
			'timestamp' => current_time( 'mysql' ),
			'ip'        => $this->getClientIp(),
			'user_id'   => get_current_user_id(),
			'data'      => $data,
		);

		// Store in database for audit trail
		$this->storeSecurityLog( $log_data );
	}

	/**
	 * Store security log in database
	 *
	 * @param array $log_data Log data
	 * @return void
	 */
	private function storeSecurityLog( array $log_data ): void {
		// Store recent security events in transient for quick access
		$recent_events = get_transient( 'surefeedback_security_events' ) ?: array();

		// Keep only last 100 events
		if ( count( $recent_events ) >= 100 ) {
			$recent_events = array_slice( $recent_events, -99 );
		}

		$recent_events[] = $log_data;
		set_transient( 'surefeedback_security_events', $recent_events, DAY_IN_SECONDS );
	}

	/**
	 * Get recent security events
	 *
	 * @param int $limit Number of events to retrieve
	 * @return array
	 */
	public function getRecentSecurityEvents( int $limit = 50 ): array {
		$events = get_transient( 'surefeedback_security_events' ) ?: array();
		return array_slice( $events, -$limit );
	}

	/**
	 * Clear security logs
	 *
	 * @return void
	 */
	public function clearSecurityLogs(): void {
		delete_transient( 'surefeedback_security_events' );
	}

	/**
	 * Cleanup old rate limit data
	 *
	 * @return void
	 */
	public function cleanupRateLimits(): void {
		$now    = time();
		$cutoff = $now - ( 24 * 3600 ); // 24 hours ago

		// Get all options with rate limit prefix using WordPress API
		$all_options = wp_load_alloptions();

		foreach ( $all_options as $option_name => $option_value ) {
			// Check if this is a rate limit option
			if ( strpos( $option_name, self::RATE_LIMIT_PREFIX ) === 0 ) {
				$data = maybe_unserialize( $option_value );

				if ( is_array( $data ) && isset( $data['last_update'] ) ) {
					// Delete old rate limit data
					if ( $data['last_update'] < $cutoff ) {
						delete_option( $option_name );
					}
				}
			}
		}
	}
}
