<?php

namespace SureFeedback\Services;

defined( 'ABSPATH' ) || exit;

use SureFeedback\Repositories\SettingsRepository;
use SureFeedback\Http\Requests\UpdateSettingsRequest;
use SureFeedback\Http\Controllers\Api\VerificationController;

/**
 * Admin Service
 *
 * Handles all WordPress admin functionality including menu creation,
 * settings pages, dashboard integration, and admin-specific features.
 *
 * @package SureFeedback\App\Services
 * @author Anurag Singh <anurags@bsf.io>
 */
class AdminService {

	/**
	 * Settings Repository
	 *
	 * @var SettingsRepository
	 */
	protected $settingsRepository;

	/**
	 * Connection Repository
	 *
	 * @var \SureFeedback\Repositories\ConnectionRepository
	 */
	private $connection_repository;

	/**
	 * Menu slug
	 *
	 * @var string
	 */
	private $menu_slug = 'surefeedback';

	/**
	 * Registered menu pages
	 *
	 * @var array
	 */
	private $menu_pages = array();

	/**
	 * Current page context
	 *
	 * @var string
	 */
	private $current_page = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settingsRepository    = new SettingsRepository();
		$this->connection_repository = new \SureFeedback\Repositories\ConnectionRepository();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Admin menu and pages
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_admin_settings' ) );

		// Admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_surefeedback_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_surefeedback_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_surefeedback_verify_connection', array( $this, 'ajax_verify_connection' ) );
		add_action( 'wp_ajax_surefeedback_reset_plugin', array( $this, 'ajax_reset_plugin' ) );
		add_action( 'wp_ajax_surefeedback_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_surefeedback_import_settings', array( $this, 'ajax_import_settings' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

		// Plugin action links
		// add_filter('plugin_action_links_' . SUREFEEDBACK_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

		// Admin bar
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );

		// Dashboard widgets
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Register admin menu and subpages
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		// Connection as main menu page
		$connection_hook = add_menu_page(
			__( 'SureFeedback', 'surefeedback' ),
			__( 'SureFeedback', 'surefeedback' ),
			'manage_options',
			$this->menu_slug . '-connection',
			array( $this, 'render_connection_page' ),
			$this->get_menu_icon(),
			30
		);

		$this->menu_pages['connection'] = $connection_hook;

		// Rename the first submenu to "Connection" to match
		$connection_submenu_hook = add_submenu_page(
			$this->menu_slug . '-connection',
			__( 'Connection', 'surefeedback' ),
			__( 'Connection', 'surefeedback' ),
			'manage_options',
			$this->menu_slug . '-connection',
			array( $this, 'render_connection_page' )
		);

		// Widget Control submenu
		$widget_control_hook = add_submenu_page(
			$this->menu_slug . '-connection',
			__( 'Widget Control', 'surefeedback' ),
			__( 'Widget Control', 'surefeedback' ),
			'manage_options',
			$this->menu_slug . '-widget-control',
			array( $this, 'render_widget_control_page' )
		);

		$this->menu_pages['widget_control'] = $widget_control_hook;

		// Settings submenu
		$settings_hook = add_submenu_page(
			$this->menu_slug . '-connection',
			__( 'Settings', 'surefeedback' ),
			__( 'Settings', 'surefeedback' ),
			'manage_options',
			$this->menu_slug . '-settings',
			array( $this, 'render_settings_page' )
		);

		$this->menu_pages['settings'] = $settings_hook;

		// Add page-specific hooks
		foreach ( $this->menu_pages as $page => $hook ) {
			add_action( "load-{$hook}", array( $this, 'admin_page_load' ) );
		}
	}

	/**
	 * Initialize admin settings
	 *
	 * @return void
	 */
	public function init_admin_settings(): void {
		// Register settings sections and fields
		$this->register_general_settings();
		$this->register_connection_settings();
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only enqueue on our plugin pages
		if ( ! in_array( $hook, $this->menu_pages ) ) {
			return;
		}

		// Enqueue admin styles
		wp_enqueue_style(
			'surefeedback-admin',
			SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.css',
			array(),
			SUREFEEDBACK_VERSION
		);

		// Enqueue pre-init script first
		wp_enqueue_script(
			'surefeedback-pre-init',
			SUREFEEDBACK_PLUGIN_URL . 'resources/assets/js/pre-init.js',
			array(),
			SUREFEEDBACK_VERSION,
			false // Load in head to run early
		);

		// Ensure wp-api script is loaded (provides wpApiSettings)
		wp_enqueue_script( 'wp-api' );

		// Enqueue admin scripts
		wp_enqueue_script(
			'surefeedback-admin',
			SUREFEEDBACK_PLUGIN_URL . 'assets/dist/admin.js',
			array( 'wp-element', 'wp-api', 'wp-i18n', 'surefeedback-pre-init' ),
			SUREFEEDBACK_VERSION,
			true
		);

		// Set script translations for admin
		wp_set_script_translations(
			'surefeedback-admin',
			'surefeedback',
			SUREFEEDBACK_PLUGIN_DIR . 'languages'
		);

		// Get connection data for use in multiple places
		$connection_data = $this->get_connection_data();

		// Localize script with admin data
		wp_localize_script(
			'surefeedback-admin',
			'sureFeedbackAdmin',
			array(
				'apiUrl'                => rest_url( 'surefeedback/v1/' ),
				'rest_url'              => rest_url(),
				'rest_nonce'            => wp_create_nonce( 'wp_rest' ),
				'nonce'                 => wp_create_nonce( 'wp_rest' ),
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'             => wp_create_nonce( 'surefeedback_admin' ),
				'installer_nonce'       => wp_create_nonce( 'surefeedback_installer' ),
				'currentUser'           => wp_get_current_user(),
				'pluginUrl'             => SUREFEEDBACK_PLUGIN_URL,
				'admin_url'             => admin_url(),
				'adminUrl'              => admin_url( 'admin.php?page=' . $this->menu_slug ),
				'settings'              => $this->get_admin_settings(),
				'strings'               => $this->get_localized_strings(),
				'capabilities'          => $this->get_user_capabilities(),
				'environment'           => $this->get_environment_info(),
				// Add image URLs for components
				'icon_url'              => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/cropped-projecthuddle-favicon-1-192x192.svg',
				'welcome_url'           => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome.png',
				'welcome_background'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome_background.png',
				'surefeedback_icon'     => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surefeedback.svg',
				'welcome'               => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/welcome.png',
				'thumbs'                => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/thumbs.svg',
				'rocket'                => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/rocket.svg',
				'admin'                 => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/admin.svg',
				'docs'                  => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/docs.svg',
				'footer'                => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/footer.png',
				'connection_url'        => SUREFEEDBACK_PLUGIN_URL . 'assets/connection.svg',
				'configure'             => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/configure_banner.png',
				'settings_url'          => SUREFEEDBACK_PLUGIN_URL . 'assets/settings.svg',
				'settings_selected_url' => SUREFEEDBACK_PLUGIN_URL . 'assets/settings_unselected.svg',
				'label_url'             => SUREFEEDBACK_PLUGIN_URL . 'assets/label.svg',
				'label_selected_url'    => SUREFEEDBACK_PLUGIN_URL . 'assets/label_selected.svg',
				// Plugin icons for ExtendWebsite
				'surerank_icon'         => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surerank.svg',
				'surecart_icon'         => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/surecart.svg',
				'sureforms_icon'        => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/sureforms.svg',
				'presto_player_icon'    => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/pplayer.svg',
				'suretriggers_icon'     => SUREFEEDBACK_PLUGIN_URL . 'assets/images/settings/rocket.svg',
				// Connection data
				'connection'            => $connection_data,
			)
		);

		// Enqueue WordPress media uploader on settings page
		if ( strpos( $hook, 'settings' ) !== false ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Render main admin page
	 *
	 * @return void
	 */
	public function render_main_page(): void {
		$this->current_page = 'dashboard';

		echo '<div class="wrap" style="margin: 0; padding: 0; max-width: none;">';
		echo '<div id="surefeedback-admin-dashboard" style="margin: 0; padding: 0; width: 100%;"></div>';
		echo '</div>';
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$this->current_page = 'settings';
		echo '<div class="wrap">';
		echo '<div id="surefeedback-admin-settings"></div>';
		echo '</div>';
	}

	/**
	 * Render connection page
	 *
	 * @return void
	 */
	public function render_connection_page(): void {
		$this->current_page = 'connection';
		echo '<div class="wrap">';
		echo '<div id="surefeedback-admin-connection"></div>';
		echo '</div>';
	}

	/**
	 * Render widget control page
	 *
	 * @return void
	 */
	public function render_widget_control_page(): void {
		$this->current_page = 'widget-control';
		echo '<div class="wrap">';
		echo '<div id="surefeedback-admin-widget-control"></div>';
		echo '</div>';
	}

	/**
	 * Admin page load handler
	 *
	 * @return void
	 */
	public function admin_page_load(): void {
		// Admin page loaded - ready for customizations
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	public function display_admin_notices(): void {
		// Check connection status
		$parent_url   = $this->connection_repository->getParentUrl() ?: '';
		$access_token = $this->connection_repository->getAccessToken() ?: '';
		$is_connected = ! empty( $parent_url ) && ! empty( $access_token );

		// Connection status notice
		if ( ! $is_connected && $this->is_plugin_page() ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p>';
			echo esc_html__( 'SureFeedback is not connected to a parent site. ', 'surefeedback' );
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-connection' ) ) . '">';
			echo esc_html__( 'Connect now', 'surefeedback' );
			echo '</a>';
			echo '</p>';
			echo '</div>';
		}

		// Verification status notice (disabled for now - verification_status not in ConnectionRepository)
		/*
		if ($connectionData['verification_status'] === 'failed' && $this->is_plugin_page()) {
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p>';
			echo esc_html__('SureFeedback verification failed. Please check your connection settings.', 'surefeedback');
			echo '</p>';
			echo '</div>';
		}
		*/

		// Show success notices
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// This is a read-only display of GET parameter, not processing form data
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );

			$messages = array(
				'settings_saved'        => __( 'Settings saved successfully.', 'surefeedback' ),
				'connection_successful' => __( 'Connection established successfully.', 'surefeedback' ),
				'disconnected'          => __( 'Disconnected successfully.', 'surefeedback' ),
				'reset_complete'        => __( 'Plugin reset completed.', 'surefeedback' ),
			);

			if ( isset( $messages[ $message ] ) ) {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html( $messages[ $message ] ) . '</p>';
				echo '</div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links Existing action links
	 * @return array Modified action links
	 */
	// public function add_plugin_action_links(array $links): array
	// {
	// $plugin_links = [
	// '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug . '-connection')) . '">' .
	// esc_html__('Connection', 'surefeedback') . '</a>',
	// ];

	// return array_merge($plugin_links, $links);
	// }

	/**
	 * Add admin bar menu
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar object
	 * @return void
	 */
	public function add_admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$parent_url   = $this->connection_repository->getParentUrl() ?: '';
		$access_token = $this->connection_repository->getAccessToken() ?: '';
		$is_connected = ! empty( $parent_url ) && ! empty( $access_token );
		$status_class = $is_connected ? 'connected' : 'disconnected';

		$wp_admin_bar->add_node(
			array(
				'id'    => 'surefeedback',
				'title' => '<span class="ab-icon dashicons-feedback"></span><span class="ab-label">SureFeedback</span>',
				'href'  => admin_url( 'admin.php?page=' . $this->menu_slug . '-connection' ),
				'meta'  => array(
					'class' => 'surefeedback-admin-bar ' . $status_class,
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'surefeedback',
				'id'     => 'surefeedback-settings',
				'title'  => __( 'Settings', 'surefeedback' ),
				'href'   => admin_url( 'admin.php?page=' . $this->menu_slug . '-settings' ),
			)
		);

		if ( $is_connected ) {
			$parent_url = $this->connection_repository->getParentUrl() ?: '';
			if ( ! empty( $parent_url ) ) {
				$wp_admin_bar->add_node(
					array(
						'parent' => 'surefeedback',
						'id'     => 'surefeedback-parent',
						'title'  => __( 'Open Parent Dashboard', 'surefeedback' ),
						'href'   => $parent_url,
						'meta'   => array( 'target' => '_blank' ),
					)
				);
			}
		}
	}

	/**
	 * Add dashboard widget
	 *
	 * @return void
	 */
	public function add_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'surefeedback_dashboard_widget',
			__( 'SureFeedback Status', 'surefeedback' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget
	 *
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$parent_url   = $this->connection_repository->getParentUrl() ?: '';
		$access_token = $this->connection_repository->getAccessToken() ?: '';
		$is_connected = ! empty( $parent_url ) && ! empty( $access_token );

		echo '<div class="surefeedback-dashboard-widget">';

		// Connection status
		echo '<p><strong>' . esc_html__( 'Connection Status:', 'surefeedback' ) . '</strong> ';
		if ( $is_connected ) {
			echo '<span style="color: green;">' . esc_html__( 'Connected', 'surefeedback' ) . '</span>';
		} else {
			echo '<span style="color: red;">' . esc_html__( 'Disconnected', 'surefeedback' ) . '</span>';
		}
		echo '</p>';

		// Last verification
		$last_verification = $this->connection_repository->getLastVerification() ?: '';
		if ( ! empty( $last_verification ) ) {
			echo '<p><strong>' . esc_html__( 'Last Verification:', 'surefeedback' ) . '</strong> ';
			echo esc_html( human_time_diff( strtotime( $last_verification ), time() ) . ' ago' );
			echo '</p>';
		}

		// Site name
		$site_name = $this->connection_repository->getSiteName() ?: '';
		if ( ! empty( $site_name ) ) {
			echo '<p><strong>' . esc_html__( 'Site Name:', 'surefeedback' ) . '</strong> ';
			echo esc_html( $site_name );
			echo '</p>';
		}

		// Quick actions
		echo '<p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->menu_slug ) ) . '" class="button">';
		echo esc_html__( 'View Dashboard', 'surefeedback' );
		echo '</a> ';

		if ( ! $is_connected ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '-connection' ) ) . '" class="button-primary">';
			echo esc_html__( 'Connect Now', 'surefeedback' );
			echo '</a>';
		}
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Register general settings
	 *
	 * @return void
	 */
	private function register_general_settings(): void {
		// Settings managed via webhook
	}

	/**
	 * Register connection settings
	 *
	 * @return void
	 */
	private function register_connection_settings(): void {
		register_setting( 'surefeedback_connection', 'surefeedback_parent_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'surefeedback_connection', 'surefeedback_access_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	/**
	 * Sanitize settings array recursively
	 *
	 * @param array $settings Settings array to sanitize
	 * @return array Sanitized settings array
	 */
	private function sanitize_settings_array( array $settings ): array {
		$sanitized = array();
		foreach ( $settings as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_settings_array( $value );
			} elseif ( is_string( $value ) ) {
				$sanitized[ $sanitized_key ] = sanitize_text_field( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) ) {
				$sanitized[ $sanitized_key ] = $value;
			} else {
				$sanitized[ $sanitized_key ] = sanitize_text_field( (string) $value );
			}
		}
		return $sanitized;
	}


	/**
	 * Get menu icon
	 *
	 * @return string
	 */
	private function get_menu_icon(): string {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 192 192"><path d="M0 0 C1.31319717 -0.00511093 2.62639435 -0.01022186 3.97938538 -0.01548767 C7.56207495 -0.02881203 11.14465861 -0.02946377 14.72736812 -0.02692437 C17.72672093 -0.02585031 20.7260527 -0.03074906 23.72540098 -0.03552979 C30.80523144 -0.04660201 37.88500883 -0.04707511 44.96484375 -0.04101562 C52.25055248 -0.03497552 59.53606811 -0.04729294 66.82174391 -0.06858569 C73.09461788 -0.08623935 79.36743059 -0.09219718 85.64032847 -0.08894795 C89.37912254 -0.08714113 93.11777881 -0.08971094 96.85655022 -0.10366249 C101.02819101 -0.11864879 105.19944209 -0.10923791 109.37109375 -0.09765625 C110.59786835 -0.10529999 111.82464294 -0.11294373 113.08859253 -0.12081909 C121.50955746 -0.06923806 129.5753935 0.64467483 136.12524414 6.4621582 C136.75172852 6.99196289 137.37821289 7.52176758 138.02368164 8.06762695 C142.7432565 14.10056767 143.19487578 20.66872706 143.17016602 28.02026367 C143.17863556 28.96121887 143.1871051 29.90217407 143.1958313 30.87164307 C143.2191043 33.96185061 143.22022574 37.05155739 143.21899414 40.1418457 C143.22610159 42.30364173 143.23380144 44.46543587 143.24206543 46.62722778 C143.25548803 51.15119678 143.25708897 55.67499132 143.2512207 60.19897461 C143.24532707 65.97798476 143.27591342 71.75615411 143.31633759 77.53500175 C143.34226749 81.99789896 143.34508717 86.46059514 143.34144592 90.92355728 C143.34313223 93.05328611 143.35272726 95.18302471 143.37059021 97.31267929 C143.53279216 119.04047805 143.53279216 119.04047805 137.75024414 126.2746582 C135.39868164 128.52856445 135.39868164 128.52856445 133.12524414 129.8371582 C132.36985352 130.29219727 131.61446289 130.74723633 130.83618164 131.21606445 C127.4764353 132.92110426 124.88638545 133.39284305 121.17599487 133.34539795 C120.23722504 133.33639969 119.2984552 133.32740143 118.33123779 133.31813049 C117.30274719 133.30346237 116.27425659 133.28879425 115.21459961 133.27368164 C112.96483892 133.2509107 110.7150725 133.22870028 108.46530151 133.20697021 C107.26906033 133.19418785 106.07281914 133.18140549 104.84032822 133.16823578 C97.15823235 133.09765969 89.47576179 133.09141761 81.79339409 133.07438278 C75.05286669 133.05684296 68.31390107 133.01556925 61.57397461 132.92163086 C55.04702792 132.83330063 48.52257562 132.80954031 41.99509048 132.82801056 C39.51934288 132.82373724 37.04351638 132.79673466 34.56829453 132.7455368 C19.56799067 132.45058168 9.51592626 133.46996311 -1.76657104 143.92047119 C-2.6278039 144.75891445 -2.6278039 144.75891445 -3.50643539 145.61429596 C-6.30502903 148.27971479 -9.34496355 150.6305367 -12.37475586 153.0246582 C-13.83268555 154.22348633 -13.83268555 154.22348633 -15.32006836 155.4465332 C-18.74780585 157.5854414 -21.23461046 158.08681514 -25.24975586 158.2746582 C-29.26494649 156.75949193 -30.85343939 155.86913291 -33.24975586 152.2746582 C-33.50423145 149.83513355 -33.50423145 149.83513355 -33.51045227 146.88056946 C-33.51753708 145.7605101 -33.52462189 144.64045074 -33.53192139 143.4864502 C-33.52944397 142.25680542 -33.52696655 141.02716064 -33.52441406 139.76025391 C-33.52952499 138.46568771 -33.53463593 137.17112152 -33.53990173 135.83732605 C-33.55330794 132.28333225 -33.55386823 128.7294447 -33.55133843 125.17543054 C-33.55026902 122.20605812 -33.55515174 119.23670722 -33.55994385 116.26733941 C-33.57104634 109.25714873 -33.57147342 102.24701163 -33.56542969 95.23681641 C-33.55939919 88.01537946 -33.57167556 80.7941378 -33.59299976 73.57273418 C-33.61064792 67.36706723 -33.61661174 61.16146225 -33.61336201 54.95577115 C-33.61155287 51.25214566 -33.61414759 47.54865922 -33.62807655 43.84505653 C-33.64305008 39.70858853 -33.63365673 35.57251405 -33.62207031 31.43603516 C-33.62971405 30.21651154 -33.63735779 28.99698792 -33.64523315 27.74050903 C-33.59843974 20.17438663 -33.18326517 13.91984729 -28.26928711 7.8137207 C-27.5409668 7.18208008 -26.81264648 6.55043945 -26.06225586 5.8996582 C-25.34682617 5.25254883 -24.63139648 4.60543945 -23.89428711 3.9387207 C-16.65669055 -0.61551288 -8.24981533 -0.01663269 0 0 Z M5.81274414 55.7746582 C3.70947907 58.32407041 2.8806229 59.82734416 3.14477539 63.16137695 C6.35452435 74.36443621 16.78565764 83.97114648 26.36743164 89.99731445 C40.28346515 97.45722094 55.13067315 99.36013686 70.31640625 95.03417969 C85.53134976 90.38772336 96.5563142 80.82513781 104.75024414 67.2746582 C106.31454662 64.14605325 106.10651698 61.71862896 105.75024414 58.2746582 C103.08746412 55.04704605 101.52616553 54.36087169 97.37524414 53.8996582 C91.80703014 56.01173937 89.84943932 60.38272015 86.75024414 65.2746582 C81.25650842 73.22896852 73.01961553 77.78167323 63.75024414 80.2746582 C50.88814403 81.81140788 40.16939345 79.86791854 29.37524414 72.4621582 C24.3816814 68.43208371 20.94110528 63.38747635 17.81274414 57.8371582 C15.89331471 54.97557904 15.89331471 54.97557904 12.31274414 54.1496582 C8.72998473 54.02374465 8.72998473 54.02374465 5.81274414 55.7746582 Z " fill="#7C818C" transform="translate(41.249755859375,16.725341796875)"/></svg>' );
	}

	/**
	 * Get admin settings for JavaScript
	 *
	 * @return array
	 */
	private function get_admin_settings(): array {
		$settingsData = $this->settingsRepository->getSettings();

		return array(
			'connected'          => $this->connection_repository->isConnected(),
			'parentUrl'          => $this->connection_repository->getParentUrl() ?: '',
			'siteId'             => $this->connection_repository->getSiteId() ?: '',
			'verificationStatus' => null,
			'lastVerification'   => $this->connection_repository->getLastVerification() ?: '',
			'widgetEnabled'      => $settingsData['general']['widget_enabled'] ?? true,
			'debugMode'          => $settingsData['general']['debug_mode'] ?? false,
		);
	}

	/**
	 * Get localized strings for JavaScript
	 *
	 * @return array
	 */
	private function get_localized_strings(): array {
		return array(
			'connecting'   => __( 'Connecting...', 'surefeedback' ),
			'connected'    => __( 'Connected', 'surefeedback' ),
			'disconnected' => __( 'Disconnected', 'surefeedback' ),
			'testing'      => __( 'Testing connection...', 'surefeedback' ),
			'saving'       => __( 'Saving...', 'surefeedback' ),
			'saved'        => __( 'Saved', 'surefeedback' ),
			'error'        => __( 'Error', 'surefeedback' ),
			'success'      => __( 'Success', 'surefeedback' ),
			'confirmReset' => __( 'Are you sure you want to reset all settings? This action cannot be undone.', 'surefeedback' ),
		);
	}

	/**
	 * Get user capabilities for JavaScript
	 *
	 * @return array
	 */
	private function get_user_capabilities(): array {
		return array(
			'manage_options' => current_user_can( 'manage_options' ),
			'edit_posts'     => current_user_can( 'edit_posts' ),
			'upload_files'   => current_user_can( 'upload_files' ),
		);
	}

	/**
	 * Get environment info for JavaScript
	 *
	 * @return array
	 */
	private function get_environment_info(): array {
		return array(
			'pluginVersion'    => SUREFEEDBACK_VERSION,
			'wordpressVersion' => get_bloginfo( 'version' ),
			'phpVersion'       => PHP_VERSION,
			'environment'      => defined( 'SUREFEEDBACK_MODE' ) ? SUREFEEDBACK_MODE : 'development',
		);
	}

	/**
	 * Check if current page is a plugin page
	 *
	 * @return bool
	 */
	private function is_plugin_page(): bool {
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, $this->menu_slug ) !== false;
	}

	/**
	 * AJAX handler for saving settings
	 *
	 * @return void
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'surefeedback_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'surefeedback' ) );
		}

		try {
			// Validate request data
			$request       = new UpdateSettingsRequest( $_POST );
			$validatedData = $request->validated();

			// Save settings through repository
			$this->settingsRepository->updateSettings( $validatedData );

			wp_send_json_success(
				array(
					'message'  => __( 'Settings saved successfully', 'surefeedback' ),
					'settings' => $validatedData,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to save settings', 'surefeedback' ),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX handler for testing connection
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'surefeedback_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'surefeedback' ) );
		}

		// Connection testing is handled via webhook
		wp_send_json_success(
			array(
				'message'   => __( 'Connection is managed via webhook', 'surefeedback' ),
				'connected' => $this->connection_repository->isConnected(),
			)
		);
	}

	/**
	 * AJAX handler for verifying connection with API
	 *
	 * @return void
	 */
	public function ajax_verify_connection(): void {
		check_ajax_referer( 'surefeedback_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'surefeedback' ) );
		}

		try {
			// Use the verification controller
			$verification_controller = new \SureFeedback\Http\Controllers\Api\VerificationController();
			$request                 = new \WP_REST_Request();

			$result = $verification_controller->verify_connection( $request );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
					)
				);
			} else {
				$data = $result->get_data();
				if ( $data['success'] ) {
					wp_send_json_success( $data );
				} else {
					wp_send_json_error( $data );
				}
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX handler for resetting plugin
	 *
	 * @return void
	 */
	public function ajax_reset_plugin(): void {
		check_ajax_referer( 'surefeedback_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'surefeedback' ) );
		}

		// Reset connection-specific options only
		$options_to_delete = array(
			'surefeedback_access_token',
			'surefeedback_parent_url',
			'surefeedback_site_id',
			'surefeedback_last_verification',
		);

		foreach ( $options_to_delete as $option ) {
			delete_option( $option );
		}

		// Clear scheduled events
		wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
		wp_clear_scheduled_hook( 'surefeedback_hourly_verify' );

		wp_send_json_success(
			array(
				'message' => __( 'Plugin reset successfully', 'surefeedback' ),
			)
		);
	}

	/**
	 * AJAX handler for exporting settings
	 *
	 * @return void
	 */
	public function ajax_export_settings(): void {
		check_ajax_referer( 'surefeedback_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'surefeedback' ) );
		}

		// Get all settings through repositories
		$settings = $this->settingsRepository->getSettings();

		// Combine settings for export
		$exportData = array_merge(
			$settings,
			array(
				'parent_url' => $this->connection_repository->getParentUrl() ?: '',
				'site_id'    => $this->connection_repository->getSiteId() ?: '',
			)
		);

		wp_send_json_success(
			array(
				'settings'    => $exportData,
				'exported_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * AJAX handler for importing settings
	 *
	 * @return void
	 */
	public function ajax_import_settings(): void {
		check_ajax_referer( 'surefeedback_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'surefeedback' ) );
		}

		try {
			// Nonce already verified by check_ajax_referer
			// Get POST data using filter_input to avoid direct $_POST access warning
			// Then sanitize immediately
			$raw_settings = filter_input( INPUT_POST, 'settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( null === $raw_settings || false === $raw_settings ) {
				$settings = array();
			} else {
				$raw_settings = wp_unslash( $raw_settings );
				// Ensure it's an array and sanitize recursively
				if ( is_array( $raw_settings ) ) {
					$settings = $this->sanitize_settings_array( $raw_settings );
				} else {
					$settings = array();
				}
			}

			if ( empty( $settings ) ) {
				wp_send_json_error( __( 'No settings provided', 'surefeedback' ) );
			}

			// Separate settings and connection data
			$settingsData   = array();
			$connectionData = array();

			foreach ( $settings as $key => $value ) {
				if ( in_array( $key, array( 'parent_url', 'site_id' ) ) ) {
					$connectionData[ $key ] = $value;
				} else {
					$settingsData[ $key ] = $value;
				}
			}

			// Import through repositories
			if ( ! empty( $settingsData ) ) {
				$this->settingsRepository->updateSettings( $settingsData );
			}

			// Connection data is managed via webhook, not imported

			wp_send_json_success(
				array(
					'message'  => __( 'Settings imported successfully', 'surefeedback' ),
					'imported' => $settingsData,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to import settings', 'surefeedback' ),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get connection data for frontend
	 *
	 * @return array
	 */
	private function get_connection_data(): array {
		// Get connection settings from repository
		$parent_url   = $this->connection_repository->getParentUrl() ?: '';
		$site_id      = $this->connection_repository->getSiteId() ?: '';
		$access_token = $this->connection_repository->getAccessToken() ?: '';

		// Use constants for URLs (Sigmize pattern)
		$app_url = SUREFEEDBACK_APP_BASE_URL;
		$api_url = SUREFEEDBACK_API_BASE_URL;

		// Build site data (needed for both connected and initial connection states)
		$site_data = array(
			'site_name'      => get_bloginfo( 'name' ),
			'domain'         => home_url(),
			'site_url'       => home_url(), // Always include site_url to match Next.js types
			'wp_version'     => get_bloginfo( 'version' ),
			'plugin_version' => SUREFEEDBACK_VERSION,
			'admin_email'    => get_option( 'admin_email' ),
			'language'       => get_locale(),
			'timezone'       => get_option( 'timezone_string' ) ?: 'UTC',
			'theme'          => get_option( 'current_theme' ) ?: wp_get_theme()->get( 'Name' ),
			'active_plugins' => $this->get_active_plugins_list(),
		);

		// Determine if connected - check for essential connection data
		// A site is only considered connected if it has essential tokens (access_token and site_id)
		$is_connected = ! empty( $access_token ) && ! empty( $site_id );

		// Get verification status
		$site_connected    = $this->connection_repository->getSiteConnected();
		$is_fully_verified = $this->connection_repository->getIsFullyVerified( 0 );
		$last_verification = $this->connection_repository->getLastVerification() ?: '';

		// Add site_id if connected (for internal use)
		if ( $is_connected ) {
			$site_data['site_id'] = $site_id;
		}

		$connection_data = array(
			'connected'         => $is_connected, // Boolean for JavaScript compatibility
			'site_connected'    => $site_connected, // Site webhook connection status
			'is_fully_verified' => $is_fully_verified, // Verification status: 0=PENDING, 1=CONNECTED, 2=DISCONNECTED
			'last_verification' => $last_verification, // Last verification timestamp
			'connection_status' => $this->get_connection_status( $site_connected, $is_fully_verified ), // String status for UI
			'app_url'           => $app_url,
			'api_url'           => $api_url,
			'parent_url'        => $parent_url,
			'callback_url'      => admin_url( 'admin.php?page=surefeedback-connection' ),
			'environment'       => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'development' : 'production' ),
			'site_data'         => $site_data,
			'site_id'           => $site_id,
			'access_token'      => $access_token, // Full access token for API calls
			'site_token'        => $access_token, // Alias for compatibility with verification service
		);

		return $connection_data;
	}

	/**
	 * Get connection status string based on verification flags
	 *
	 * @param bool $site_connected
	 * @param int  $is_fully_verified
	 * @return string
	 */
	private function get_connection_status( bool $site_connected, int $is_fully_verified ): string {
		// Map connection status based on both site_connected and is_fully_verified:
		// site_connected = 0 → not_connected (show NotConnected component)
		// site_connected = 1 AND is_fully_verified = 0 → not_verified (show UnverifiedState component)
		// site_connected = 1 AND is_fully_verified = 1 → connected (show Connected component)
		// site_connected = 1 AND is_fully_verified = 2 → not_verified (show UnverifiedState component)

		if ( ! $site_connected ) {
			return 'not_connected';
		}

		if ( $site_connected && $is_fully_verified === 1 ) {
			return 'connected';
		}

		if ( $site_connected && ( $is_fully_verified === 0 || $is_fully_verified === 2 ) ) {
			return 'not_verified';
		}

		// Default fallback
		return 'not_connected';
	}

	/**
	 * Get list of active plugins
	 *
	 * @return array
	 */
	private function get_active_plugins_list(): array {
		// Get active plugins
		$active_plugins = get_option( 'active_plugins', array() );

		// Extract plugin folder/file names
		$plugin_names = array();
		foreach ( $active_plugins as $plugin ) {
			// Extract plugin folder name (e.g., "surefeedback/surefeedback.php" -> "surefeedback")
			$plugin_parts = explode( '/', $plugin );
			if ( isset( $plugin_parts[0] ) ) {
				$plugin_names[] = $plugin_parts[0];
			}
		}

		// Remove duplicates and return
		return array_unique( $plugin_names );
	}
}
