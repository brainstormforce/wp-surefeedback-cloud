<?php
/**
 * Admin API endpoints for Vue.js frontend
 *
 * @package SureFeedback Child
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SureFeedback_Admin_API' ) ) :
	/**
	 * Admin API Class for handling Vue.js frontend requests
	 */
	class SureFeedback_Admin_API {
		
		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_admin_routes' ) );
		}

		/**
		 * Register admin REST API routes
		 */
		public function register_admin_routes() {
			register_rest_route(
				'surefeedback/v1',
				'/settings',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				)
			);

			register_rest_route(
				'surefeedback/v1',
				'/settings/general',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_general_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'surefeedback_role_can_comment' => array(
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'sanitize_callback' => array( $this, 'sanitize_roles' ),
						),
						'surefeedback_guest_comments_enabled' => array(
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						'surefeedback_admin' => array(
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				)
			);

			register_rest_route(
				'surefeedback/v1',
				'/settings/connection',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_connection_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'surefeedback_id' => array(
							'type'              => array( 'integer', 'string' ),
							'sanitize_callback' => array( $this, 'sanitize_project_id' ),
						),
						'surefeedback_api_key' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'surefeedback_access_token' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'surefeedback_parent_url' => array(
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
						'surefeedback_signature' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);

			register_rest_route(
				'surefeedback/v1',
				'/settings/white-label',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_white_label_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'surefeedback_plugin_name' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'surefeedback_plugin_description' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'surefeedback_plugin_author' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'surefeedback_plugin_author_url' => array(
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
						'surefeedback_plugin_link' => array(
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				)
			);

			register_rest_route(
				'surefeedback/v1',
				'/verify-integration',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'verify_integration' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				)
			);

			register_rest_route(
				'surefeedback/v1',
				'/disconnect',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'disconnect_site' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				)
			);
		}

		/**
		 * Check admin permissions
		 */
		public function check_admin_permissions() {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Get all settings
		 */
		public function get_settings( $request ) {
			$general_settings = array(
				'surefeedback_role_can_comment'        => get_option( 'surefeedback_role_can_comment', array() ),
				'surefeedback_guest_comments_enabled'  => (bool) get_option( 'surefeedback_guest_comments_enabled', false ),
				'surefeedback_admin'                   => (bool) get_option( 'surefeedback_admin', false ),
			);

			$connection_settings = array(
				'surefeedback_id'           => get_option( 'surefeedback_id', '' ),
				'surefeedback_api_key'      => get_option( 'surefeedback_api_key', '' ),
				'surefeedback_access_token' => get_option( 'surefeedback_access_token', '' ),
				'surefeedback_parent_url'   => get_option( 'surefeedback_parent_url', '' ),
				'surefeedback_signature'    => get_option( 'surefeedback_signature', '' ),
				'surefeedback_installed'    => (bool) get_option( 'surefeedback_installed', false ),
			);

			$white_label_settings = array(
				'surefeedback_plugin_name'        => get_option( 'surefeedback_plugin_name', '' ),
				'surefeedback_plugin_description' => get_option( 'surefeedback_plugin_description', '' ),
				'surefeedback_plugin_author'      => get_option( 'surefeedback_plugin_author', '' ),
				'surefeedback_plugin_author_url'  => get_option( 'surefeedback_plugin_author_url', '' ),
				'surefeedback_plugin_link'        => get_option( 'surefeedback_plugin_link', '' ),
			);

			$connection_status = $this->get_connection_status();
			$available_roles   = $this->get_available_roles();

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'general'          => $general_settings,
						'connection'       => $connection_settings,
						'whiteLabel'       => $white_label_settings,
						'connectionStatus' => $connection_status,
						'availableRoles'   => $available_roles,
					),
				)
			);
		}

		/**
		 * Save general settings
		 */
		public function save_general_settings( $request ) {
			$params = $request->get_params();

			$updated = 0;

			if ( isset( $params['surefeedback_role_can_comment'] ) ) {
				update_option( 'surefeedback_role_can_comment', $params['surefeedback_role_can_comment'] );
				$updated++;
			}

			if ( isset( $params['surefeedback_guest_comments_enabled'] ) ) {
				update_option( 'surefeedback_guest_comments_enabled', $params['surefeedback_guest_comments_enabled'] );
				$updated++;
			}

			if ( isset( $params['surefeedback_admin'] ) ) {
				update_option( 'surefeedback_admin', $params['surefeedback_admin'] );
				$updated++;
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => sprintf( 'Updated %d settings.', $updated ),
				)
			);
		}

		/**
		 * Save connection settings
		 */
		public function save_connection_settings( $request ) {
			$params = $request->get_params();

			$updated = 0;

			foreach ( $params as $key => $value ) {
				if ( strpos( $key, 'surefeedback_' ) === 0 ) {
					update_option( $key, $value );
					$updated++;
				}
			}

			$connection_status = $this->get_connection_status();

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => sprintf( 'Updated %d settings.', $updated ),
					'data'    => array(
						'connectionStatus' => $connection_status,
					),
				)
			);
		}

		/**
		 * Save white label settings
		 */
		public function save_white_label_settings( $request ) {
			$params = $request->get_params();

			$updated = 0;

			foreach ( $params as $key => $value ) {
				if ( strpos( $key, 'surefeedback_plugin_' ) === 0 ) {
					update_option( $key, $value );
					$updated++;
				}
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => sprintf( 'Updated %d settings.', $updated ),
				)
			);
		}

		/**
		 * Get connection status
		 */
		private function get_connection_status() {
			$parent_url    = get_option( 'surefeedback_parent_url', '' );
			$site_id       = get_option( 'surefeedback_id', '' );
			$access_token  = get_option( 'surefeedback_access_token', '' );
			$api_key       = get_option( 'surefeedback_api_key', '' );
			$signature     = get_option( 'surefeedback_signature', '' );
			$is_installed  = (bool) get_option( 'surefeedback_installed', false );

			$is_connected = ! empty( $parent_url ) && ! empty( $site_id ) && ! empty( $access_token );

			return array(
				'connected'     => $is_connected,
				'parent_url'    => $parent_url,
				'site_id'       => $site_id,
				'has_api_key'   => ! empty( $api_key ),
				'has_token'     => ! empty( $access_token ),
				'has_signature' => ! empty( $signature ),
				'installed'     => $is_installed,
				'dashboard_url' => $is_connected ? $parent_url . '/wp-admin/post.php?post=' . $site_id . '&action=edit' : '',
			);
		}

		

		/**
		 * Get available WordPress roles
		 */
		private function get_available_roles() {
			global $wp_roles;

			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}

			$roles = array();
			$selected_roles = get_option( 'surefeedback_role_can_comment', array() );

			foreach ( $wp_roles->roles as $role_key => $role_data ) {
				$roles[] = array(
					'name'     => $role_key,
					'label'    => $role_data['name'],
					'selected' => in_array( $role_key, $selected_roles, true ),
				);
			}

			return $roles;
		}

		/**
		 * Sanitize roles array
		 */
		public function sanitize_roles( $roles ) {
			if ( ! is_array( $roles ) ) {
				return array();
			}

			// Ensure the function is available
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			$valid_roles = array_keys( get_editable_roles() );
			return array_intersect( $roles, $valid_roles );
		}

		/**
		 * Sanitize project ID
		 */
		public function sanitize_project_id( $value ) {
			if ( is_numeric( $value ) && $value > 0 ) {
				return intval( $value );
			}
			return '';
		}

		/**
		 * Get plugin status for recommendations
		 */
		public function get_plugin_status( $request ) {
			// Include required WordPress files
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_list = array(
				'surerank/surerank.php',
				'surecart/surecart.php', 
				'sureforms/sureforms.php',
				'presto-player/presto-player.php',
				'suretriggers/suretriggers.php'
			);

			$status_map = array();
			$installed_plugins = get_plugins();

			foreach ( $plugin_list as $plugin ) {
				if ( ! isset( $installed_plugins[ $plugin ] ) ) {
					$status_map[ $plugin ] = 'Install';
				} elseif ( is_plugin_active( $plugin ) ) {
					$status_map[ $plugin ] = 'Activated';
				} else {
					$status_map[ $plugin ] = 'Installed';
				}
			}

			return rest_ensure_response( $status_map );
		}

		/**
		 * Verify script integration
		 */
		public function verify_integration( $request ) {
			$surefeedback = SureFeedback::get_instance();
			$result = $surefeedback->verify_script_integration();
			
			return rest_ensure_response( $result );
		}

		/**
		 * Disconnect site and clear local data
		 */
		public function disconnect_site( $request ) {
			try {
				// Clear all SureFeedback related WordPress options
				// $options_to_clear = array(
				// 	'surefeedback_script_token',
				// );

				// $cleared_options = 0;
				// foreach ( $options_to_clear as $option ) {
				// 	if ( delete_option( $option ) ) {
				// 		$cleared_options++;
				// 	}
				// }
				delete_option( 'surefeedback_script_token' );
				update_option( 'surefeedback_verification_status', 'failed');
				// Clear any transients related to SureFeedback
				delete_transient( 'surefeedback_connection_test' );
				delete_transient( 'surefeedback_verification_status' );
				

				return rest_ensure_response( array(
					'success' => true,
					'message' => __( 'Site disconnected successfully. All local data has been cleared.', 'surefeedback' ),
					'data' => array(
						'cleared_options' => $cleared_options,
						'disconnected_at' => current_time( 'mysql' ),
					),
				) );

			} catch ( Exception $e ) {
				
				return new WP_Error(
					'disconnect_failed',
					__( 'Failed to disconnect site. Please try again.', 'surefeedback' ),
					array( 'status' => 500 )
				);
			}
		}
	}
endif;