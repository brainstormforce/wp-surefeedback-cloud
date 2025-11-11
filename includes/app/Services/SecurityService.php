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

		$now = time();
		$window_start = $now - $window;

		// Clean old entries
		if ( isset( $rate_data['attempts'] ) ) {
			$rate_data['attempts'] = array_filter( 
				$rate_data['attempts'], 
				function( $timestamp ) use ( $window_start ) {
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
		$rate_data['attempts'][] = $now;
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
				'[::1]'
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
		return array(
			'X-Content-Type-Options'  => 'nosniff',
			'X-Frame-Options'         => 'SAMEORIGIN',
			'X-XSS-Protection'        => '1; mode=block',
			'Referrer-Policy'         => 'strict-origin-when-cross-origin',
			'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self';",
		);
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
		$now = time();
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
