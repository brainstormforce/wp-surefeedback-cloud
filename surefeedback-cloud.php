<?php

/**
 * Plugin Name: SureFeedback Cloud
 * Plugin URI: https://surefeedback.com
 * Description: Collect note-style feedback from your client's websites and sync them with your SureFeedback parent project.
 * Version: 0.0.1
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: surefeedback-cloud
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 *
 * @package SureFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Base URL constant
 */
if ( ! defined( 'SUREFEEDBACK_API_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_API_BASE_URL', 'https://api.surefeedback.com' );
}

/**
 * App Base URL constant
 */
if ( ! defined( 'SUREFEEDBACK_APP_BASE_URL' ) ) {
	define( 'SUREFEEDBACK_APP_BASE_URL', 'https://app.surefeedback.com' );
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

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );

		add_action( 'admin_init', array( $this, 'activation_redirect' ) );

		add_action( 'admin_init', array( $this, 'poll_connection_tokens' ) );
		
		add_action( 'plugins_loaded', array( $this, 'poll_connection_tokens' ), 20 );

		add_action( 'surefeedback_poll_connection_tokens', array( $this, 'poll_connection_tokens' ) );
		if ( ! wp_next_scheduled( 'surefeedback_poll_connection_tokens' ) ) {
			wp_schedule_event( time(), 'hourly', 'surefeedback_poll_connection_tokens' );
		}
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/class-surefeedback-autoloader.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/class-encryption.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/class-secure-cookie-manager.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/class-auth-manager.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/class-saas-client.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Admin/class-admin-menu.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Api/class-rest-controller.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/Api/class-webhook-controller.php';
		require_once SUREFEEDBACK_PLUGIN_PATH . 'includes/class-frontend-script.php';
	}

	/**
	 * Initialize plugin components
	 */
	private function init() {
		if ( is_admin() ) {
			new SureFeedback\Admin\Admin_Menu();
		}
		add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );
		add_action( 'init', array( $this, 'init_frontend_script' ) );
	}

	/**
	 * Initialize REST API
	 */
	public function init_rest_api() {
		$rest_controller = new SureFeedback\API\Rest_Controller();
		$rest_controller->register_routes();
		$webhook_controller = new SureFeedback\API\WebhookController();
		$webhook_controller->register_routes();
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
		flush_rewrite_rules();
		set_transient( 'surefeedback_activation_redirect', true, 30 );
		$this->poll_connection_tokens();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		flush_rewrite_rules();
		$timestamp = wp_next_scheduled( 'surefeedback_poll_connection_tokens' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'surefeedback_poll_connection_tokens' );
		}
	}

	/**
	 * Handle activation redirect to setup view
	 */
	public function activation_redirect() {
		if ( ! get_transient( 'surefeedback_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'surefeedback_activation_redirect' );
		if ( wp_doing_ajax() || wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$current_page = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple GET parameter check for activation redirect, no data modification
		if ( isset( $_GET['page'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe GET parameter read for navigation logic
			$current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}
		
		if ( ! empty( $current_page ) && strpos( $current_page, 'surefeedback-cloud' ) !== false ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=surefeedback-cloud-dashboard#setup' ) );
		exit;
	}

	/**
	 * Poll for pending connection tokens and automatically connect
	 * Called on admin_init, plugin activation, and via cron
	 */
	public function poll_connection_tokens() {
		$auth_manager = new SureFeedback\Auth_Manager();
		if ( $auth_manager->is_authenticated() ) {
			return;
		}
		$is_activation = doing_action( 'activate_' . plugin_basename( __FILE__ ) );
		$is_plugins_loaded = doing_action( 'plugins_loaded' );

		if ( ! is_admin() && ! wp_doing_cron() && ! $is_activation && ! $is_plugins_loaded ) {
			return;
		}
		$rest_controller = new SureFeedback\API\Rest_Controller();
		
		$request = new WP_REST_Request( 'POST', '/surefeedback/v1/connection/poll-tokens' );
		
		$result = $rest_controller->poll_connection_tokens( $request );
		if ( is_wp_error( $result ) ) {
		} elseif ( is_object( $result ) && method_exists( $result, 'get_data' ) ) {
			$data = $result->get_data();
			if ( isset( $data['connected'] ) && $data['connected'] ) {
			} elseif ( isset( $data['success'] ) && ! $data['success'] ) {
			}
		}
	}

	/**
	 * Add action links to plugin list
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_action_links( $links ) {
		$auth_manager = new SureFeedback\Auth_Manager();
		$is_connected = $auth_manager->is_authenticated();
		$link_text = $is_connected
			? __( 'Access Dashboard', 'surefeedback-cloud' )
			: __( 'Get Started Now', 'surefeedback-cloud' );

		$dashboard_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=surefeedback-cloud-dashboard' ),
			$link_text
		);

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

function surefeedback() {
	return SureFeedback::get_instance();
}

surefeedback();
