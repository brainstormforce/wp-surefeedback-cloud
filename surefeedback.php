<?php
/**
 * Plugin Name: SureFeedback Client
 * Plugin URI: http://surefeedback.com
 * Description: Collect note-style feedback from your client's websites and sync them with your SureFeedback parent project.
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * Version: 1.0.0
 *
 * Requires at least: 4.7
 * Tested up to: 6.8
 *
 * Text Domain: surefeedback
 * Domain Path: languages
 *
 * @package SureFeedback
 * @author Brainstorm Force
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Constants before init
 *
 * @since 1.0.0
 */

// Plugin Folder Path.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_DIR' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_URL' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin Root File.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_FILE' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_FILE', __FILE__ );
}

// Plugin Version.
if ( ! defined( 'SUREFEEDBACK_VERSION' ) ) {
	define( 'SUREFEEDBACK_VERSION', '1.0.0' );
}

// Plugin Basename.
if ( ! defined( 'SUREFEEDBACK_PLUGIN_BASENAME' ) ) {
	define( 'SUREFEEDBACK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Include autoloader.
require_once SUREFEEDBACK_PLUGIN_DIR . 'includes/class-autoloader.php';


/**
 * Main plugin class
 */
final class SureFeedback {

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
	 * Admin menu manager
	 *
	 * @var \SureFeedback\Admin\Admin_Menu
	 */
	private $admin_menu;

	/**
	 * REST controller
	 *
	 * @var \SureFeedback\API\Rest_Controller
	 */
	private $rest_controller;

	/**
	 * SaaS client
	 *
	 * @var \SureFeedback\SaaS\SaaS_Client
	 */
	private $saas_client;

	/**
	 * Frontend manager
	 *
	 * @var \SureFeedback\Frontend\Frontend_Manager
	 */
	private $frontend_manager;

	/**
	 * Environment modes
	 */
	const MODE_DEVELOPMENT = 'development';
	const MODE_PRODUCTION = 'production';

	/**
	 * API URLs
	 */
	const API_URL_LOCAL = 'http://localhost:8000';
	const API_URL_PRODUCTION = 'https://api.surefeedback.com';

	/**
	 * App URLs  
	 */
	const APP_URL_LOCAL = 'http://localhost:3000';
	const APP_URL_PRODUCTION = 'https://app.surefeedback.com';

	/**
	 * Default mode
	 */
	const DEFAULT_MODE = self::MODE_DEVELOPMENT;

	/**
	 * Make sure to whitelist our option names
	 *
	 * @var array
	 */
	protected $whitelist_option_names = array();

	/**
	 * Get instance of this class
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
	 * Initialize the plugin
	 */
	public function __construct() {
		$this->setup_properties();
		$this->define_constants();
		$this->init_hooks();
		
		// Initialize components after WordPress is loaded
		add_action('init', array($this, 'includes'), 0);
	}

	/**
	 * Setup plugin properties
	 */
	private function setup_properties() {
		$this->plugin_path = SUREFEEDBACK_PLUGIN_DIR;
		$this->plugin_url  = SUREFEEDBACK_PLUGIN_URL;
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants() {
		// Environment already defined in global constants
	}

	/**
	 * Include required files and initialize components
	 */
	public function includes() {
		// Initialize admin menu
		if ( is_admin() ) {
			$this->admin_menu = new \SureFeedback\Admin\Admin_Menu();
		}

		// Initialize REST controller
		$this->rest_controller = new \SureFeedback\API\Rest_Controller();

		// Initialize SaaS client
		$this->saas_client = new \SureFeedback\SaaS\SaaS_Client();

		// Initialize frontend manager
		if ( ! is_admin() ) {
			$this->frontend_manager = new \SureFeedback\Frontend\Frontend_Manager();
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Load plugin text domain and setup options after WordPress is ready
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Handle automatic verification using SaaS client
		add_action( 'surefeedback_auto_verify', array( $this, 'perform_auto_verification' ) );
		add_action( 'surefeedback_hourly_verify', array( $this, 'perform_hourly_verification_update' ) );

		// Update registration option in database for parent site reference
		register_activation_hook( SUREFEEDBACK_PLUGIN_FILE, array( $this, 'register_installation' ) );
		register_deactivation_hook( SUREFEEDBACK_PLUGIN_FILE, array( $this, 'deregister_installation' ) );

		// Redirect to the options page after activating
		add_action( 'activated_plugin', array( $this, 'redirect_options_page' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( SUREFEEDBACK_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		// White label text only on plugins page
		global $pagenow;
		if ( is_admin() && 'plugins.php' === $pagenow ) {
			add_filter( 'gettext', array( $this, 'white_label' ), 20, 3 );
		}
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain('surefeedback', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

		/**
		 * Get current environment mode
		 *
		 * @return string
		 */
		public function get_environment_mode() {
			return defined( 'SUREFEEDBACK_MODE' ) ? SUREFEEDBACK_MODE : self::DEFAULT_MODE;
		}

		/**
		 * Get API URL based on environment
		 *
		 * @return string
		 */
		public function get_api_url() {
			return $this->get_environment_mode() === self::MODE_PRODUCTION 
				? self::API_URL_PRODUCTION 
				: self::API_URL_LOCAL;
		}

		/**
		 * Get App URL based on environment
		 *
		 * @return string
		 */
		public function get_app_url() {
			return $this->get_environment_mode() === self::MODE_PRODUCTION 
				? self::APP_URL_PRODUCTION 
				: self::APP_URL_LOCAL;
		}

		/**
		 * Apply white label.
		 *
		 * @param string $translated_text White label translated text.
		 * @param string $untranslated_text White label untranslated text.
		 * @param string $domain Plugin domain name.
		 *
		 * @return mixed
		 */
		public function white_label( $translated_text, $untranslated_text, $domain ) {
			global $pagenow;
			if ( ! is_admin() || 'plugins.php' !== $pagenow ) {
				return $translated_text;
			}
			// make the changes to the text.
			if ( 'surefeedback' === $domain ) { // added this check to avoid conflicting other plugins.
				switch ( $untranslated_text ) {
					case 'SureFeedback Client':
						$name = get_option( 'surefeedback_plugin_name', false );
						if ( $name ) {
							$translated_text = $name;
						}
						break;
					case 'Collect note-style feedback from your client\'s websites and sync them with your SureFeedback parent project.';
						$description = get_option( 'surefeedback_plugin_description', false );
						if ( $description ) {
							$translated_text = $description;
						}
						break;
					case 'Brainstorm Force':
						$author = get_option( 'surefeedback_plugin_author', false );
						if ( $author ) {
							$translated_text = $author;
						}
						break;
					case 'https://www.brainstormforce.com':
						$author_url = get_option( 'surefeedback_plugin_author_url', false );
						if ( $author_url ) {
							$translated_text = $author_url;
						}
						break;
					case 'http://surefeedback.com':
						$plugin_link = get_option( 'surefeedback_plugin_link', false );
						if ( $plugin_link ) {
							$translated_text = $plugin_link;
						}
						break;
					// add more items.
					
				}
			}
			return $translated_text;
		}

		/**
		 * Add settings link to plugin list table
		 *
		 * @param  array $links Existing links.
		 *
		 * @since 1.0.0
		 * @return array        Modified links
		 */
		public function add_settings_link( $links ) {
			$dashboard_link = '<a href="' . admin_url( 'admin.php?page=surefeedback#dashboard' ) . '">' . __( 'Dashboard', 'surefeedback' ) . '</a>';
			$settings_link = '<a href="' . admin_url( 'admin.php?page=surefeedback#settings' ) . '">' . __( 'Settings', 'surefeedback' ) . '</a>';
			array_push( $links, $dashboard_link, $settings_link );
			return $links;
		}

	

		/**
		 * Redirect to options page.
		 *
		 * @param string $plugin Plugin name.
		 *
		 * @return void
		 */
		public function redirect_options_page( $plugin ) {
			$connection = get_option( 'surefeedback_connection_status', false );
			if ( plugin_basename( __FILE__ ) == $plugin) {
				if(  ($connection !== 'connected') ){
					exit( wp_redirect( admin_url( 'admin.php?page=surefeedback#setup-wizard' ) ) );
				}
				else{
					exit( wp_redirect( admin_url( 'admin.php?page=surefeedback#settings' ) ) );
				}
			}
		}

		/**
		 * Add option for when plugin is active
		 *
		 * @return void
		 */
		public function register_installation() {
			update_option( 'surefeedback_installed', true );
		}

		/**
		 * Delete option when deactivated
		 *
		 * @return void
		 */
		public function deregister_installation() {
			delete_option( 'surefeedback_installed' );
		}


	/**
	 * Auto-verify script with smart scheduling and retry limits
	 * Delegates to SaaS_Client
	 *
	 * @return void
	 */
	public function auto_verify_script() {
		if ( $this->saas_client ) {
			$this->saas_client->auto_verify_script();
		}
	}

	/**
	 * Perform automatic verification with smart retry logic
	 * Delegates to SaaS_Client
	 *
	 * @return void
	 */
	public function perform_auto_verification() {
		error_log('SureFeedback: Main plugin perform_auto_verification() called');
		if ( $this->saas_client ) {
			error_log('SureFeedback: SaaS client exists, delegating to perform_auto_verification()');
			$this->saas_client->perform_auto_verification();
		} else {
			error_log('SureFeedback: ERROR - SaaS client not initialized!');
		}
	}

	/**
	 * Perform hourly verification update to keep database fresh
	 * Delegates to SaaS_Client
	 *
	 * @return void
	 */
	public function perform_hourly_verification_update() {
		if ( $this->saas_client ) {
			$this->saas_client->perform_hourly_verification_update();
		}
	}

	/**
	 * Verify script integration with SaaS platform
	 * Delegates to SaaS_Client
	 *
	 * @return array
	 */
	public function verify_script_integration() {
		error_log('SureFeedback: Main plugin verify_script_integration() called');
		if ( $this->saas_client ) {
			error_log('SureFeedback: SaaS client exists, calling verify_script_integration()');
			$result = $this->saas_client->verify_script_integration();
			error_log('SureFeedback: Verification result: ' . print_r($result, true));
			return $result;
		}
		error_log('SureFeedback: ERROR - SaaS client not initialized in verify_script_integration()');
		return array(
			'success' => false,
			'message' => 'SaaS client not initialized',
		);
	}
}

// Initialize the plugin
SureFeedback::get_instance();
