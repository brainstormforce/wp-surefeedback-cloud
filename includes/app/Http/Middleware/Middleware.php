<?php

namespace SureFeedback\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_Error;

/**
 * Base Middleware Class
 *
 * Provides base functionality for request middleware processing.
 *
 * @package SureFeedback\App\Http\Middleware
 * @author Anurag Singh <anurags@bsf.io>
 */
abstract class Middleware {

	/**
	 * Handle the middleware
	 *
	 * @param WP_REST_Request $request
	 * @param callable        $next
	 * @return mixed
	 */
	abstract public function handle( WP_REST_Request $request, callable $next );

	/**
	 * Create error response
	 *
	 * @param string $message
	 * @param int    $code
	 * @param array  $data
	 * @return WP_Error
	 */
	protected function error( string $message, int $code = 400, array $data = array() ): WP_Error {
		return new WP_Error( 'middleware_error', $message, array_merge( array( 'status' => $code ), $data ) );
	}

	/**
	 * Check if user has required capability
	 *
	 * @param string   $capability
	 * @param int|null $userId
	 * @return bool
	 */
	protected function userCan( string $capability, ?int $userId = null ): bool {
		if ( $userId ) {
			return user_can( $userId, $capability );
		}

		return current_user_can( $capability );
	}

	/**
	 * Get current user ID
	 *
	 * @return int
	 */
	protected function getCurrentUserId(): int {
		return get_current_user_id();
	}

	/**
	 * Get request IP address
	 *
	 * @param WP_REST_Request $request
	 * @return string
	 */
	protected function getClientIp( WP_REST_Request $request ): string {
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

	/**
	 * Log middleware action
	 *
	 * @param string $action
	 * @param string $message
	 * @param array  $context
	 * @return void
	 */
	protected function log( string $action, string $message, array $context = array() ): void {
		// Logging disabled
	}
}
