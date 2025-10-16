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
            $this->validateNonce($request);
            $this->validateCapability('manage_options');
            
            $settings = [
                'general' => $this->settingsRepository->getGeneralSettings(),
                'white_label' => $this->settingsRepository->getWhiteLabelSettings(),
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
            $this->validateNonce($request);
            $this->validateCapability('manage_options');
            
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
            $this->validateNonce($request);
            $this->validateCapability('manage_options');
            
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
            $this->validateNonce($request);
            $this->validateCapability('manage_options');
            
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
            $this->validateNonce($request);
            $this->validateCapability('manage_options');
            
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
            $this->validateNonce($request);
            $this->validateCapability('manage_options');
            
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
}