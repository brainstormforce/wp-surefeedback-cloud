<?php
/**
 * Frontend Script Loader
 *
 * @package SureFeedback
 * @since   0.0.1
 */

namespace SureFeedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Script Loader class
 * Handles loading the SureFeedback SDK on the frontend with enhanced security and performance
 */
class Frontend_Script {

	/**
	 * Cache for expensive operations
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Script loaded flag
	 *
	 * @var bool
	 */
	private static $script_loaded = false;

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * Note: We use wp_enqueue_scripts for proper WordPress compliance,
	 * with conditional checks during the enqueue process.
	 *
	 * @return void
	 */
	private function init_hooks() {
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ), 20 );
		}

		if ( is_admin() && $this->should_load_in_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ), 20 );
		}
	}

	/**
	 * Check if script should load in admin
	 *
	 * @return bool
	 */
	private function should_load_in_admin() {
		if ( isset( self::$cache['load_in_admin'] ) ) {
			return self::$cache['load_in_admin'];
		}

		self::$cache['load_in_admin'] = (bool) get_option( 'surefeedback_load_in_admin', false );
		return self::$cache['load_in_admin'];
	}

	/**
	 * Check if widget should display on current page
	 *
	 * @return bool
	 */
	private function should_display_widget() {
		$cache_key = 'should_display_widget_' . $this->get_current_page_id();
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$current_page_id = $this->get_current_page_id();
		$page_settings   = $this->get_page_settings();

		if ( empty( $page_settings ) || ! $current_page_id ) {
			self::$cache[ $cache_key ] = true;
			return true;
		}

		$should_display = ! isset( $page_settings[ $current_page_id ] ) || false !== $page_settings[ $current_page_id ];

		$should_display = apply_filters( 'surefeedback_should_display_widget', $should_display, $current_page_id, $page_settings );

		self::$cache[ $cache_key ] = $should_display;
		return $should_display;
	}

	/**
	 * Get page settings with caching
	 *
	 * @return array
	 */
	private function get_page_settings() {
		if ( isset( self::$cache['page_settings'] ) ) {
			return self::$cache['page_settings'];
		}

		$settings = get_option( 'surefeedback_page_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		self::$cache['page_settings'] = $settings;
		return $settings;
	}

	/**
	 * Get current page ID with improved logic and caching
	 *
	 * @return string|null
	 */
	private function get_current_page_id() {
		if ( isset( self::$cache['current_page_id'] ) ) {
			return self::$cache['current_page_id'];
		}

		$page_id = null;

		if ( is_front_page() ) {
			$page_id = 'home';
		} elseif ( is_home() ) {
			$page_id = 'blog';
		} elseif ( is_singular() ) {
			$post_type = get_post_type();
			$post_id   = get_the_ID();

			if ( $post_type && $post_id ) {
				$page_id = $post_type . '_' . $post_id;
			}
		} elseif ( is_category() ) {
			$page_id = 'category_' . get_queried_object_id();
		} elseif ( is_tag() ) {
			$page_id = 'tag_' . get_queried_object_id();
		} elseif ( is_tax() ) {
			$page_id = 'tax_' . get_queried_object()->taxonomy . '_' . get_queried_object_id();
		} elseif ( is_archive() ) {
			$page_id = 'archive';
		} elseif ( is_search() ) {
			$page_id = 'search';
		} elseif ( is_404() ) {
			$page_id = '404';
		}

		$page_id = apply_filters( 'surefeedback_current_page_id', $page_id );

		self::$cache['current_page_id'] = $page_id;
		return $page_id;
	}

	/**
	 * Check if user is allowed to access the widget
	 *
	 * @return bool
	 */
	private function is_user_allowed() {
		if ( isset( self::$cache['user_allowed'] ) ) {
			return self::$cache['user_allowed'];
		}

		$allowed_roles = get_option( 'surefeedback_allowed_roles', array( 'administrator' ) );

		if ( ! is_array( $allowed_roles ) || empty( $allowed_roles ) ) {
			$allowed_roles = array( 'administrator' );
		}

		if ( ! is_user_logged_in() ) {
			$allow_guests                = apply_filters( 'surefeedback_allow_guest_users', true );
			self::$cache['user_allowed'] = $allow_guests;
			return $allow_guests;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			self::$cache['user_allowed'] = false;
			return false;
		}

		$user_roles = (array) $user->roles;
		$is_allowed = ! empty( array_intersect( $user_roles, $allowed_roles ) );

		$is_allowed = apply_filters( 'surefeedback_is_user_allowed', $is_allowed, $user, $allowed_roles );

		self::$cache['user_allowed'] = $is_allowed;
		return $is_allowed;
	}

	/**
	 * Validate and check for magic token in URL
	 *
	 * @return bool
	 */
	private function has_magic_token_param() {
		if ( ! isset( $_GET['magic_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$magic_token = sanitize_text_field( wp_unslash( $_GET['magic_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$is_valid = ! empty( $magic_token )
			&& strlen( $magic_token ) === 64
			&& ctype_xdigit( $magic_token );

		return $is_valid;
	}

	/**
	 * Validate and check for API token in URL
	 *
	 * @return bool
	 */
	private function has_api_token_param() {
		if ( ! isset( $_GET['api_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$api_token = sanitize_text_field( wp_unslash( $_GET['api_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$is_valid = ! empty( $api_token )
			&& strpos( $api_token, 'sc_' ) === 0
			&& strlen( $api_token ) > 10;

		return $is_valid;
	}

	/**
	 * Get authentication manager instance with error handling
	 *
	 * @return Auth_Manager|null
	 */
	private function get_auth_manager() {
		try {
			if ( ! class_exists( '\SureFeedback\Auth_Manager' ) ) {
				return null;
			}
			return new Auth_Manager();
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get and validate site configuration
	 *
	 * @return array|false Array with site_id and access_token, or false on failure
	 */
	private function get_site_config() {
		if ( isset( self::$cache['site_config'] ) ) {
			return self::$cache['site_config'];
		}

		$site_id      = trim( get_option( 'surefeedback_site_id', '' ) );
		$access_token = trim( get_option( 'surefeedback_access_token', '' ) );

		if ( empty( $site_id ) || empty( $access_token ) ) {
			self::$cache['site_config'] = false;
			return false;
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $site_id ) ) {
			self::$cache['site_config'] = false;
			return false;
		}

		$config = array(
			'site_id'      => $site_id,
			'access_token' => $access_token,
		);

		self::$cache['site_config'] = $config;
		return $config;
	}

	/**
	 * Build and validate SDK URL
	 *
	 * @param string $site_id The site ID.
	 * @return string|false
	 */
	private function build_sdk_url( $site_id ) {
		if ( ! defined( 'SUREFEEDBACK_API_BASE_URL' ) ) {
			return false;
		}

		$sdk_base_url = SUREFEEDBACK_API_BASE_URL;
		$sdk_base_url = preg_replace( '#/api/v1/?$#', '', rtrim( $sdk_base_url, '/' ) );

		if ( empty( $sdk_base_url ) ) {
			return false;
		}

		if ( ! filter_var( $sdk_base_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return $sdk_base_url . '/sdk/ws/' . esc_attr( $site_id ) . '.js';
	}

	/**
	 * Generate optimized JavaScript for SDK loading
	 *
	 * @param array $config Configuration array with SDK URL, tokens, etc.
	 * @return string
	 */
	private function generate_sdk_javascript( $config ) {
		// Use wp_json_encode with proper flags for JSON data to prevent XSS
		$page_settings   = wp_json_encode( $this->get_page_settings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$current_page_id = wp_json_encode( $this->get_current_page_id(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		
		// Use esc_js for all string values that will be inserted into JavaScript context
		// This prevents XSS attacks through maliciously crafted URLs or tokens
		$sdk_url      = esc_js( $config['sdk_url'] );
		$sdk_base_url = esc_js( $config['sdk_base_url'] );
		$access_token = esc_js( $config['access_token'] );

		$javascript = '(function(){
			\'use strict\';
			
			function shouldDisplayWidget(){
				var ps=' . $page_settings . ',cpi=' . $current_page_id . ';
				return !ps||!Object.keys(ps).length||!cpi||ps[cpi]!==false;
			}
			
			function checkAuth(){
				var up=new URLSearchParams(window.location.search);
				var hasMT=up.get(\'magic_token\')!==null;
				var hasAT=up.get(\'api_token\')!==null;
				var hasSA=false;
				
				try{
					var items=[\'surefeedback_user_session\',\'surefeedback_site_config\',\'surefeedback_api_token\'];
					hasSA=items.some(function(item){return localStorage.getItem(item);});
				}catch(e){/* ignored */}
				
				return hasMT||hasAT||hasSA;
			}
			
			function loadSDK(){
				var s=document.createElement(\'script\');
				s.src=\'' . $sdk_url . '\';
				s.async=true;
				s.defer=true;
				s.charset=\'UTF-8\';
				s.setAttribute(\'data-token\',\'' . $access_token . '\');
				s.setAttribute(\'data-base-url\',\'' . $sdk_base_url . '\');
				
				document.head.appendChild(s);
			}
			
			if(shouldDisplayWidget()&&checkAuth()){
				loadSDK();
			}
		})();';

		return $javascript;
	}

	/**
	 * Main script enqueuing method with comprehensive error handling
	 *
	 * @since 0.0.1
	 */
	public function enqueue_script() {
		if ( self::$script_loaded ) {
			return;
		}

		if ( ! apply_filters( 'surefeedback_script_should_load', true ) ) {
			return;
		}

		if ( ! $this->should_display_widget() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<!-- SureFeedback: Widget disabled for this page -->';
			}
			return;
		}

		$auth_manager = $this->get_auth_manager();
		if ( ! $auth_manager || ! $auth_manager->is_authenticated() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<!-- SureFeedback: Not connected -->';
			}
			return;
		}

		$site_config = $this->get_site_config();
		if ( false === $site_config ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<!-- SureFeedback: Invalid site configuration -->';
			}
			return;
		}

		$sdk_url = $this->build_sdk_url( $site_config['site_id'] );
		if ( false === $sdk_url ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<!-- SureFeedback: Failed to build SDK URL -->';
			}
			return;
		}

		$sdk_base_url = defined( 'SUREFEEDBACK_API_BASE_URL' )
			? preg_replace( '#/api/v1/?$#', '', rtrim( SUREFEEDBACK_API_BASE_URL, '/' ) )
			: '';

		$js_config = array(
			'sdk_url'      => $sdk_url,
			'sdk_base_url' => $sdk_base_url,
			'access_token' => $site_config['access_token'],
		);

		// Register and enqueue a minimal script to attach inline JavaScript to.
		$script_handle = 'surefeedback-inline-loader';
		wp_register_script( $script_handle, false, array(), SUREFEEDBACK_VERSION, true );
		wp_enqueue_script( $script_handle );

		// Add inline JavaScript using WordPress proper enqueuing system.
		wp_add_inline_script( $script_handle, $this->generate_sdk_javascript( $js_config ), 'after' );

		self::$script_loaded = true;

		do_action( 'surefeedback_script_loaded', $js_config );
	}

	/**
	 * Clear internal cache (useful for testing or dynamic updates)
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cache         = array();
		self::$script_loaded = false;
	}

	/**
	 * Get cached value for debugging
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public static function get_cache( $key = null ) {
		if ( null === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}
}
