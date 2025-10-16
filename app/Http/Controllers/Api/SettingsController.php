<?php

namespace SureFeedback\Http\Controllers\Api;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Http\Requests\Settings\UpdateSettingsRequest;
use SureFeedback\Http\Requests\Settings\WhiteLabelRequest;
use SureFeedback\Repositories\SettingsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Settings Controller
 *
 * Handles all settings-related API endpoints including
 * general settings, white label configuration, and plugin options.
 *
 * @package SureFeedback\App\Http\Controllers\Api
 */
class SettingsController extends Controller
{
    /**
     * Settings Repository
     *
     * @var SettingsRepository
     */
    protected $settingsRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settingsRepository = new SettingsRepository();
    }
    
    /**
     * Get all settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function index(WP_REST_Request $request)
    {
        try {
            // First check for admin capability - this handles cookie-based auth
            if (current_user_can('manage_options')) {
                // User is authenticated via WordPress cookies and has admin rights
                // No additional nonce validation needed for GET requests
                $this->logInfo('Settings access granted via cookie authentication');
            } else {
                // Fallback: Check if user is logged in at all
                if (!is_user_logged_in()) {
                    return $this->error(__('Authentication required', 'surefeedback'), null, 401);
                }
                
                // User is logged in but doesn't have manage_options capability
                return $this->error(__('Insufficient permissions. Administrator access required.', 'surefeedback'), null, 403);
            }
            
            $settings = [
                'general' => $this->settingsRepository->getGeneralSettings(),
                'white_label' => $this->settingsRepository->getWhiteLabelSettings(),
                'availableRoles' => $this->getAvailableRoles(),
            ];
            
            $this->logInfo('Settings retrieved');
            
            return $this->success($settings);
            
        } catch (\Exception $e) {
            $this->logError('Settings retrieval error: ' . $e->getMessage());
            return $this->error('Failed to retrieve settings', 500);
        }
    }
    
    /**
     * Update all settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $nonce_result = $this->validateNonce($request);
            if (is_wp_error($nonce_result)) {
                return $nonce_result;
            }
            
            $capability_result = $this->validateCapability('manage_options');
            if (is_wp_error($capability_result)) {
                return $capability_result;
            }
            
            // Create and validate request
            $updateRequest = UpdateSettingsRequest::createFromWpRequest($request);
            
            if ($updateRequest->fails()) {
                return $this->error('Validation failed', 422, $updateRequest->errors());
            }
            
            $validated = $updateRequest->validated();
            $updated_settings = [];
            
            // Update general settings if provided
            if (!empty($validated)) {
                $updated_settings = $this->settingsRepository->updateGeneralSettings($validated);
            }
            
            $this->logInfo('Settings updated', $updated_settings);
            
            return $this->success([
                'message' => 'Settings updated successfully',
                'settings' => $updated_settings,
                'updated_at' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Settings update error: ' . $e->getMessage());
            return $this->error('Failed to update settings', 500);
        }
    }
    
    /**
     * Get general settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function general(WP_REST_Request $request)
    {
        try {
            $nonce_result = $this->validateNonce($request);
            if (is_wp_error($nonce_result)) {
                return $nonce_result;
            }
            
            $capability_result = $this->validateCapability('manage_options');
            if (is_wp_error($capability_result)) {
                return $capability_result;
            }
            
            $settings = $this->settingsRepository->getGeneralSettings();
            
            return $this->success($settings);
            
        } catch (\Exception $e) {
            $this->logError('General settings retrieval error: ' . $e->getMessage());
            return $this->error('Failed to retrieve general settings', 500);
        }
    }
    
    /**
     * Update general settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function updateGeneral(WP_REST_Request $request)
    {
        try {
            $nonce_result = $this->validateNonce($request);
            if (is_wp_error($nonce_result)) {
                return $nonce_result;
            }
            
            $capability_result = $this->validateCapability('manage_options');
            if (is_wp_error($capability_result)) {
                return $capability_result;
            }
            
            // Create and validate request
            $updateRequest = UpdateSettingsRequest::createFromWpRequest($request);
            
            if ($updateRequest->fails()) {
                return $this->error('Validation failed', 422, $updateRequest->errors());
            }
            
            $validated = $updateRequest->validated();
            $settings = $this->settingsRepository->updateGeneralSettings($validated);
            
            $this->logInfo('General settings updated', $settings);
            
            return $this->success([
                'message' => 'General settings updated successfully',
                'settings' => $settings
            ]);
            
        } catch (\Exception $e) {
            $this->logError('General settings update error: ' . $e->getMessage());
            return $this->error('Failed to update general settings', 500);
        }
    }
    
    /**
     * Get white label settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function whiteLabel(WP_REST_Request $request)
    {
        try {
            $nonce_result = $this->validateNonce($request);
            if (is_wp_error($nonce_result)) {
                return $nonce_result;
            }
            
            $capability_result = $this->validateCapability('manage_options');
            if (is_wp_error($capability_result)) {
                return $capability_result;
            }
            
            $settings = $this->settingsRepository->getWhiteLabelSettings();
            
            return $this->success($settings);
            
        } catch (\Exception $e) {
            $this->logError('White label settings retrieval error: ' . $e->getMessage());
            return $this->error('Failed to retrieve white label settings', 500);
        }
    }
    
    /**
     * Update white label settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function updateWhiteLabel(WP_REST_Request $request)
    {
        try {
            $nonce_result = $this->validateNonce($request);
            if (is_wp_error($nonce_result)) {
                return $nonce_result;
            }
            
            $capability_result = $this->validateCapability('manage_options');
            if (is_wp_error($capability_result)) {
                return $capability_result;
            }
            
            // Create and validate request
            $whiteLabelRequest = WhiteLabelRequest::createFromWpRequest($request);
            
            if ($whiteLabelRequest->fails()) {
                return $this->error('Validation failed', 422, $whiteLabelRequest->errors());
            }
            
            $validated = $whiteLabelRequest->validated();
            $settings = $this->settingsRepository->updateWhiteLabelSettings($validated);
            
            $this->logInfo('White label settings updated', $settings);
            
            return $this->success([
                'message' => 'White label settings updated successfully',
                'settings' => $settings
            ]);
            
        } catch (\Exception $e) {
            $this->logError('White label settings update error: ' . $e->getMessage());
            return $this->error('Failed to update white label settings', 500);
        }
    }
    
    /**
     * Get available WordPress roles
     *
     * @return array
     */
    private function getAvailableRoles(): array
    {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        
        $roles = [];
        foreach ($wp_roles->roles as $role_key => $role_data) {
            $roles[] = [
                'name' => $role_key,
                'label' => $role_data['name']
            ];
        }
        
        return $roles;
    }
}