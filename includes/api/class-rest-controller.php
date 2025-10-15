<?php

/**
 * REST Controller class
 *
 * @package SureFeedback
 */

namespace SureFeedback\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * REST Controller class
 */
class Rest_Controller extends WP_REST_Controller
{

    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace = 'surefeedback/v1';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register REST routes
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register routes
     */
    public function register_routes()
    {
        // Admin settings routes
        $this->register_admin_routes();

        // Webhook routes
        $this->register_webhook_routes();

        // Verification routes
        $this->register_verification_routes();
    }

    /**
     * Register admin routes
     */
    private function register_admin_routes()
    {
        register_rest_route(
            $this->namespace,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_settings'),
                    'permission_callback' => array($this, 'admin_permissions_check'),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/settings/general',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'save_general_settings'),
                    'permission_callback' => array($this, 'admin_permissions_check'),
                    'args'                => $this->get_general_settings_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/settings/connection',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'save_connection_settings'),
                    'permission_callback' => array($this, 'admin_permissions_check'),
                    'args'                => $this->get_connection_settings_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/settings/white-label',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'save_white_label_settings'),
                    'permission_callback' => array($this, 'admin_permissions_check'),
                    'args'                => $this->get_white_label_settings_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/disconnect',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'disconnect_site'),
                    'permission_callback' => array($this, 'admin_permissions_check'),
                ),
            )
        );
    }

    /**
     * Register webhook routes
     */
    private function register_webhook_routes()
    {
        register_rest_route(
            $this->namespace,
            '/webhook',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'handle_webhook_callback'),
                    'permission_callback' => array($this, 'verify_webhook_permission'),
                ),
            )
        );
    }

    /**
     * Register verification routes
     */
    private function register_verification_routes()
    {
        register_rest_route(
            $this->namespace,
            '/verify-integration',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'verify_integration'),
                    'permission_callback' => array($this, 'admin_permissions_check'),
                ),
            )
        );
    }

    // ===========================================
    // PERMISSION CALLBACKS
    // ===========================================

    /**
     * Check admin permissions
     */
    public function admin_permissions_check($request)
    {
        // Check user capability
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Verify nonce for non-GET requests
        if ($request->get_method() !== 'GET') {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error(
                    'rest_forbidden',
                    __('Invalid nonce.', 'surefeedback'),
                    array('status' => 403)
                );
            }
        }

        return true;
    }

    /**
     * Verify webhook permission
     */
    public function verify_webhook_permission($request)
    {
        // For now, allow all requests - can add more security later
        return true;
    }

    // ===========================================
    // ADMIN SETTINGS ENDPOINTS
    // ===========================================

    /**
     * Get all settings
     */
    public function get_settings($request)
    {
        $general_settings = array(
            'surefeedback_role_can_comment'        => get_option('surefeedback_role_can_comment', array()),
            'surefeedback_guest_comments_enabled'  => (bool) get_option('surefeedback_guest_comments_enabled', false),
            'surefeedback_admin'                   => (bool) get_option('surefeedback_admin', false),
        );

        $connection_settings = array(
            'surefeedback_id'           => get_option('surefeedback_id', ''),
            'surefeedback_api_key'      => get_option('surefeedback_api_key', ''),
            'surefeedback_access_token' => get_option('surefeedback_access_token', ''),
            'surefeedback_parent_url'   => get_option('surefeedback_parent_url', ''),
            'surefeedback_signature'    => get_option('surefeedback_signature', ''),
            'surefeedback_installed'    => (bool) get_option('surefeedback_installed', false),
        );

        $white_label_settings = array(
            'surefeedback_plugin_name'        => get_option('surefeedback_plugin_name', ''),
            'surefeedback_plugin_description' => get_option('surefeedback_plugin_description', ''),
            'surefeedback_plugin_author'      => get_option('surefeedback_plugin_author', ''),
            'surefeedback_plugin_author_url'  => get_option('surefeedback_plugin_author_url', ''),
            'surefeedback_plugin_link'        => get_option('surefeedback_plugin_link', ''),
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
    public function save_general_settings($request)
    {
        $params = $request->get_params();
        $updated = 0;

        $allowed_fields = [
            'surefeedback_role_can_comment',
            'surefeedback_guest_comments_enabled',
            'surefeedback_admin'
        ];

        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                update_option($field, $params[$field]);
                $updated++;
            }
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'message' => sprintf('Updated %d settings.', $updated),
            )
        );
    }

    /**
     * Save connection settings
     */
    public function save_connection_settings($request)
    {
        $params = $request->get_params();
        $updated = 0;

        $allowed_fields = [
            'surefeedback_id',
            'surefeedback_api_key',
            'surefeedback_access_token',
            'surefeedback_parent_url',
            'surefeedback_signature'
        ];

        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                update_option($field, $params[$field]);
                $updated++;
            }
        }

        $connection_status = $this->get_connection_status();

        return rest_ensure_response(
            array(
                'success' => true,
                'message' => sprintf('Updated %d settings.', $updated),
                'data'    => array(
                    'connectionStatus' => $connection_status,
                ),
            )
        );
    }

    /**
     * Save white label settings
     */
    public function save_white_label_settings($request)
    {
        $params = $request->get_params();
        $updated = 0;

        $allowed_fields = [
            'surefeedback_plugin_name',
            'surefeedback_plugin_description',
            'surefeedback_plugin_author',
            'surefeedback_plugin_author_url',
            'surefeedback_plugin_link'
        ];

        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                update_option($field, $params[$field]);
                $updated++;
            }
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'message' => sprintf('Updated %d settings.', $updated),
            )
        );
    }

    /**
     * Disconnect site and clear local data
     */
    public function disconnect_site($request)
    {
        try {
            // Clear all SureFeedback related WordPress options
            $options_to_clear = array(
                'surefeedback_script_token',
                'surefeedback_site_token',
                'surefeedback_verification_status',
            );

            $cleared_options = 0;
            foreach ($options_to_clear as $option) {
                if (delete_option($option)) {
                    $cleared_options++;
                }
            }

            // Update verification and connection status
            update_option('surefeedback_verification_status', 'failed');
            
            // Clear any transients related to SureFeedback
            delete_transient('surefeedback_connection_test');
            delete_transient('surefeedback_verification_status');
            

            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Site disconnected successfully. All local data has been cleared.', 'surefeedback'),
                'data' => array(
                    'cleared_options' => $cleared_options,
                    'disconnected_at' => current_time('mysql'),
                ),
            ));

        } catch (\Exception $e) {
            
            return new WP_Error(
                'disconnect_failed',
                __('Failed to disconnect site. Please try again.', 'surefeedback'),
                array('status' => 500)
            );
        }
    }

    // ===========================================
    // WEBHOOK ENDPOINTS
    // ===========================================

    /**
     * Handle webhook callback from SureFeedback backend
     */
    public function handle_webhook_callback($request)
    {
        $params = $request->get_params();
        $headers = $request->get_headers();
        $raw_body = $request->get_body();
    
        // Laravel sends JSON, so let's try to parse the body if params are empty or insufficient
        if ((empty($params) || (!isset($params['success']) && !isset($params['site_token']))) && !empty($raw_body)) {
            $json_data = json_decode($raw_body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                $params = $json_data;
            }
        }
        
        // Validate required parameters
        if (empty($params['success']) || empty($params['site_token'])) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }

        if ($params['success'] === '1' || $params['success'] === 1 || $params['success'] === true) {
            $result = $this->process_successful_connection($params);
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Connection data saved successfully',
                'processed_at' => current_time('mysql'),
                'saved_options' => $result ? 'true' : 'false',
            ));
        } else {
            // Connection failed
            
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'Connection failed or invalid data',
                'debug_info' => array(
                    'success_param' => $params['success'] ?? 'NOT SET',
                    'available_keys' => array_keys($params)
                ),
            ));
        }
    }

    /**
     * Process successful connection from webhook
     */
    private function process_successful_connection($params)
    {
        try {
            // Sanitize and save connection data
            // Get the SureFeedback instance to determine proper parent URL
            $surefeedback = \SureFeedback::get_instance();
            $parent_url = $params['parent_url'] ?? $surefeedback->get_api_url();
            
            $connection_data = array(
                'surefeedback_site_token' => sanitize_text_field($params['site_token']),
                'surefeedback_script_token' => sanitize_text_field($params['script_token'] ?? $params['site_token']),
                'surefeedback_api_key' => sanitize_text_field($params['site_token']),
                'surefeedback_id' => sanitize_text_field($params['site_id'] ?? ''),
                'surefeedback_site_id' => sanitize_text_field($params['site_id'] ?? ''),
                'surefeedback_organization_id' => sanitize_text_field($params['organization_id'] ?? ''),
                'surefeedback_site_name' => sanitize_text_field($params['site_name'] ?? ''),
                'surefeedback_domain' => sanitize_text_field($params['domain'] ?? ''),
                'surefeedback_user_token' => sanitize_text_field($params['user_token'] ?? ''),
                'surefeedback_connection_status' => 'connected',
                'surefeedback_widget_enabled' => true,
                'surefeedback_access_token' => sanitize_text_field($params['site_token']),
                'surefeedback_parent_url' => esc_url_raw($parent_url),
                'surefeedback_verification_status' => 'pending',
                'surefeedback_verification_attempts' => 0,
            );

            $saved_options = 0;
            foreach ($connection_data as $key => $value) {
                if (update_option($key, $value)) {
                    $saved_options++;
                }
            }

            
            // Trigger immediate script injection and auto verification
            error_log('SureFeedback: Webhook - Starting immediate script injection and verification');
            
            $surefeedback_frontend = new \SureFeedback\Frontend\Frontend_Manager();;
            
            $surefeedback_frontend->trigger_script_injection_immediately();
            
             $surefeedback = \SureFeedback::get_instance();
            // Perform immediate verification (no scheduling delays)
             $surefeedback->auto_verify_script();
            
                        error_log('SureFeedback: Webhook - Script injection triggered, now starting immediate verification');

            // Trigger the connection updated action
            do_action('surefeedback_connection_updated');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ===========================================
    // VERIFICATION ENDPOINTS
    // ===========================================

    /**
     * Verify script integration
     */
    public function verify_integration($request)
    {
        $surefeedback = \SureFeedback::get_instance();
        $result = $surefeedback->verify_script_integration();
        
        return rest_ensure_response($result);
    }

    // ===========================================
    // HELPER METHODS
    // ===========================================

    /**
     * Get connection status
     */
    private function get_connection_status()
    {
        $parent_url    = get_option('surefeedback_parent_url', '');
        $site_id       = get_option('surefeedback_id', '');
        $access_token  = get_option('surefeedback_access_token', '');
        $api_key       = get_option('surefeedback_api_key', '');
        $signature     = get_option('surefeedback_signature', '');
        $is_installed  = (bool) get_option('surefeedback_installed', false);

        $is_connected = ! empty($parent_url) && ! empty($site_id) && ! empty($access_token);

        return array(
            'connected'     => $is_connected,
            'parent_url'    => $parent_url,
            'site_id'       => $site_id,
            'has_api_key'   => ! empty($api_key),
            'has_token'     => ! empty($access_token),
            'has_signature' => ! empty($signature),
            'installed'     => $is_installed,
            'dashboard_url' => $is_connected ? $parent_url . '/wp-admin/post.php?post=' . $site_id . '&action=edit' : '',
        );
    }

    /**
     * Get available WordPress roles
     */
    private function get_available_roles()
    {
        global $wp_roles;

        if (! isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $roles = array();
        $selected_roles = get_option('surefeedback_role_can_comment', array());

        foreach ($wp_roles->roles as $role_key => $role_data) {
            $roles[] = array(
                'name'     => $role_key,
                'label'    => $role_data['name'],
                'selected' => in_array($role_key, $selected_roles, true),
            );
        }

        return $roles;
    }

    /**
     * Get general settings arguments
     */
    private function get_general_settings_args()
    {
        return array(
            'surefeedback_role_can_comment' => array(
                'type'              => 'array',
                'items'             => array('type' => 'string'),
                'sanitize_callback' => array($this, 'sanitize_roles'),
            ),
            'surefeedback_guest_comments_enabled' => array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
            'surefeedback_admin' => array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
        );
    }

    /**
     * Get connection settings arguments
     */
    private function get_connection_settings_args()
    {
        return array(
            'surefeedback_id' => array(
                'type'              => array('integer', 'string'),
                'sanitize_callback' => array($this, 'sanitize_project_id'),
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
        );
    }

    /**
     * Get white label settings arguments
     */
    private function get_white_label_settings_args()
    {
        return array(
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
        );
    }

    /**
     * Sanitize roles array
     */
    public function sanitize_roles($roles)
    {
        if (! is_array($roles)) {
            return array();
        }

        // Ensure the function is available
        if (! function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $valid_roles = array_keys(get_editable_roles());
        return array_intersect($roles, $valid_roles);
    }

    /**
     * Sanitize project ID
     */
    public function sanitize_project_id($value)
    {
        if (is_numeric($value) && $value > 0) {
            return intval($value);
        }
        return '';
    }
}