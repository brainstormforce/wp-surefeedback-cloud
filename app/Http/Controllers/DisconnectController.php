<?php

namespace SureFeedback\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Disconnect Controller
 *
 * Handles disconnect requests to the SureFeedback Laravel API
 *
 * @package SureFeedback\Http\Controllers
 * @author Anurag Singh <anurags@bsf.io>
 */
class DisconnectController
{
    /**
     * Master disconnect from SureFeedback API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function master_disconnect(WP_REST_Request $request)
    {
        try {
            // Check authentication and permissions
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'rest_forbidden',
                    'Authentication required.',
                    ['status' => 401]
                );
            }

            // Check if user has admin capabilities
            if (!current_user_can('manage_options')) {
                return new WP_Error(
                    'rest_forbidden',
                    'You do not have permission to disconnect this site.',
                    ['status' => 403]
                );
            }

            // Verify nonce for additional security
            $nonce = $request->get_header('X-WP-Nonce');
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error(
                    'rest_cookie_invalid_nonce',
                    'Cookie nonce is invalid.',
                    ['status' => 403]
                );
            }

            // Get site token from database
            $site_token = get_option('surefeedback_access_token', '');
            
            if (empty($site_token)) {
                return new WP_Error(
                    'no_site_token',
                    'Site token not found. Please reconnect your site.',
                    ['status' => 400]
                );
            }

            // Get site domain
            $site_domain = parse_url(home_url(), PHP_URL_HOST);
            if (empty($site_domain)) {
                return new WP_Error(
                    'no_site_domain',
                    'Site domain could not be determined.',
                    ['status' => 400]
                );
            }

            // Generate JWT token for Laravel API authentication
            $jwt_service = new \SureFeedback\Services\JWTService();
            $current_user = wp_get_current_user();
            
            $jwt_payload = [
                'user_id' => $current_user->ID,
                'userId' => $current_user->ID,
                'email' => $current_user->user_email,
                'role' => 'ADMIN', // Set as ADMIN for disconnect operation
                'domain' => $site_domain,
                'site_token' => $site_token
            ];
            
            $jwt_token = $jwt_service->generate_token($jwt_payload);
            
            if (empty($jwt_token)) {
                return new WP_Error(
                    'jwt_generation_failed',
                    'Failed to generate authentication token.',
                    ['status' => 500]
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

            $disconnect_endpoint = "{$base_api_url}/api/v1/sites/wordpress/master-disconnect";

            // Prepare payload for Laravel API
            $payload = [
                'site_token' => $site_token,
                'domain' => $site_domain
            ];

            // Prepare headers to match the correct curl request
            $headers = [
                'accept' => 'application/json',
                'accept-language' => 'en-GB,en-US;q=0.9,en;q=0.8',
                'content-type' => 'application/json',
                'authorization' => 'Bearer ' . $jwt_token, // Add JWT token for Laravel API authentication
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
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($payload),
                'timeout' => 30,
                'sslverify' => true
            ];

            // Try to perform the HTTP request to Laravel API, but don't fail if it doesn't work
            $laravel_success = false;
            $laravel_message = '';
            
            $wp_response = wp_remote_post($disconnect_endpoint, $args);

            if (!is_wp_error($wp_response)) {
                $response_code = wp_remote_retrieve_response_code($wp_response);
                $response_body = wp_remote_retrieve_body($wp_response);
                $decoded = json_decode($response_body, true);

                if (json_last_error() === JSON_ERROR_NONE && $response_code < 400) {
                    $laravel_success = $decoded['success'] ?? false;
                    $laravel_message = $decoded['message'] ?? 'Laravel disconnect completed';
                }
            }

            // Always perform local disconnect regardless of Laravel API result
            $this->performLocalDisconnect();

            return new WP_REST_Response([
                'success' => true, // Local disconnect always succeeds
                'message' => 'Site disconnected successfully from WordPress',
                'laravel_disconnect' => $laravel_success,
                'laravel_message' => $laravel_message,
                'connection_status' => 'disconnected',
                'disconnected_at' => current_time('mysql')
            ], 200);

        } catch (Exception $e) {
            return new WP_Error('disconnect_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Perform local WordPress disconnect operations
     * 
     * @return void
     */
    private function performLocalDisconnect()
    {
        // Clear all SureFeedback-related options
        delete_option('surefeedback_access_token');
        delete_option('surefeedback_site_token');
        delete_option('surefeedback_connection_status');
        delete_option('surefeedback_site_id');
        delete_option('surefeedback_app_url');
        delete_option('surefeedback_api_url');
        delete_option('surefeedback_signature');
        delete_option('surefeedback_connection_data');
        delete_option('surefeedback_settings');
        delete_option('surefeedback_widget_config');
        
        // Set disconnected status
        update_option('surefeedback_connection_status', 'disconnected');
        
        // Clear any cached data
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}