<?php
/**
 * Admin Menu class
 *
 * @package SureFeedback
 */

namespace SureFeedback\Admin;

use SureFeedback\Auth_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Menu class
 */
class Admin_Menu {

	/**
	 * Menu slug
	 *
	 * @var string
	 */
	private $menu_slug = 'surefeedback-cloud';

	/**
	 * Auth manager instance
	 *
	 * @var Auth_Manager
	 */
	private $auth_manager;

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->auth_manager = new Auth_Manager();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Get menu icon
	 *
	 * @since 0.0.1
	 * @return string
	 */
	private function get_menu_icon() {
		$icon_path = SUREFEEDBACK_PLUGIN_DIR . 'assets/images/settings/surefeedback-icon.svg';

		if ( file_exists( $icon_path ) ) {
			$svg = file_get_contents( $icon_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return 'dashicons-feedback';
	}

	/**
	 * Register admin menu
	 *
	 * @since 0.0.1
	 */
	public function register_menu() {
		add_menu_page(
			__( 'SureFeedback', 'surefeedback-cloud' ),
			__( 'SureFeedback', 'surefeedback-cloud' ),
			'manage_options',
			$this->menu_slug . '-dashboard',
			array( $this, 'render_dashboard_page' ),
			$this->get_menu_icon(),
			30
		);

		add_submenu_page(
			$this->menu_slug . '-dashboard',
			__( 'Connections', 'surefeedback-cloud' ),
			__( 'Connections', 'surefeedback-cloud' ),
			'manage_options',
			$this->menu_slug . '-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			$this->menu_slug . '-dashboard',
			__( 'Widget Control', 'surefeedback-cloud' ),
			__( 'Widget Control', 'surefeedback-cloud' ),
			'manage_options',
			$this->menu_slug . '-widget-control',
			array( $this, 'render_widget_control_page' )
		);

		add_submenu_page(
			$this->menu_slug . '-dashboard',
			__( 'Settings', 'surefeedback-cloud' ),
			__( 'Settings', 'surefeedback-cloud' ),
			'manage_options',
			$this->menu_slug . '-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 0.0.1
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, $this->menu_slug ) === false ) {
			return;
		}

		$admin_css_path = SUREFEEDBACK_PLUGIN_PATH . 'assets/dist/admin.css';
		$admin_css_url  = SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.css';
		$css_version    = file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : SUREFEEDBACK_VERSION;

		wp_enqueue_style(
			'surefeedback-admin',
			$admin_css_url,
			array(),
			$css_version
		);

		$admin_js_path = SUREFEEDBACK_PLUGIN_PATH . 'assets/js/admin.js';
		$admin_js_url  = SUREFEEDBACK_PLUGIN_URL . 'assets/js/admin.js';
		$js_version    = file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : SUREFEEDBACK_VERSION;

		wp_enqueue_script(
			'surefeedback-admin',
			$admin_js_url,
			array(),
			$js_version,
			true
		);

		$connection_id       = get_option( 'surefeedback_connection_id', '' );
		$site_id             = get_option( 'surefeedback_site_id', '' );
		$site_token          = get_option( 'surefeedback_access_token', '' );
		$site_url            = home_url();
		$admin_url           = admin_url();
		$app_url             = SUREFEEDBACK_API_BASE_URL;
		$verification_status = get_option( 'surefeedback_verification_status', 'unverified' );

		$connection_data = array(
			'connection_id' => $connection_id,
			'site_id'       => $site_id,
			'site_token'    => $site_token,
			'access_token'  => $site_token,
			'app_url'       => $app_url,
			'connected'     => $this->auth_manager->is_authenticated(),
			'site_data'     => array(
				'site_url' => $site_url,
			),
		);

		wp_localize_script(
			'surefeedback-admin',
			'surefeedbackAdmin',
			array(
				'apiUrl'              => rest_url( 'surefeedback/v1/' ),
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'rest_nonce'          => wp_create_nonce( 'wp_rest' ),
				'isConnected'         => $this->auth_manager->is_authenticated(),
				'authUrl'             => $this->auth_manager->get_auth_url(),
				'hasAuthError'        => $this->auth_manager->has_auth_error(),
				'pluginUrl'           => SUREFEEDBACK_PLUGIN_URL,
				'admin_url'           => $admin_url,
				'appUrl'              => $app_url,
				'siteUrl'             => $site_url,
				'connectionId'        => $connection_id,
				'siteId'              => $site_id,
				'site_token'          => $site_token,
				'verification_status' => $verification_status,
				'connection'          => $connection_data,
				'settings'            => array(
					'roles' => get_option( 'surefeedback_allowed_roles', array( 'administrator' ) ),
				),
				'availableRoles'      => $this->get_available_roles(),
				'surefeedback_icon'   => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surefeedback-logo-img.svg',
				'welcome_background'  => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome_background.png',
				'welcome'             => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome.png',
				'thumbs'              => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/thumbs.svg',
				'rocket'              => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/rocket.svg',
				'admin'               => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/admin.svg',
				'docs'                => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/docs.svg',
				'footer'              => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/footer.png',
			)
		);

		wp_add_inline_script(
			'surefeedback-admin',
			'if (typeof window.surefeedbackAdmin !== "undefined" && typeof window.sureFeedbackAdmin === "undefined") {
				window.sureFeedbackAdmin = window.surefeedbackAdmin;
			}',
			'after'
		);
	}

	/**
	 * Get available WordPress roles
	 *
	 * @return array
	 */
	private function get_available_roles() {
		$wp_roles = wp_roles()->get_names();
		$roles    = array();

		foreach ( $wp_roles as $role_key => $role_name ) {
			$roles[] = array(
				'name'  => $role_key,
				'label' => $role_name,
			);
		}

		return $roles;
	}

	/**
	 * Render dashboard page
	 *
	 * @since 0.0.1
	 */
	public function render_dashboard_page() {
		$is_connected = $this->auth_manager->is_authenticated();
		$has_error    = $this->auth_manager->has_auth_error();
		$auth_url     = $this->auth_manager->get_auth_url();

		?>
		<div class="wrap surefeedback-admin">

			<?php if ( $has_error ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Authentication failed. Please try again.', 'surefeedback-cloud' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="surefeedback-dashboard-app" data-page="connections"></div>
		</div>
		<?php
	}

	/**
	 * Render widget control page
	 *
	 * @since 0.0.1
	 */
	public function render_widget_control_page() {
		$is_connected = $this->auth_manager->is_authenticated();
		$has_error    = $this->auth_manager->has_auth_error();

		?>
		<div class="wrap surefeedback-admin">

			<?php if ( $has_error ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Authentication failed. Please try again.', 'surefeedback-cloud' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="surefeedback-dashboard-app" data-page="widget-control"></div>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 *
	 * @since 0.0.1
	 */
	public function render_settings_page() {
		$is_connected = $this->auth_manager->is_authenticated();
		$has_error    = $this->auth_manager->has_auth_error();

		?>
		<div class="wrap surefeedback-admin">
			<?php if ( $has_error ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Authentication failed. Please try again.', 'surefeedback-cloud' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="surefeedback-dashboard-app" data-page="settings"></div>
		</div>
		<?php
	}
}
