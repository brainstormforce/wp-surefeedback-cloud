<?php

namespace SureFeedback\Http\Controllers\Api;

defined('ABSPATH') || exit;

use SureFeedback\Services\ApiGatewayService;
use SureFeedback\Constants\VerificationStatus;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Verification Controller
 *
 * Handles verification requests to the SureFeedback Laravel API
 *
 * @package SureFeedback\Http\Controllers\Api
 * @author Anurag Singh <anurags@bsf.io>
 */
class VerificationController
{
    /**
     * API Gateway Service
     *
     * @var ApiGatewayService
     */
    private $api_gateway;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_gateway = new ApiGatewayService();
    }

    /**
     * Verify connection with SureFeedback Laravel API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function verify_connection(WP_REST_Request $request)
    {
        try {
            // Get site token from database
            $site_token = get_option('surefeedback_access_token', '');
            
            if (empty($site_token)) {
                return new WP_Error(
                    'no_site_token',
                    'Site token not found. Please reconnect your site.',
                    ['status' => 400]
                );
            }

            // Get stored JWT token for API authentication
            $jwt_token = get_option('surefeedback_user_token', '');

            // Call Laravel API via Gateway Service
            $response = $this->api_gateway->verifyIntegration($site_token, $jwt_token);

            // Handle API errors
            if (is_wp_error($response)) {
                return $response;
            }

            // Extract response data
            $decoded = $response['data'] ?? [];
            $status_code = $response['status_code'] ?? 200;

            // Extract response data
            $decoded = $response['data'] ?? [];
            $status_code = $response['status_code'] ?? 200;

            // Determine verification state from decoded response
            $is_fully_verified = isset($decoded['verification']);
            $is_script_not_loaded = isset($decoded['data']['integrated']) && !$decoded['data']['integrated'];

            // Update DB based on verification state
            if ($is_fully_verified) {
                update_option('surefeedback_last_verification', current_time('mysql'));
                update_option('surefeedback_is_fully_verified', VerificationStatus::CONNECTED);
                if (isset($decoded['site']['id'])) {
                    update_option('surefeedback_site_id', $decoded['site']['id']);
                }
            } elseif ($is_script_not_loaded) {
                update_option('surefeedback_is_fully_verified', VerificationStatus::PENDING);
                if (isset($decoded['data']['site']['id'])) {
                    update_option('surefeedback_site_id', $decoded['data']['site']['id']);
                }
            } else {
                update_option('surefeedback_is_fully_verified', VerificationStatus::NOT_VERIFIED);
            }

            $verification_status = $is_fully_verified ? 'verified' : ($is_script_not_loaded ? 'pending' : 'not_verified');

            return new WP_REST_Response([
                'success' => $decoded['success'] ?? false,
                'message' => $decoded['message'] ?? 'Verification completed',
                'data' => $decoded,
                'verification_status' => $verification_status,
                'is_fully_verified' => $is_fully_verified,
                'is_script_pending' => $is_script_not_loaded,
                'integration_instructions' => $is_script_not_loaded ? ($decoded['data']['instructions'] ?? null) : null
            ], $status_code);

        } catch (\Exception $e) {
            return new WP_Error('verification_error', $e->getMessage(), ['status' => 500]);
        }
    }
}
