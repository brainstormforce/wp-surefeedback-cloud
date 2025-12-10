<?php
/**
 * Authentication Manager class
 *
 * @package SureFeedback
 */

namespace SureFeedback;

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
	const SAAS_AUTH_URL = SUREFEEDBACK_APP_BASE_URL . '/connect';

	/**
	 * SaaS token exchange URL
	 */
	const TOKEN_EXCHANGE_URL = SUREFEEDBACK_API_BASE_URL . '/api/v1/connections/exchange';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
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

		if ( ! empty( $token ) ) {
			return true;
		}

		$site_id         = get_option( 'surefeedback_site_id', '' );
		$site_token      = get_option( 'surefeedback_site_token', '' );
		$organization_id = get_option( 'surefeedback_organization_id', '' );

		if ( ! empty( $site_id ) && ! empty( $site_token ) ) {
			$db_token = get_option( self::BEARER_TOKEN_OPTION, false );

			if ( $db_token && ! empty( $db_token ) ) {
				if ( strpos( $db_token, 'eyJ' ) === 0 ) {
					$this->store_bearer_token( $db_token );
					return true;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Get the stored bearer token
	 *
	 * @since 0.0.1
	 *
	 * @return string|false
	 */
	public function get_bearer_token() {
		$secure_cookie_manager = Secure_Cookie_Manager::get_instance();
		$token                 = $secure_cookie_manager->get_auth_token();

		if ( $token ) {
			return $token;
		}

		$stored_token = get_option( self::BEARER_TOKEN_OPTION, false );
		if ( ! $stored_token ) {
			return false;
		}

		if ( is_string( $stored_token ) && strpos( $stored_token, 'eyJ' ) === 0 ) {
			$encryption = new Encryption();
			$encrypted  = $encryption->encrypt( $stored_token );
			if ( $encrypted ) {
				update_option( self::BEARER_TOKEN_OPTION, $encrypted );
			}
			return $stored_token;
		}

		$encryption = new Encryption();
		$decrypted  = $encryption->decrypt( $stored_token );

		if ( ! $decrypted && is_string( $stored_token ) && strpos( $stored_token, 'eyJ' ) === 0 ) {
			return $stored_token;
		}

		return $decrypted;
	}

	/**
	 * Store the bearer token
	 *
	 * @param string $token Bearer token.
	 * @return bool
	 */
	public function store_bearer_token( $token ) {
		$secure_cookie_manager = Secure_Cookie_Manager::get_instance();
		$cookie_result         = $secure_cookie_manager->store_auth_token( $token, 30 * DAY_IN_SECONDS );

		$encryption      = new Encryption();
		$encrypted_token = $encryption->encrypt( sanitize_text_field( $token ) );
		$db_result       = update_option( self::BEARER_TOKEN_OPTION, $encrypted_token );

		return $cookie_result && $db_result;
	}

	/**
	 * Check if there's an authentication error
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function has_auth_error() {
		return isset( $_GET['auth_error'] ) && sanitize_text_field( wp_unslash( $_GET['auth_error'] ) ) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get the OAuth callback URL
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_callback_url() {
		return admin_url( 'admin.php?page=surefeedback-cloud-dashboard' );
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
				'oauth_url' => rawurlencode( $callback_url ),
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
		$oauth_token = null;

		if ( isset( $_GET['oauth_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$oauth_token = sanitize_text_field( wp_unslash( $_GET['oauth_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( $request_uri && preg_match( '/[?&]oauth_token=([^&]+)/', $request_uri, $matches ) ) {
				$oauth_token = sanitize_text_field( $matches[1] );
			}
		}

		if ( ! $oauth_token ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $page ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( $request_uri && preg_match( '/page=([^&?]+)/', $request_uri, $matches ) ) {
				$page = sanitize_text_field( $matches[1] );
			}
		}

		if ( ! $page || strpos( $page, 'surefeedback-cloud' ) !== 0 ) {
			return;
		}

		$result = $this->exchange_token( $oauth_token );

		if ( $result ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
			exit;
		} else {
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

			if ( 200 !== $response_code && 201 !== $response_code ) {
				return false;
			}

			if ( ! empty( $result['success'] ) && true === $result['success'] && ! empty( $result['data'] ) ) {
				$data = $result['data'];
				if ( ! empty( $data['access_token'] ) ) {
					$this->store_bearer_token( $data['access_token'] );

					if ( ! empty( $data['connection_id'] ) ) {
						update_option( 'surefeedback_connection_id', sanitize_text_field( $data['connection_id'] ) );
					}

					if ( ! empty( $data['site_id'] ) ) {
						update_option( 'surefeedback_site_id', sanitize_text_field( $data['site_id'] ) );
					}

					if ( ! empty( $data['organization_id'] ) ) {
						update_option( 'surefeedback_organization_id', sanitize_text_field( $data['organization_id'] ) );
					}

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
			<p><?php esc_html_e( 'Authentication failed. Please try again.', 'surefeedback-cloud' ); ?></p>
		</div>
		<?php
	}
}