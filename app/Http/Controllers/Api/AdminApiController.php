<?php

namespace SureFeedback\Http\Controllers\Api;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Http\Requests\UpdateSettingsRequest;
use SureFeedback\Http\Requests\WhiteLabelRequest;
use SureFeedback\Http\Requests\VerifyConnectionRequest;
use SureFeedback\Repositories\SettingsRepository;
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
 * @author Anurag Singh <anurags@bsf.io>
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
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->settingsRepository = new SettingsRepository();
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
                'connection' => [
                    'connected' => $this->isConnected(),
                    'parent_url' => get_option('surefeedback_parent_url', ''),
                    'site_id' => get_option('surefeedback_site_id', ''),
                    'last_verification' => get_option('surefeedback_last_verification', ''),
                ],
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

            // Settings are managed via webhook
            return $this->success([
                'message' => 'Settings are managed via webhook connection',
                'settings' => []
            ]);

        } catch (\Exception $e) {
            $this->logError('Failed to save general settings: ' . $e->getMessage());
            return $this->error('Failed to save general settings', 500);
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
                'last_verification' => get_option('surefeedback_last_verification', ''),
                'has_token' => !empty(get_option('surefeedback_access_token', '')),
                'status_message' => $this->getConnectionStatusMessage()
            ];

            return $this->success($connection_data);

        } catch (\Exception $e) {
            $this->logError('Failed to get connection status: ' . $e->getMessage());
            return $this->error('Failed to retrieve connection status', 500);
        }
    }

    /**
     * Check if site is connected
     *
     * @return bool
     */
    private function isConnected(): bool
    {
        return !empty(get_option('surefeedback_parent_url', '')) &&
               !empty(get_option('surefeedback_access_token', ''));
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

        $last_verification = get_option('surefeedback_last_verification', '');

        if (!empty($last_verification)) {
            return 'Connected and verified';
        }

        return 'Connected - verification pending';
    }
}