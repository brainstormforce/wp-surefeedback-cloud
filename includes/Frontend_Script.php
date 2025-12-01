<?php

/**
 * Frontend Script Loader
 *
 * @package SureFeedback
 */

namespace SureFeedback;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Script Loader class
 * Handles loading the SureFeedback SDK on the frontend
 */
class Frontend_Script {

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		// Only load on frontend
		if ( ! is_admin() ) {
			add_action( 'wp_footer', array( $this, 'enqueue_script' ) );
		}

		// Also load in admin if enabled
		if ( is_admin() && $this->should_load_in_admin() ) {
			add_action( 'admin_footer', array( $this, 'enqueue_script' ) );
		}
	}

	/**
	 * Check if script should load in admin
	 *
	 * @return bool
	 */
	private function should_load_in_admin() {
		return (bool) get_option( 'surefeedback_load_in_admin', false );
	}

	/**
	 * Check if widget should display on current page
	 *
	 * @return bool
	 */
	private function should_display_widget() {
		// Check page settings
		$current_page_id = $this->get_current_page_id();
		$page_settings   = get_option( 'surefeedback_page_settings', array() );

		if ( empty( $page_settings ) ) {
			return true; // Default: show on all pages
		}

		if ( ! $current_page_id ) {
			return true; // Show on pages without ID
		}

		// Check if this page is disabled
		if ( isset( $page_settings[ $current_page_id ] ) && false === $page_settings[ $current_page_id ] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get current page ID
	 *
	 * @return string|null
	 */
	private function get_current_page_id() {
		if ( is_front_page() ) {
			return 'home';
		}

		if ( is_home() ) {
			return 'blog';
		}

		if ( is_singular() ) {
			$post_type = get_post_type();
			$post_id   = get_the_ID();

			if ( 'page' === $post_type ) {
				return 'page_' . $post_id;
			} elseif ( 'post' === $post_type ) {
				return 'post_' . $post_id;
			} else {
				return $post_type . '_' . $post_id;
			}
		}

		if ( is_archive() ) {
			return 'archive';
		}

		if ( is_search() ) {
			return 'search';
		}

		if ( is_404() ) {
			return '404';
		}

		return null;
	}

	/**
	 * Check if user is allowed to comment
	 *
	 * @return bool
	 */
	private function is_user_allowed() {
		$allowed_roles = get_option( 'surefeedback_allowed_roles', array( 'administrator' ) );

		if ( empty( $allowed_roles ) ) {
			// Default: allow administrators
			$allowed_roles = array( 'administrator' );
		}

		if ( ! is_user_logged_in() ) {
			// Guest users - allow by default for SaaS connection
			// The SDK will handle authentication via token
			return true;
		}

		$user       = wp_get_current_user();
		$user_roles = $user->roles;

		// Check if user has any allowed role
		foreach ( $user_roles as $role ) {
			if ( in_array( $role, $allowed_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue frontend script
	 *
	 * @since 0.0.1
	 */
	public function enqueue_script() {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}

		// Check if should load
		if ( ! apply_filters( 'surefeedback_script_should_load', true ) ) {
			return;
		}

		// Check if widget should display on this page
		if ( ! $this->should_display_widget() ) {
			echo '<!-- SureFeedback: widget disabled for this page -->';
			return;
		}

		// Check if connected
		$auth_manager = new Auth_Manager();
		if ( ! $auth_manager->is_authenticated() ) {
			echo '<!-- SureFeedback: not connected -->';
			return;
		}

		// Get site ID
		$site_id = get_option( 'surefeedback_site_id', '' );
		if ( empty( $site_id ) ) {
			echo '<!-- SureFeedback: site ID not set -->';
			return;
		}

		// Get SDK base URL (Laravel API base URL without /api/v1)
		$sdk_base_url = SUREFEEDBACK_SAAS_API_BASE_URL;
		$sdk_base_url = preg_replace( '#/api/v1/?$#', '', rtrim( $sdk_base_url, '/' ) );

		// Build SDK URL
		$sdk_url = $sdk_base_url . '/sdk/ws/' . esc_attr( $site_id ) . '.js';

		// Get access token (script token)
		$access_token = get_option( 'surefeedback_access_token', '' );

		// Check if user is allowed
		$allowed = $this->is_user_allowed();

		$loaded = true;
		?>

		<script>
			(function() {
				var shouldDisplayWidget = function() {
					var pageSettings = <?php echo wp_json_encode( get_option( 'surefeedback_page_settings', array() ) ); ?>;
					var currentPageId = <?php echo wp_json_encode( $this->get_current_page_id() ); ?>;

					if (!pageSettings || Object.keys(pageSettings).length === 0) {
						return true;
					}

					if (!currentPageId) {
						return true;
					}

					if (pageSettings[currentPageId] === false) {
						return false;
					}

					return true;
				};

				if (!shouldDisplayWidget()) {
					return;
				}

				// Load SureFeedback SDK
				var script = document.createElement('script');
				script.src = '<?php echo esc_url_raw( $sdk_url ); ?>';
				script.async = true;
				script.defer = true;
				script.charset = 'UTF-8';
				
				<?php if ( $access_token ) : ?>
				script.setAttribute('data-token', '<?php echo esc_js( $access_token ); ?>');
				
				// Also pass token via URL parameter for SDK to pick up
				var urlParams = new URLSearchParams(script.src.split('?')[1] || '');
				urlParams.set('api_token', '<?php echo esc_js( $access_token ); ?>');
				script.src = script.src.split('?')[0] + '?' + urlParams.toString();
				<?php endif; ?>
				
				script.setAttribute('data-base-url', '<?php echo esc_js( $sdk_base_url ); ?>');
				
				document.head.appendChild(script);
			})();
		</script>
		<?php
	}
}
