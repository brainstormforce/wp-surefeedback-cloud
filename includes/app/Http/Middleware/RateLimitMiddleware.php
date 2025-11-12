<?php

namespace SureFeedback\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_Error;
use SureFeedback\Repositories\RateLimitRepository;

/**
 * Rate Limiting Middleware
 *
 * Implements rate limiting to prevent abuse of API endpoints.
 *
 * @package SureFeedback\App\Http\Middleware
 * @author Anurag Singh <anurags@bsf.io>
 */
class RateLimitMiddleware extends Middleware {

	/**
	 * Maximum number of stored rate-limit option entries to keep.
	 * This prevents unbounded growth in the options table if many distinct
	 * client IDs are used. If exceeded, oldest entries will be evicted.
	 */
	const MAX_RATE_LIMIT_ENTRIES = 2000;

	/**
	 * Rate limit configurations for different endpoints
	 *
	 * @var array
	 */
	protected $rateLimits = array(
		// Connection endpoints - more restrictive due to external calls
		'/surefeedback/v1/connection/connect' => array(
			'limit'  => 5,
			'window' => 300,
		),    // 5 per 5 min
		'/surefeedback/v1/connection/verify'  => array(
			'limit'  => 10,
			'window' => 300,
		),    // 10 per 5 min
		'/surefeedback/v1/connection/test'    => array(
			'limit'  => 10,
			'window' => 300,
		),      // 10 per 5 min

		// Plugin management endpoints - very restrictive due to sensitive operations
		'/surefeedback/v1/plugin/activate'    => array(
			'limit'  => 3,
			'window' => 300,
		),        // 3 per 5 min

		// Settings endpoints
		'/surefeedback/v1/settings'           => array(
			'limit'  => 30,
			'window' => 300,
		),             // 30 per 5 min

		// Dashboard endpoints - less restrictive for read operations
		'/surefeedback/v1/dashboard/*'        => array(
			'limit'  => 100,
			'window' => 300,
		),         // 100 per 5 min

		// Default rate limit
		'default'                             => array(
			'limit'  => 60,
			'window' => 300,
		),                               // 60 per 5 min
	);

	/**
	 * Handle rate limiting middleware
	 *
	 * @param WP_REST_Request $request
	 * @param callable        $next
	 * @return mixed
	 */
	public function handle( WP_REST_Request $request, callable $next ) {
		$route    = $request->get_route();
		$clientId = $this->getClientIdentifier( $request );

		// Get rate limit configuration for this endpoint
		$config = $this->getRateLimitConfig( $route );

		// Check if rate limit is exceeded
		if ( $this->isRateLimitExceeded( $clientId, $route, $config ) ) {
			// Log rate limit violation to security service
			try {
				$security_service = new \SureFeedback\Services\SecurityService();
				$security_service->logSecurityEvent(
					'rate_limit_exceeded',
					array(
						'client_id'  => $clientId,
						'route'      => $route,
						'limit'      => $config['limit'],
						'window'     => $config['window'],
						'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					)
				);
			} catch ( \Exception $e ) {
				// Silently fail if security service is not available
			}

			$this->log(
				'rate_limit_exceeded',
				'Rate limit exceeded',
				array(
					'client_id' => $clientId,
					'route'     => $route,
					'limit'     => $config['limit'],
					'window'    => $config['window'],
				)
			);

			return $this->error(
				sprintf(
					'Rate limit exceeded. Maximum %d requests per %d seconds allowed.',
					$config['limit'],
					$config['window']
				),
				429,
				array(
					'retry_after' => $this->getRetryAfter( $clientId, $route, $config ),
					'limit'       => $config['limit'],
					'window'      => $config['window'],
				)
			);
		}

		// Record this request
		$this->recordRequest( $clientId, $route, $config );

		return $next( $request );
	}

	/**
	 * Get client identifier for rate limiting
	 *
	 * @param WP_REST_Request $request
	 * @return string
	 */
	protected function getClientIdentifier( WP_REST_Request $request ): string {
		// Use user ID if logged in, otherwise use IP address
		$userId = $this->getCurrentUserId();

		if ( $userId > 0 ) {
			return 'user_' . $userId;
		}

		return 'ip_' . $this->getClientIp( $request );
	}

	/**
	 * Get rate limit configuration for a route
	 *
	 * @param string $route
	 * @return array
	 */
	protected function getRateLimitConfig( string $route ): array {
		// Check for exact match first
		if ( isset( $this->rateLimits[ $route ] ) ) {
			return $this->rateLimits[ $route ];
		}

		// Check for wildcard matches
		foreach ( $this->rateLimits as $pattern => $config ) {
			if ( strpos( $pattern, '*' ) !== false ) {
				$regex = str_replace( '*', '.*', preg_quote( $pattern, '/' ) );
				if ( preg_match( '/^' . $regex . '$/', $route ) ) {
					return $config;
				}
			}
		}

		// Return default configuration
		return $this->rateLimits['default'];
	}

	/**
	 * Check if rate limit is exceeded (persistent storage)
	 *
	 * @param string $clientId
	 * @param string $route
	 * @param array  $config
	 * @return bool
	 */
	protected function isRateLimitExceeded( string $clientId, string $route, array $config ): bool {
		$key      = 'surefeedback_rate_limit_' . md5( $clientId . '_' . $route );
		$requests = get_option( $key, array() );

		if ( ! is_array( $requests ) || empty( $requests ) ) {
			return false; // No previous requests recorded
		}

		// Remove expired requests
		$cutoff   = time() - $config['window'];
		$requests = array_filter(
			$requests,
			function ( $timestamp ) use ( $cutoff ) {
				return $timestamp > $cutoff;
			}
		);

		return count( $requests ) >= $config['limit'];
	}

	/**
	 * Record a request for rate limiting (persistent storage)
	 *
	 * @param string $clientId
	 * @param string $route
	 * @param array  $config
	 * @return void
	 */
	protected function recordRequest( string $clientId, string $route, array $config ): void {
		$key      = 'surefeedback_rate_limit_' . md5( $clientId . '_' . $route );
		$requests = get_option( $key, array() );

		if ( ! is_array( $requests ) ) {
			$requests = array();
		}

		// Add current timestamp
		$requests[] = time();

		// Remove old requests outside the window
		$cutoff   = time() - $config['window'];
		$requests = array_filter(
			$requests,
			function ( $timestamp ) use ( $cutoff ) {
				return $timestamp > $cutoff;
			}
		);

		// Store updated requests list using WordPress options for persistence
		update_option( $key, array_values( $requests ), 'no' ); // no autoload for performance

		// Schedule cleanup of old rate limit entries
		if ( ! wp_next_scheduled( 'surefeedback_cleanup_rate_limits' ) ) {
			wp_schedule_event( time() + 3600, 'hourly', 'surefeedback_cleanup_rate_limits' );
		}
	}

	/**
	 * Get retry after seconds
	 *
	 * @param string $clientId
	 * @param string $route
	 * @param array  $config
	 * @return int
	 */
	protected function getRetryAfter( string $clientId, string $route, array $config ): int {
		$key      = $this->getTransientKey( $clientId, $route );
		$requests = get_transient( $key );

		if ( $requests === false || empty( $requests ) ) {
			return 0;
		}

		// Get the oldest request in the current window
		$oldestRequest = min( $requests );
		$retryAfter    = ( $oldestRequest + $config['window'] ) - time();

		return max( 0, $retryAfter );
	}

	/**
	 * Get transient key for rate limiting
	 *
	 * @param string $clientId
	 * @param string $route
	 * @return string
	 */
	protected function getTransientKey( string $clientId, string $route ): string {
		return 'surefeedback_rate_limit_' . md5( $clientId . '_' . $route );
	}

	/**
	 * Reset rate limit for a client and route (persistent storage)
	 *
	 * @param string $clientId
	 * @param string $route
	 * @return bool
	 */
	public function resetRateLimit( string $clientId, string $route ): bool {
		$key = 'surefeedback_rate_limit_' . md5( $clientId . '_' . $route );
		return delete_option( $key );
	}

	/**
	 * Cleanup expired rate limit entries (scheduled task)
	 *
	 * @return void
	 */
	public static function cleanupExpiredRateLimits(): void {
		$repository = new RateLimitRepository();

		// Remove rate limit options older than 1 day
		$cutoff = time() - DAY_IN_SECONDS;

		// Clean up expired entries using repository
		$cleaned_count = $repository->cleanupExpiredRateLimits( $cutoff );

		// Enforce a global cap on stored rate-limit entries to avoid unbounded growth
		$total_entries = $repository->getRateLimitEntriesCount();
		if ( $total_entries > self::MAX_RATE_LIMIT_ENTRIES ) {
			$repository->enforceGlobalCap( self::MAX_RATE_LIMIT_ENTRIES );
		}
	}

	/**
	 * Get current rate limit status for a client
	 *
	 * @param string $clientId
	 * @param string $route
	 * @return array
	 */
	public function getRateLimitStatus( string $clientId, string $route ): array {
		$config   = $this->getRateLimitConfig( $route );
		$key      = 'surefeedback_rate_limit_' . md5( $clientId . '_' . $route );
		$requests = get_option( $key, array() );

		if ( ! is_array( $requests ) ) {
			$requests = array();
		}

		// Remove expired requests
		$cutoff   = time() - $config['window'];
		$requests = array_filter(
			$requests,
			function ( $timestamp ) use ( $cutoff ) {
				return $timestamp > $cutoff;
			}
		);

		$remaining  = max( 0, $config['limit'] - count( $requests ) );
		$retryAfter = $this->getRetryAfter( $clientId, $route, $config );

		return array(
			'limit'       => $config['limit'],
			'remaining'   => $remaining,
			'window'      => $config['window'],
			'retry_after' => $retryAfter,
			'exceeded'    => $remaining === 0,
		);
	}

	/**
	 * Static method to check rate limit for a specific route
	 *
	 * @param string $route The route to check
	 * @param WP_REST_Request $request The request object
	 * @return WP_Error|null Returns WP_Error if rate limited, null if allowed
	 */
	public static function checkRateLimit( string $route, $request ) {
		$middleware = new self();

		// Get client identifier
		$userId   = get_current_user_id();
		$clientId = $userId > 0 ? 'user_' . $userId : 'ip_' . $middleware->getClientIp( $request );

		// Get rate limit configuration
		$config = $middleware->getRateLimitConfig( $route );

		// Check if rate limit is exceeded
		if ( $middleware->isRateLimitExceeded( $clientId, $route, $config ) ) {
			// Log rate limit violation to security service
			try {
				$security_service = new \SureFeedback\Services\SecurityService();
				$security_service->logSecurityEvent(
					'rate_limit_exceeded',
					array(
						'client_id'  => $clientId,
						'route'      => $route,
						'limit'      => $config['limit'],
						'window'     => $config['window'],
						'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					)
				);
			} catch ( \Exception $e ) {
				// Silently fail if security service is not available
			}

			return new \WP_Error(
				'rest_rate_limit_exceeded',
				sprintf(
					'Rate limit exceeded. Maximum %d requests per %d seconds allowed.',
					$config['limit'],
					$config['window']
				),
				array(
					'status'      => 429,
					'retry_after' => $middleware->getRetryAfter( $clientId, $route, $config ),
					'limit'       => $config['limit'],
					'window'      => $config['window'],
				)
			);
		}

		// Record this request
		$middleware->recordRequest( $clientId, $route, $config );

		return null;
	}
}
