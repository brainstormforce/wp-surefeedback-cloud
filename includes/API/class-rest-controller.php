<?php
/**
 * REST Controller class
 *
 * Handles WordPress-specific REST API endpoints for the SureFeedback plugin.
 * Manages authentication bridging between WordPress and SaaS platform.
 *
 * @package SureFeedback
 */

namespace SureFeedback\API;

use SureFeedback\Encryption;
use SureFeedback\SaaS_Client;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST Controller class
 *
 * Provides REST API endpoints for:
 * - Connection management
 * - Settings management
 * - Page settings
 * - Verification
 */
class Rest_Controller extends WP_REST_Controller {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'surefeedback/v1';

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
	}

	/**
	 * Register routes
	 *
	 * @since 0.0.1
	 */
	public function register_routes() {
		$this->register_connection_routes();

		$this->register_settings_routes();

		$this->register_page_settings_routes();

		$this->register_verification_routes();
	}

	/**
	 * Register connection routes
	 */
	private function register_connection_routes() {
		register_rest_route(
			$this->namespace,
			'/connection/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_connection_status' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/connection/disconnect',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'disconnect_from_saas' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/connection/store-state',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'store_state' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => array(
						'state' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/connection/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'verify_connection' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/connection/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_connection_health' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Register settings routes
	 */
	private function register_settings_routes() {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/general',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_general_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_general_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Register page settings routes
	 */
	private function register_page_settings_routes() {
		register_rest_route(
			$this->namespace,
			'/page-settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_page_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_page_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/page-settings/enable-all',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'enable_all_pages' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/page-settings/disable-all',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'disable_all_pages' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Register verification routes
	 */
	private function register_verification_routes() {
		register_rest_route(
			$this->namespace,
			'/verification/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'verify_integration' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get connection status
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_connection_status( $request ) {
		$auth_manager = new \SureFeedback\Auth_Manager();
		$is_connected = $auth_manager->is_authenticated();

		$connection_id = get_option( 'surefeedback_connection_id', '' );
		$site_id       = get_option( 'surefeedback_site_id', '' );

		return rest_ensure_response(
			array(
				'success'        => true,
				'connected'      => $is_connected,
				'connection_id'  => $connection_id,
				'site_id'        => $site_id,
				'plugin_version' => SUREFEEDBACK_VERSION,
			)
		);
	}

	/**
	 * Disconnect from SaaS
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function disconnect_from_saas( $request ) {
		$secure_cookie_manager = \SureFeedback\Secure_Cookie_Manager::get_instance();
		$secure_cookie_manager->delete_secure_cookie( 'auth_token' );

		delete_option( 'surefeedback_bearer_token' );
		delete_option( 'surefeedback_connection_id' );
		delete_option( 'surefeedback_site_id' );
		delete_option( 'surefeedback_organization_id' );
		delete_option( 'surefeedback_access_token' );

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Disconnected successfully',
				'connected' => false,
			)
		);
	}

	/**
	 * Store state for OAuth flow
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function store_state( $request ) {
		$state = $request->get_param( 'state' );

		if ( empty( $state ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'State parameter is required.', 'surefeedback-cloud' ),
				array( 'status' => 400 )
			);
		}

		$expiry = time() + ( 60 * MINUTE_IN_SECONDS );
		$result = update_option(
			'surefeedback_webhook_state',
			array(
				'state'      => sanitize_text_field( $state ),
				'expiry'     => $expiry,
				'created_at' => time(),
			)
		);

		if ( $result ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'message'   => 'State stored successfully',
					'state'     => $state,
					'stored_at' => current_time( 'mysql' ),
					'expiry'    => gmdate( 'Y-m-d H:i:s', $expiry ),
				)
			);
		}

		return new WP_Error(
			'rest_state_storage_failed',
			__( 'Failed to store state.', 'surefeedback-cloud' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Verify connection
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function verify_connection( $request ) {
		$auth_manager = new \SureFeedback\Auth_Manager();

		if ( ! $auth_manager->is_authenticated() ) {
			return new WP_Error(
				'rest_not_connected',
				__( 'Not connected to SureFeedback.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'connected' => true,
				'message'   => 'Connection verified',
			)
		);
	}

	/**
	 * Get connection health
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_connection_health( $request ) {
		$auth_manager = new \SureFeedback\Auth_Manager();
		$is_connected = $auth_manager->is_authenticated();

		$health_score = 100;
		if ( ! $is_connected ) {
			$health_score = 0;
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'health_score' => $health_score,
				'connected'    => $is_connected,
				'status'       => $is_connected ? 'healthy' : 'disconnected',
			)
		);
	}

	/**
	 * Get all settings
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_settings( $request ) {
		$wp_roles        = wp_roles()->get_names();
		$available_roles = array();

		foreach ( $wp_roles as $role_key => $role_name ) {
			$available_roles[] = array(
				'name'  => $role_key,
				'label' => $role_name,
			);
		}

		$saved_roles = get_option( 'surefeedback_allowed_roles', array( 'administrator' ) );

		$general = array(
			'roles' => $saved_roles,
		);

		return rest_ensure_response(
			array(
				'success'        => true,
				'general'        => $general,
				'availableRoles' => $available_roles,
			)
		);
	}

	/**
	 * Update settings
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_settings( $request ) {
		$data = $request->get_json_params();

		if ( isset( $data['general'] ) && isset( $data['general']['roles'] ) ) {
			update_option( 'surefeedback_allowed_roles', array_map( 'sanitize_text_field', $data['general']['roles'] ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Settings updated successfully',
			)
		);
	}

	/**
	 * Get general settings
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_general_settings( $request ) {
		$wp_roles        = wp_roles()->get_names();
		$available_roles = array();

		foreach ( $wp_roles as $role_key => $role_name ) {
			$available_roles[] = array(
				'name'  => $role_key,
				'label' => $role_name,
			);
		}

		$saved_roles = get_option( 'surefeedback_allowed_roles', array( 'administrator' ) );

		return rest_ensure_response(
			array(
				'success'        => true,
				'general'        => array(
					'roles' => $saved_roles,
				),
				'availableRoles' => $available_roles,
			)
		);
	}

	/**
	 * Update general settings
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_general_settings( $request ) {
		$data = $request->get_json_params();

		if ( isset( $data['roles'] ) && is_array( $data['roles'] ) ) {
			update_option( 'surefeedback_allowed_roles', array_map( 'sanitize_text_field', $data['roles'] ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'General settings updated successfully',
			)
		);
	}

	/**
	 * Get page settings
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_page_settings( $request ) {
		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);

		$page_list = array();
		foreach ( $pages as $page ) {
			$page_list[] = array(
				'id'    => $page->ID,
				'title' => $page->post_title,
				'url'   => get_permalink( $page->ID ),
				'type'  => 'page',
			);
		}

		$settings = get_option( 'surefeedback_page_settings', array() );

		return rest_ensure_response(
			array(
				'success'  => true,
				'pages'    => $page_list,
				'settings' => $settings,
			)
		);
	}

	/**
	 * Update page settings
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_page_settings( $request ) {
		$data = $request->get_json_params();

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$sanitized = array();
			foreach ( $data['settings'] as $page_id => $enabled ) {
				$sanitized[ intval( $page_id ) ] = (bool) $enabled;
			}
			update_option( 'surefeedback_page_settings', $sanitized );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Page settings updated successfully',
			)
		);
	}

	/**
	 * Enable widget for all pages
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function enable_all_pages( $request ) {
		$pages    = get_pages();
		$settings = array();

		foreach ( $pages as $page ) {
			$settings[ $page->ID ] = true;
		}

		update_option( 'surefeedback_page_settings', $settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Widget enabled for all pages',
			)
		);
	}

	/**
	 * Disable widget for all pages
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function disable_all_pages( $request ) {
		$pages    = get_pages();
		$settings = array();

		foreach ( $pages as $page ) {
			$settings[ $page->ID ] = false;
		}

		update_option( 'surefeedback_page_settings', $settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Widget disabled for all pages',
			)
		);
	}

	/**
	 * Verify integration with Laravel API
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function verify_integration( $request ) {
		$data       = $request->get_json_params();
		$site_token = $data['site_token'] ?? '';

		if ( empty( $site_token ) ) {
			return new WP_Error(
				'rest_missing_param',
				__( 'Site token is required.', 'surefeedback-cloud' ),
				array( 'status' => 400 )
			);
		}

		$auth_manager = new \SureFeedback\Auth_Manager();
		$bearer_token = $auth_manager->get_bearer_token();

		if ( ! $bearer_token ) {
			return new WP_Error(
				'rest_not_authenticated',
				__( 'Not authenticated with SureFeedback.', 'surefeedback-cloud' ),
				array( 'status' => 401 )
			);
		}

		try {
			$saas_client = new SaaS_Client( $auth_manager );

			$site_token = get_option( 'surefeedback_access_token', '' );
			if ( ! $site_token ) {
				$site_token = '';
			}

			$result = $saas_client->get(
				'admin/verify-integration',
				array(
					'query' => array(
						'script_token' => sanitize_text_field( $site_token ),
					),
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$is_verified = false;
			if ( isset( $result['data']['integrated'] ) && true === $result['data']['integrated'] ) {
				$is_verified = true;
			}

			return rest_ensure_response(
				array(
					'success'             => true,
					'is_fully_verified'   => $is_verified,
					'verification_status' => $is_verified ? 'verified' : 'pending',
					'data'                => $result,
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'rest_verification_failed',
				__( 'Verification failed: ', 'surefeedback-cloud' ) . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Poll for pending connection tokens and automatically exchange them
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function poll_connection_tokens( $request ) {
		$auth_manager = new \SureFeedback\Auth_Manager();
		if ( $auth_manager->is_authenticated() ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'message'   => 'Already connected',
					'connected' => true,
				)
			);
		}

		$site_url = get_site_url();
		$parsed   = wp_parse_url( $site_url );
		$domain   = $parsed['host'] ?? '';

		if ( empty( $domain ) ) {
			return new WP_Error(
				'rest_invalid_domain',
				__( 'Unable to determine site domain.', 'surefeedback-cloud' ),
				array( 'status' => 400 )
			);
		}

		$domain = $this->normalize_domain_for_query( $domain );

		$api_base_url = SUREFEEDBACK_API_BASE_URL;

		$saas_client = new SaaS_Client();
		$result      = $saas_client->get(
			'connections/pending-tokens',
			array(
				'query' => array(
					'domain' => $domain,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success'   => false,
					'message'   => 'Failed to fetch pending tokens: ' . $result->get_error_message(),
					'connected' => false,
				)
			);
		}

		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			return rest_ensure_response(
				array(
					'success'   => false,
					'message'   => 'No pending tokens found',
					'connected' => false,
				)
			);
		}

		$tokens = $result['data']['tokens'] ?? array();

		if ( empty( $tokens ) ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'message'   => 'No pending connection tokens found',
					'connected' => false,
				)
			);
		}

		$token = $tokens[0]['token'] ?? null;

		if ( ! $token ) {
			return rest_ensure_response(
				array(
					'success'   => false,
					'message'   => 'Invalid token data',
					'connected' => false,
				)
			);
		}

		$exchange_result = $this->exchange_connection_token( $token, $site_url );

		if ( is_wp_error( $exchange_result ) ) {
			return rest_ensure_response(
				array(
					'success'   => false,
					'message'   => 'Token exchange failed: ' . $exchange_result->get_error_message(),
					'connected' => false,
				)
			);
		}

		$site_id = get_option( 'surefeedback_site_id', '' );

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Connection established successfully',
				'connected' => true,
				'site_id'   => $site_id,
			)
		);
	}

	/**
	 * Exchange connection token for permanent JWT
	 *
	 * @param string $oauth_token The connection token.
	 * @param string $site_url    The WordPress site URL.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function exchange_connection_token( $oauth_token, $site_url ) {
		$api_base_url = SUREFEEDBACK_API_BASE_URL;
		$api_url      = $api_base_url . '/api/v1/connections/exchange';

		$site_api_url = rtrim( $site_url, '/' ) . '/wp-json/surefeedback/v1';

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'oauth_token' => $oauth_token,
						'site_url'    => $site_api_url,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			return new WP_Error(
				'token_exchange_failed',
				sprintf( 'Token exchange failed with status code %d', $code ),
				array(
					'body' => $body,
					'code' => $code,
				)
			);
		}

		$data = json_decode( $body, true );

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			return new WP_Error(
				'token_exchange_failed',
				$data['message'] ?? 'Token exchange failed',
			);
		}
		$connection_data = $data['data'] ?? array();
		$access_token    = $connection_data['access_token'] ?? null;
		$site_id         = $connection_data['site_id'] ?? null;
		$organization_id = $connection_data['organization_id'] ?? null;
		$script_token    = $connection_data['script_token'] ?? null;

		if ( ! $access_token || ! $site_id ) {
			return new WP_Error(
				'token_exchange_invalid',
				'Invalid response data from token exchange',
			);
		}

		$auth_manager = new \SureFeedback\Auth_Manager();
		$auth_manager->store_bearer_token( $access_token );

		$options_stored = array();

		if ( ! empty( $connection_data['connection_id'] ) ) {
			$options_stored[] = 'connection_id';
			update_option( 'surefeedback_connection_id', sanitize_text_field( $connection_data['connection_id'] ) );
		}

		if ( ! empty( $site_id ) ) {
			$options_stored[] = 'site_id';
			update_option( 'surefeedback_site_id', sanitize_text_field( $site_id ) );
		}

		if ( ! empty( $organization_id ) ) {
			$options_stored[] = 'organization_id';
			update_option( 'surefeedback_organization_id', sanitize_text_field( $organization_id ) );
		}

		if ( ! empty( $script_token ) ) {
			$options_stored[] = 'access_token';
			$options_stored[] = 'site_token';
			update_option( 'surefeedback_access_token', sanitize_text_field( $script_token ) );
			update_option( 'surefeedback_site_token', sanitize_text_field( $script_token ) );
		}

		$options_stored[] = 'parent_url';
		$options_stored[] = 'widget_script_url';
		update_option( 'surefeedback_parent_url', esc_url_raw( $api_base_url ) );
		update_option( 'surefeedback_widget_script_url', esc_url_raw( $api_base_url . '/dist/widget.js' ) );
		return true;
	}

	/**
	 * Normalize domain for querying (matches Laravel's normalization)
	 *
	 * @param string $domain The domain to normalize.
	 * @return string Normalized domain.
	 */
	private function normalize_domain_for_query( $domain ) {
		$domain = preg_replace( '#^https?://#', '', $domain );

		$domain = rtrim( $domain, '/' );

		$domain = preg_replace( '#^www\.#', '', $domain );

		$parts  = explode( '/', $domain );
		$domain = $parts[0];

		$domain = preg_replace( '#:\d+$#', '', $domain );

		return strtolower( $domain );
	}

	/**
	 * Check if user has admin permissions
	 *
	 * @return bool|WP_Error
	 */
	public function admin_permissions_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'surefeedback-cloud' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}
}
