<?php

/**
 * Authentication Manager class
 *
 * @package SureFeedback
 */

namespace SureFeedback;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authentication Manager class
 * Handles OAuth authentication with the SaaS platform
 */
class Auth_Manager {

	/**
	 * Option name for storing the bearer token
	 */
	const BEARER_TOKEN_OPTION = 'surefeedback_bearer_token';

	/**
	 * SaaS authentication URL
	 */
	const SAAS_AUTH_URL = SUREFEEDBACK_SAAS_BASE_URL . '/connect';

	/**
	 * SaaS token exchange URL
	 */
	const TOKEN_EXCHANGE_URL = SUREFEEDBACK_SAAS_API_BASE_URL . '/api/v1/connections/exchange';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
		// Don't check authentication on admin_init - let menu show first
		// Authentication check will happen when page is accessed
	}

	/**
	 * Check if user is authenticated
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_authenticated() {
		$token = $this->get_bearer_token();
		return ! empty( $token );
	}

	/**
	 * Get the stored bearer token
	 *
	 * @since 0.0.1
	 *
	 * @return string|false
	 */
	public function get_bearer_token() {
		// Try secure cookie first
		$secure_cookie_manager = Secure_Cookie_Manager::get_instance();
		$token                 = $secure_cookie_manager->get_auth_token();

		if ( $token ) {
			return $token;
		}

		// Fallback to database option
		$encrypted_token = get_option( self::BEARER_TOKEN_OPTION, false );
		if ( ! $encrypted_token ) {
			return false;
		}

		$encryption = new Encryption();
		return $encryption->decrypt( $encrypted_token );
	}

	/**
	 * Check if there's an authentication error
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function has_auth_error() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback, nonce not applicable for external redirects
		return isset( $_GET['auth_error'] ) && sanitize_text_field( wp_unslash( $_GET['auth_error'] ) ) === '1';
	}

	/**
	 * Store the bearer token
	 *
	 * @param string $token Bearer token.
	 * @return bool
	 */
	private function store_bearer_token( $token ) {
		// Store in secure cookie
		$secure_cookie_manager = Secure_Cookie_Manager::get_instance();
		$cookie_result         = $secure_cookie_manager->store_auth_token( $token, 30 * DAY_IN_SECONDS );

		// Also store in database as backup
		$encryption      = new Encryption();
		$encrypted_token = $encryption->encrypt( sanitize_text_field( $token ) );
		$db_result       = update_option( self::BEARER_TOKEN_OPTION, $encrypted_token );

		return $cookie_result && $db_result;
	}

	/**
	 * Get the OAuth callback URL
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_callback_url() {
		return admin_url( 'admin.php?page=surefeedback-dashboard' );
	}

	/**
	 * Get the authentication URL
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_auth_url() {
		$callback_url = $this->get_callback_url();
		return add_query_arg(
			array(
				'oauth_url' => urlencode( $callback_url ),
			),
			self::SAAS_AUTH_URL
		);
	}

	/**
	 * Handle OAuth callback
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function handle_oauth_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback from external SaaS, nonce not applicable

		// First check if we have oauth_token in the URL
		// Handle both proper format and malformed URLs
		$oauth_token = null;

		// Check standard $_GET parameter
		if ( isset( $_GET['oauth_token'] ) ) {
			$oauth_token = sanitize_text_field( wp_unslash( $_GET['oauth_token'] ) );
		} else {
			// Handle malformed URL with double question mark
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( $request_uri && preg_match( '/[?&]oauth_token=([^&]+)/', $request_uri, $matches ) ) {
				$oauth_token = sanitize_text_field( $matches[1] );
			}
		}

		// If no token found, return early
		if ( ! $oauth_token ) {
			return;
		}

		// Check if we're on our plugin page
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! $page ) {
			// Try to extract page from URL if not in $_GET
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( $request_uri && preg_match( '/page=([^&?]+)/', $request_uri, $matches ) ) {
				$page = sanitize_text_field( $matches[1] );
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $page || strpos( $page, 'surefeedback' ) !== 0 ) {
			return;
		}

		// Exchange token
		$result = $this->exchange_token( $oauth_token );

		if ( $result ) {
			// Redirect to remove oauth_token from URL
			wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
			exit;
		} else {
			// Redirect with error parameter
			wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&auth_error=1' ) );
			exit;
		}
	}

	/**
	 * Exchange OAuth token for bearer token
	 *
	 * @param string $oauth_token OAuth token from callback.
	 * @return bool
	 */
	private function exchange_token( $oauth_token ) {
		try {
			$body     = array(
				'oauth_token' => $oauth_token,
				'site_url'    => rest_url( 'surefeedback/v1/' ),
			);
			$response = wp_remote_post(
				self::TOKEN_EXCHANGE_URL,
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$body          = wp_remote_retrieve_body( $response );
			$result        = json_decode( $body, true );

			// Check HTTP status code
			if ( $response_code !== 200 && $response_code !== 201 ) {
				return false;
			}

			// Check if the response is successful and has the expected structure
			if ( ! empty( $result['success'] ) && $result['success'] === true && ! empty( $result['data'] ) ) {
				$data = $result['data'];
				// Store the access token as bearer token
				if ( ! empty( $data['access_token'] ) ) {
					$this->store_bearer_token( $data['access_token'] );

					// Also store the connection ID if needed
					if ( ! empty( $data['connection_id'] ) ) {
						update_option( 'surefeedback_connection_id', sanitize_text_field( $data['connection_id'] ) );
					}

					if ( ! empty( $data['site_id'] ) ) {
						update_option( 'surefeedback_site_id', sanitize_text_field( $data['site_id'] ) );
					}

					if ( ! empty( $data['organization_id'] ) ) {
						update_option( 'surefeedback_organization_id', sanitize_text_field( $data['organization_id'] ) );
					}

					// Store script token (site token) for verification
					if ( ! empty( $data['script_token'] ) ) {
						update_option( 'surefeedback_access_token', sanitize_text_field( $data['script_token'] ) );
					}

					return true;
				}
			}

			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check authentication on admin pages
	 * This is called when the page is actually loaded, not during menu registration
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function check_authentication() {
		// This method is no longer needed - menu should always be visible
		// Authentication will be checked when the page is rendered
	}

	/**
	 * Show authentication error
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function show_auth_error() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Authentication failed. Please try again.', 'surefeedback' ); ?></p>
		</div>
		<?php
	}
}
