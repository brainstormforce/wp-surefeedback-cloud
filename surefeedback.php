<?php

/**
 * Plugin Name: SureFeedback Cloud Connector
 * Plugin URI: https://surefeedback.com
 * Description: Collect note-style feedback from your client's websites and sync them with your SureFeedback parent project.
 * Version: 0.0.1
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: surefeedback
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package SureFeedback
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if running in local development environment
$surefeedback_is_local_env = defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE;

/**
 * SaaS API Base URL constant
 */
if ( ! defined( 'SUREFEEDBACK_SAAS_API_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_SAAS_API_BASE_URL', $surefeedback_is_local_env ? 'http://localhost:8000' : 'https://api.surefeedback.com' );
}

/**
 * SaaS App Base URL constant
 */
if ( ! defined( 'SUREFEEDBACK_SAAS_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_SAAS_BASE_URL', $surefeedback_is_local_env ? 'http://localhost:3000' : 'https://app.surefeedback.com' );
}

/**
 * API Base URL constant (alias for backward compatibility)
 */
if ( ! defined( 'SUREFEEDBACK_API_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_API_BASE_URL', SUREFEEDBACK_SAAS_API_BASE_URL );
}

/**
 * App Base URL constant (alias for backward compatibility)
 */
if ( ! defined( 'SUREFEEDBACK_APP_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_APP_BASE_URL', SUREFEEDBACK_SAAS_BASE_URL );
}

/**
 * Main plugin class
 */
final class SureFeedback {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const VERSION = '0.0.1';

	/**
	 * Plugin singleton instance
	 *
	 * @var SureFeedback
	 */
	private static $instance = null;

	/**
	 * Plugin directory path
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin directory URL
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Get singleton instance
	 *
	 * @return SureFeedback
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->define_constants();
		$this->setup_hooks();
		$this->includes();
		$this->init();
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants() {
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );

		define( 'SUREFEEDBACK_VERSION', self::VERSION );
		define( 'SUREFEEDBACK_PLUGIN_PATH', $this->plugin_path );
		define( 'SUREFEEDBACK_PLUGIN_URL', $this->plugin_url );
		define( 'SUREFEEDBACK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'SUREFEEDBACK_PLUGIN_FILE', __FILE__ );
		define( 'SUREFEEDBACK_PLUGIN_DIR', $this->plugin_path );
	}

	/**
	 * Setup plugin hooks
	 */
	private function setup_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Add plugin action links
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );

		// Handle activation redirect
		add_action( 'admin_init', array( $this, 'activation_redirect' ) );

		// Poll for connection tokens automatically
		add_action( 'admin_init', array( $this, 'poll_connection_tokens' ) );

		// Set up cron job for periodic token polling
		add_action( 'surefeedback_poll_connection_tokens', array( $this, 'poll_connection_tokens' ) );
		if ( ! wp_next_scheduled( 'surefeedback_poll_connection_tokens' ) ) {
			wp_schedule_event( time(), 'hourly', 'surefeedback_poll_connection_tokens' );
		}
	}

	/**
	 * Include required files
	 */
	private function includes() {
		// Include autoloader
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Autoloader.php';

		// Security & Encryption
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Encryption.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Secure_Cookie_Manager.php';

		// Authentication
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Auth_Manager.php';

		// SaaS Integration
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/SaaS_Client.php';

		// Admin
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Admin/Admin_Menu.php';

		// REST API
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/API/Rest_Controller.php';

		// Frontend Script Loader
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Frontend_Script.php';
	}

	/**
	 * Initialize plugin components
	 */
	private function init() {
		// Initialize admin menu (instantiate early so menu registers properly)
		if ( is_admin() ) {
			new SureFeedback\Admin\Admin_Menu();
		}

		// Initialize REST API
		add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );

		// Initialize frontend script loader
		add_action( 'init', array( $this, 'init_frontend_script' ) );
	}

	/**
	 * Initialize REST API
	 */
	public function init_rest_api() {
		$rest_controller = new SureFeedback\API\Rest_Controller();
		$rest_controller->register_routes();
	}

	/**
	 * Initialize frontend script loader
	 */
	public function init_frontend_script() {
		new SureFeedback\Frontend_Script();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Clear permalinks
		flush_rewrite_rules();

		// Set transient for activation redirect
		set_transient( 'surefeedback_activation_redirect', true, 30 );
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		flush_rewrite_rules();

		// Clear scheduled cron job
		$timestamp = wp_next_scheduled( 'surefeedback_poll_connection_tokens' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'surefeedback_poll_connection_tokens' );
		}
	}

	/**
	 * Handle activation redirect to setup view
	 */
	public function activation_redirect() {
		// Check if we should redirect after activation
		if ( ! get_transient( 'surefeedback_activation_redirect' ) ) {
			return;
		}

		// Delete the transient so we only redirect once
		delete_transient( 'surefeedback_activation_redirect' );

		// Don't redirect if doing AJAX, cron, or if user is not admin
		if ( wp_doing_ajax() || wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't redirect if already on our plugin page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking page parameter for redirect prevention, no data processing
		if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'surefeedback' ) !== false ) {
			return;
		}

		// Redirect to dashboard with setup route (hash will be picked up by React router)
		wp_safe_redirect( admin_url( 'admin.php?page=surefeedback-dashboard#setup' ) );
		exit;
	}

	/**
	 * Poll for pending connection tokens and automatically connect
	 * Called on admin_init and via cron
	 */
	public function poll_connection_tokens() {
		// Only poll if not already connected
		$auth_manager = new SureFeedback\Auth_Manager();
		if ( $auth_manager->is_authenticated() ) {
			return;
		}

		// Only poll in admin area or via cron (not on every page load)
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		// Get REST controller instance and call internal polling method
		$rest_controller = new SureFeedback\API\Rest_Controller();
		
		// Create a mock REST request for the method
		$request = new WP_REST_Request( 'POST', '/surefeedback/v1/connection/poll-tokens' );
		
		// Call the poll method (it will handle the logic internally)
		$result = $rest_controller->poll_connection_tokens( $request );
		
		// Log result for debugging (only log errors)
		if ( is_wp_error( $result ) ) {
			error_log( 'SureFeedback: Token polling failed - ' . $result->get_error_message() );
		}
	}

	/**
	 * Add action links to plugin list
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_action_links( $links ) {
		// Check if plugin is connected to SureFeedback
		$auth_manager = new SureFeedback\Auth_Manager();
		$is_connected = $auth_manager->is_authenticated();

		// Show different link text based on connection status
		$link_text = $is_connected
			? __( 'Access Dashboard', 'surefeedback' )
			: __( 'Get Started Now', 'surefeedback' );

		$dashboard_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=surefeedback-dashboard' ),
			$link_text
		);

		// Add our link to the beginning of the array
		array_unshift( $links, $dashboard_link );

		return $links;
	}

	/**
	 * Get plugin directory path
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get plugin directory URL
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}
}

// Initialize the plugin
function surefeedback() {
	return SureFeedback::get_instance();
}

// Start the plugin
surefeedback();
