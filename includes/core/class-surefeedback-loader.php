<?php
/**
 * Plugin Loader and Registry
 *
 * @package SureFeedback Child
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin loader class
 * Handles plugin initialization, dependency loading, and service registration
 */
class SureFeedback_Loader {
	
	/**
	 * Plugin instance
	 */
	private static $instance = null;

	/**
	 * Loaded services
	 */
	private $services = array();

	/**
	 * Plugin path
	 */
	private $plugin_path;

	/**
	 * Plugin URL
	 */
	private $plugin_url;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->plugin_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
		$this->plugin_url  = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		
		$this->load_dependencies();
		$this->init_services();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {		
		// API classes
		require_once $this->plugin_path . 'includes/admin/class-surefeedback-admin-api.php';
	}

	/**
	 * Initialize services
	 */
	private function init_services() {
		// Initialize Admin API
		if ( class_exists( 'SureFeedback_Admin_API' ) ) {
			$this->services['admin_api'] = new SureFeedback_Admin_API();
		}
	}

	/**
	 * Get plugin path
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get plugin URL
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Get service
	 */
	public function get_service( $service_name ) {
		return isset( $this->services[ $service_name ] ) ? $this->services[ $service_name ] : null;
	}
}