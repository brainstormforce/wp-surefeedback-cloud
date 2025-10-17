<?php

namespace SureFeedback\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Verification Controller
 *
 * Handles verification requests to the SureFeedback API
 *
 * @package SureFeedback\Http\Controllers
 */
class VerificationController
{
    /**
     * Verify connection with SureFeedback API
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

            // Determine base API URL (without /api/v1 suffix)
            if (function_exists('surefeedback_get_base_api_url')) {
                $base_api_url = surefeedback_get_base_api_url();
            } elseif (function_exists('surefeedback_get_app_url')) {
                $app_url = surefeedback_get_app_url();
                $base_api_url = str_replace('app.', 'api.', $app_url);
            } else {
                $base_api_url = 'http://localhost:8000';
            }

            $verify_endpoint = "{$base_api_url}/api/v1/admin/verify-integration?script_token={$site_token}";

            // Prepare headers to match the correct curl request
            $headers = [
                'accept' => '*/*',
                'accept-language' => 'en-GB,en-US;q=0.9,en;q=0.8',
                'content-type' => 'application/json',
                'origin' => home_url(),
                'referer' => home_url('/'),
                'sec-ch-ua' => '"Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"macOS"',
                'sec-fetch-dest' => 'empty',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-site' => 'same-site',
                'user-agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
                'x-requested-with' => 'XMLHttpRequest'
            ];

            $args = [
                'method' => 'GET',
                'headers' => $headers,
                'timeout' => 30,
                'sslverify' => true
            ];

            // Perform the HTTP request to Laravel API
            $wp_response = wp_remote_get($verify_endpoint, $args);

            if (is_wp_error($wp_response)) {
                return new WP_Error(
                    'api_request_failed',
                    sprintf('API request failed: %s', $wp_response->get_error_message()),
                    ['status' => 500]
                );
            }

            $response_code = wp_remote_retrieve_response_code($wp_response);
            $response_body = wp_remote_retrieve_body($wp_response);
            $decoded = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error(
                    'invalid_response',
                    'Invalid JSON response from API',
                    ['status' => 500]
                );
            }

            if ($response_code >= 400) {
                $error_message = $decoded['message'] ?? 'API request failed';
                return new WP_Error('api_error', $error_message, ['status' => $response_code]);
            }

            // Determine verification state from decoded response
            $verification_status = get_option('surefeedback_verification_status', 'unverified');
            $is_fully_verified = isset($decoded['verification']);
            $is_script_not_loaded = isset($decoded['data']['integrated']) && !$decoded['data']['integrated'];

            // Update DB only when fully verified or when site id is available
            if ($is_fully_verified) {
                update_option('surefeedback_verification_status', 'verified');
                update_option('surefeedback_connection_status', 'connected');
                update_option('surefeedback_last_verification', current_time('mysql'));
                if (isset($decoded['site']['id'])) {
                    update_option('surefeedback_id', $decoded['site']['id']);
                }
            } elseif ($is_script_not_loaded) {
                if (isset($decoded['data']['site']['id'])) {
                    update_option('surefeedback_id', $decoded['data']['site']['id']);
                }
                if (isset($decoded['data']['instructions']['script_url'])) {
                    update_option('surefeedback_script_url', $decoded['data']['instructions']['script_url']);
                }
            }

            return new WP_REST_Response([
                'success' => $decoded['success'] ?? false,
                'message' => $decoded['message'] ?? 'Verification completed',
                'data' => $decoded,
                'verification_status' => $verification_status,
                'is_fully_verified' => $is_fully_verified,
                'is_script_pending' => $is_script_not_loaded,
                'integration_instructions' => $is_script_not_loaded ? ($decoded['data']['instructions'] ?? null) : null
            ], 200);

        } catch (Exception $e) {
            return new WP_Error('verification_error', $e->getMessage(), ['status' => 500]);
        }
    }

    
}