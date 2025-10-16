<?php

namespace SureFeedback\App\Http\Controllers\Api;

use SureFeedback\App\Http\Controllers\Controller;
use SureFeedback\App\Http\Requests\UpdateSettingsRequest;
use SureFeedback\App\Http\Requests\WhiteLabelRequest;
use SureFeedback\App\Http\Requests\VerifyConnectionRequest;
use SureFeedback\App\Repositories\SettingsRepository;
use SureFeedback\App\Repositories\ConnectionRepository;
use SureFeedback\App\Repositories\DashboardRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin API Controller
 *
 * Handles admin-specific API endpoints for settings management,
 * white labeling, verification, and connection management.
 *
 * @package SureFeedback\App\Http\Controllers\Api
 */
class AdminApiController extends Controller
{
    /**
     * Settings Repository
     *
     * @var SettingsRepository
     */
    protected $settingsRepository;

    /**
     * Connection Repository
     *
     * @var ConnectionRepository
     */
    protected $connectionRepository;

    /**
     * Dashboard Repository
     *
     * @var DashboardRepository
     */
    protected $dashboardRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->settingsRepository = new SettingsRepository();
        $this->connectionRepository = new ConnectionRepository();
        $this->dashboardRepository = new DashboardRepository();
    }
    /**
     * Get all plugin settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getSettings(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('manage_options');

            $settings = [
                'general' => $this->settingsRepository->getSettings(),
                'connection' => $this->connectionRepository->getConnectionData(),
                'white_label' => $this->settingsRepository->getWhiteLabelSettings(),
                'dashboard' => $this->dashboardRepository->getDashboardData()
            ];

            $this->logInfo('Settings retrieved for admin API');

            return $this->success($settings);

        } catch (\Exception $e) {
            $this->logError('Failed to get settings: ' . $e->getMessage());
            return $this->error('Failed to retrieve settings', 500);
        }
    }

    /**
     * Save general settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function saveGeneralSettings(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('manage_options');

            $validation = $this->validate($request, [
                'surefeedback_role_can_comment' => 'array',
                'surefeedback_guest_comments' => 'boolean',
                'surefeedback_admin_can_comment' => 'boolean',
                'surefeedback_show_on_admin' => 'boolean',
                'surefeedback_disable_for_admin' => 'boolean'
            ]);

            if (is_wp_error($validation)) {
                return $validation;
            }

            // Save role settings
            $roles = $request->get_param('surefeedback_role_can_comment');
            if (is_array($roles)) {
                update_option('surefeedback_role_can_comment', array_map('sanitize_text_field', $roles));
            }

            // Save boolean settings
            $boolean_settings = [
                'surefeedback_guest_comments',
                'surefeedback_admin_can_comment',
                'surefeedback_show_on_admin',
                'surefeedback_disable_for_admin'
            ];

            foreach ($boolean_settings as $setting) {
                $value = $request->get_param($setting);
                if ($value !== null) {
                    update_option($setting, (bool) $value);
                }
            }

            $this->logInfo('General settings saved', [
                'roles' => $roles ?? [],
                'settings_updated' => count($boolean_settings)
            ]);

            return $this->success([
                'message' => 'General settings saved successfully',
                'settings' => $this->getGeneralSettings()
            ]);

        } catch (\Exception $e) {
            $this->logError('Failed to save general settings: ' . $e->getMessage());
            return $this->error('Failed to save general settings', 500);
        }
    }

    /**
     * Save white label settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function saveWhiteLabelSettings(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('manage_options');

            $validation = $this->validate($request, [
                'surefeedback_plugin_name' => 'string',
                'surefeedback_plugin_description' => 'string',
                'surefeedback_plugin_author' => 'string',
                'surefeedback_plugin_author_url' => 'string|url',
                'surefeedback_plugin_link' => 'string|url'
            ]);

            if (is_wp_error($validation)) {
                return $validation;
            }

            // White label settings
            $white_label_settings = [
                'surefeedback_plugin_name' => 'sanitize_text_field',
                'surefeedback_plugin_description' => 'sanitize_textarea_field',
                'surefeedback_plugin_author' => 'sanitize_text_field',
                'surefeedback_plugin_author_url' => 'esc_url_raw',
                'surefeedback_plugin_link' => 'esc_url_raw'
            ];

            $updated_settings = [];
            foreach ($white_label_settings as $setting => $sanitize_function) {
                $value = $request->get_param($setting);
                if ($value !== null) {
                    $sanitized_value = call_user_func($sanitize_function, $value);
                    update_option($setting, $sanitized_value);
                    $updated_settings[$setting] = $sanitized_value;
                }
            }

            $this->logInfo('White label settings saved', $updated_settings);

            return $this->success([
                'message' => 'White label settings saved successfully',
                'settings' => $this->getWhiteLabelSettings()
            ]);

        } catch (\Exception $e) {
            $this->logError('Failed to save white label settings: ' . $e->getMessage());
            return $this->error('Failed to save white label settings', 500);
        }
    }

    /**
     * Verify integration with parent site
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function verifyIntegration(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('manage_options');

            // Get current connection details
            $parent_url = get_option('surefeedback_parent_url', '');
            $site_token = get_option('surefeedback_site_token', '');

            if (empty($parent_url) || empty($site_token)) {
                return $this->error('Site not connected to parent dashboard', 400);
            }

            // Use SaaS client service for verification
            $saas_client = new \SureFeedback\App\Services\SaasClientService();
            $verification_result = $saas_client->verify_script_integration();

            if ($verification_result['success']) {
                update_option('surefeedback_last_verification', current_time('mysql'));
                update_option('surefeedback_verification_status', 'verified');

                $this->logInfo('Integration verification successful');

                return $this->success([
                    'verified' => true,
                    'message' => 'Integration verified successfully',
                    'last_verification' => current_time('mysql'),
                    'details' => $verification_result
                ]);
            } else {
                update_option('surefeedback_verification_status', 'failed');

                $this->logError('Integration verification failed', $verification_result);

                return $this->error($verification_result['message'] ?? 'Verification failed', 400);
            }

        } catch (\Exception $e) {
            $this->logError('Integration verification error: ' . $e->getMessage());
            return $this->error('Verification process failed', 500);
        }
    }

    /**
     * Disconnect site from parent dashboard
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function disconnectSite(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('manage_options');

            // Get current connection details
            $parent_url = get_option('surefeedback_parent_url', '');
            $site_token = get_option('surefeedback_site_token', '');

            if (!empty($parent_url) && !empty($site_token)) {
                // Notify parent site about disconnection
                $this->notifyParentSiteDisconnection($parent_url, $site_token);
            }

            // Clear all connection-related options
            $connection_options = [
                'surefeedback_parent_url',
                'surefeedback_site_token',
                'surefeedback_access_token',
                'surefeedback_site_id',
                'surefeedback_connection_status',
                'surefeedback_connection_date',
                'surefeedback_last_verification',
                'surefeedback_verification_status',
                'surefeedback_script_url'
            ];

            foreach ($connection_options as $option) {
                delete_option($option);
            }

            // Clear scheduled verification events
            wp_clear_scheduled_hook('surefeedback_auto_verify');
            wp_clear_scheduled_hook('surefeedback_hourly_verify');

            $this->logInfo('Site disconnected from parent dashboard');

            return $this->success([
                'disconnected' => true,
                'message' => 'Site disconnected successfully',
                'disconnected_at' => current_time('mysql')
            ]);

        } catch (\Exception $e) {
            $this->logError('Disconnection error: ' . $e->getMessage());
            return $this->error('Failed to disconnect site', 500);
        }
    }

    /**
     * Get connection status information
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getConnectionStatus(WP_REST_Request $request)
    {
        try {
            $this->validateNonce($request);
            $this->validateCapability('manage_options');

            $connection_data = [
                'connected' => $this->isConnected(),
                'parent_url' => get_option('surefeedback_parent_url', ''),
                'site_id' => get_option('surefeedback_site_id', ''),
                'connection_date' => get_option('surefeedback_connection_date', ''),
                'last_verification' => get_option('surefeedback_last_verification', ''),
                'verification_status' => get_option('surefeedback_verification_status', 'pending'),
                'script_url' => get_option('surefeedback_script_url', ''),
                'has_token' => !empty(get_option('surefeedback_site_token', '')),
                'status_message' => $this->getConnectionStatusMessage()
            ];

            return $this->success($connection_data);

        } catch (\Exception $e) {
            $this->logError('Failed to get connection status: ' . $e->getMessage());
            return $this->error('Failed to retrieve connection status', 500);
        }
    }

    /**
     * Get general settings
     *
     * @return array
     */
    private function getGeneralSettings(): array
    {
        return [
            'surefeedback_role_can_comment' => get_option('surefeedback_role_can_comment', ['administrator']),
            'surefeedback_guest_comments' => (bool) get_option('surefeedback_guest_comments', false),
            'surefeedback_admin_can_comment' => (bool) get_option('surefeedback_admin_can_comment', true),
            'surefeedback_show_on_admin' => (bool) get_option('surefeedback_show_on_admin', false),
            'surefeedback_disable_for_admin' => (bool) get_option('surefeedback_disable_for_admin', false)
        ];
    }

    /**
     * Get connection settings
     *
     * @return array
     */
    private function getConnectionSettings(): array
    {
        return [
            'parent_url' => get_option('surefeedback_parent_url', ''),
            'site_id' => get_option('surefeedback_site_id', ''),
            'connection_status' => get_option('surefeedback_connection_status', 'disconnected'),
            'connection_date' => get_option('surefeedback_connection_date', ''),
            'last_verification' => get_option('surefeedback_last_verification', ''),
            'verification_status' => get_option('surefeedback_verification_status', 'pending')
        ];
    }

    /**
     * Get white label settings
     *
     * @return array
     */
    private function getWhiteLabelSettings(): array
    {
        return [
            'surefeedback_plugin_name' => get_option('surefeedback_plugin_name', 'SureFeedback Client'),
            'surefeedback_plugin_description' => get_option('surefeedback_plugin_description', ''),
            'surefeedback_plugin_author' => get_option('surefeedback_plugin_author', ''),
            'surefeedback_plugin_author_url' => get_option('surefeedback_plugin_author_url', ''),
            'surefeedback_plugin_link' => get_option('surefeedback_plugin_link', '')
        ];
    }

    /**
     * Get advanced settings
     *
     * @return array
     */
    private function getAdvancedSettings(): array
    {
        return [
            'debug_mode' => (bool) get_option('surefeedback_debug_mode', false),
            'cache_duration' => (int) get_option('surefeedback_cache_duration', 3600),
            'api_timeout' => (int) get_option('surefeedback_api_timeout', 30),
            'max_file_size' => (int) get_option('surefeedback_max_file_size', 10485760), // 10MB
            'allowed_file_types' => get_option('surefeedback_allowed_file_types', ['jpg', 'jpeg', 'png', 'gif', 'pdf'])
        ];
    }

    /**
     * Check if site is connected
     *
     * @return bool
     */
    private function isConnected(): bool
    {
        return !empty(get_option('surefeedback_parent_url', '')) &&
               !empty(get_option('surefeedback_site_token', ''));
    }

    /**
     * Get connection status message
     *
     * @return string
     */
    private function getConnectionStatusMessage(): string
    {
        if (!$this->isConnected()) {
            return 'Not connected to parent dashboard';
        }

        $verification_status = get_option('surefeedback_verification_status', 'pending');
        
        switch ($verification_status) {
            case 'verified':
                return 'Connected and verified';
            case 'failed':
                return 'Connected but verification failed';
            case 'pending':
            default:
                return 'Connected, verification pending';
        }
    }

    /**
     * Notify parent site about disconnection
     *
     * @param string $parent_url Parent site URL
     * @param string $site_token Site token
     * @return void
     */
    private function notifyParentSiteDisconnection(string $parent_url, string $site_token): void
    {
        $disconnect_url = trailingslashit($parent_url) . 'wp-json/surefeedback/v1/site-disconnected';
        
        wp_remote_post($disconnect_url, [
            'headers' => [
                'X-SureFeedback-Token' => $site_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'disconnected_at' => current_time('mysql')
            ]),
            'timeout' => 10,
            'blocking' => false // Don't block the request
        ]);
    }
}