<?php

namespace SureFeedback\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_Error;

/**
 * Authentication Middleware
 *
 * Handles authentication and authorization for API requests.
 *
 * @package SureFeedback\App\Http\Middleware
 * @author Anurag Singh <anurags@bsf.io>
 */
class AuthMiddleware extends Middleware {

	/**
	 * Handle authentication middleware
	 *
	 * @param WP_REST_Request $request
	 * @param callable        $next
	 * @return mixed
	 */
	public function handle( WP_REST_Request $request, callable $next ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			$this->log(
				'auth_failed',
				'User not logged in',
				array(
					'ip'       => $this->getClientIp( $request ),
					'endpoint' => $request->get_route(),
				)
			);

			return $this->error(
				'Authentication required. Please log in to access this resource.',
				401
			);
		}

		// Check user capabilities
		if ( ! $this->hasRequiredCapabilities( $request ) ) {
			$this->log(
				'auth_insufficient_permissions',
				'User lacks required permissions',
				array(
					'user_id'  => $this->getCurrentUserId(),
					'endpoint' => $request->get_route(),
				)
			);

			return $this->error(
				'Insufficient permissions to access this resource.',
				403
			);
		}

		// Verify nonce for state-changing operations
		if ( $this->requiresNonceVerification( $request ) ) {
			if ( ! $this->verifyNonce( $request ) ) {
				$this->log(
					'auth_invalid_nonce',
					'Invalid or missing nonce',
					array(
						'user_id'  => $this->getCurrentUserId(),
						'endpoint' => $request->get_route(),
					)
				);

				return $this->error(
					'Invalid security token. Please refresh the page and try again.',
					403
				);
			}
		}

		$this->log(
			'auth_success',
			'Authentication successful',
			array(
				'user_id'  => $this->getCurrentUserId(),
				'endpoint' => $request->get_route(),
			)
		);

		return $next( $request );
	}

	/**
	 * Check if user has required capabilities
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	protected function hasRequiredCapabilities( WP_REST_Request $request ): bool {
		$route  = $request->get_route();
		$method = $request->get_method();

		// Define capability requirements for different endpoints
		$requirements = array(
			// Connection endpoints
			'/surefeedback/v1/connection/status'  => array( 'read' ),
			'/surefeedback/v1/connection/connect' => array( 'manage_options' ),
			'/surefeedback/v1/connection/verify'  => array( 'manage_options' ),

			// Settings
			'/surefeedback/v1/settings'           => $method === 'GET' ? array( 'read' ) : array( 'manage_options' ),
			'/surefeedback/v1/settings/general'   => array( 'manage_options' ),

			// Admin endpoints
			'/surefeedback/v1/admin/verify'       => array( 'manage_options' ),
		);

		$requiredCaps = $requirements[ $route ] ?? array( 'manage_options' );

		foreach ( $requiredCaps as $cap ) {
			if ( ! $this->userCan( $cap ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if request requires nonce verification
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	protected function requiresNonceVerification( WP_REST_Request $request ): bool {
		$method = $request->get_method();

		// Only require nonce for state-changing operations
		return in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ) );
	}

	/**
	 * Verify nonce from request
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	protected function verifyNonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) ) {
			return false;
		}

		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Get user roles
	 *
	 * @param int|null $userId
	 * @return array
	 */
	protected function getUserRoles( ?int $userId = null ): array {
		$user = $userId ? get_user_by( 'id', $userId ) : wp_get_current_user();

		return $user ? $user->roles : array();
	}

	/**
	 * Check if user has any of the specified roles
	 *
	 * @param array    $roles
	 * @param int|null $userId
	 * @return bool
	 */
	protected function hasAnyRole( array $roles, ?int $userId = null ): bool {
		$userRoles = $this->getUserRoles( $userId );

		return ! empty( array_intersect( $roles, $userRoles ) );
	}

	/**
	 * Check if user is admin
	 *
	 * @param int|null $userId
	 * @return bool
	 */
	protected function isAdmin( ?int $userId = null ): bool {
		return $this->userCan( 'manage_options', $userId );
	}
}
