<?php

/**
 * SaaS API Client
 *
 * @package SureFeedback
 */

namespace SureFeedback\SaaS;

use WP_Error;

/**
 * Class SaaS_Client
 *
 * Handles communication with the external SaaS API
 */
class SaaS_Client
{

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url;

    /**
     * Constructor
     */
    public function __construct()
    {
        $surefeedback = \SureFeedback::get_instance();
        $this->api_base_url = $surefeedback->get_api_url() . '/api/v1';
    }

    /**
     * Get default headers for API requests
     *
     * @return array
     */
    private function get_default_headers()
    {
        $access_token = get_option('surefeedback_access_token', '');

        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        );

        if (!empty($access_token)) {
            $headers['Authorization'] = 'Bearer ' . $access_token;
        }

        return $headers;
    }

    /**
     * Make a GET request to the SaaS API
     *
     * @param string $endpoint The API endpoint (relative to base URL).
     * @param array  $args     Optional. Additional arguments for wp_remote_get.
     * @return array|WP_Error The response or WP_Error on failure.
     */
    public function get($endpoint, $args = array())
    {
        $url = $this->api_base_url . '/' . ltrim($endpoint, '/');

        $default_args = array(
            'headers' => $this->get_default_headers(),
            'timeout' => 30,
        );

        $args = wp_parse_args($args, $default_args);

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if (200 !== $code) {
            return new WP_Error(
                'saas_api_error',
                sprintf('API returned status code %d', $code),
                array('body' => $body, 'code' => $code)
            );
        }

        $data = json_decode($body, true);

        if (null === $data && !empty($body)) {
            return new WP_Error('saas_api_invalid_json', 'Invalid JSON response from API');
        }

        return $data;
    }

    /**
     * Make a POST request to the SaaS API
     *
     * @param string $endpoint The API endpoint (relative to base URL).
     * @param array  $data     The data to send.
     * @param array  $args     Optional. Additional arguments for wp_remote_post.
     * @return array|WP_Error The response or WP_Error on failure.
     */
    public function post($endpoint, $data = array(), $args = array())
    {
        $url = $this->api_base_url . '/' . ltrim($endpoint, '/');

        $default_args = array(
            'headers' => $this->get_default_headers(),
            'body'    => wp_json_encode($data),
            'timeout' => 30,
        );

        $args = wp_parse_args($args, $default_args);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if (!in_array($code, array(200, 201), true)) {
            return new WP_Error(
                'saas_api_error',
                sprintf('API returned status code %d', $code),
                array('body' => $body, 'code' => $code)
            );
        }

        $data = json_decode($body, true);

        if (null === $data && !empty($body)) {
            return new WP_Error('saas_api_invalid_json', 'Invalid JSON response from API');
        }

        return $data;
    }

    /**
     * Verify script integration with SaaS platform
     *
     * @return array
     */
    public function verify_integration()
    {
        $script_token = get_option('surefeedback_script_token');
        $site_token = get_option('surefeedback_api_key');
        $parent_url = get_option('surefeedback_parent_url');

        if ((!$script_token && !$site_token) || !$parent_url) {
            return array(
                'success' => false,
                'message' => 'Missing script token or parent URL',
            );
        }

        $token_to_use = $script_token ?: $site_token;
        $verification_url = trailingslashit($parent_url) . 'api/v1/admin/verify-integration?script_token=' . $token_to_use;

        $response = wp_remote_get($verification_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'SureFeedback-WordPress-Plugin/1.0',
                'Authorization' => 'Bearer ' . $token_to_use,
            ),
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to connect to verification service: ' . $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code === 200 && isset($data['integrated']) && $data['integrated']) {
            update_option('surefeedback_last_verification', current_time('mysql'));
            update_option('surefeedback_verification_status', 'verified');

            return array(
                'success' => true,
                'verified' => true,
                'message' => $data['message'] ?? 'Integration verified successfully',
                'data' => $data,
            );
        }

        if (isset($data['status'])) {
            $error_messages = array(
                'SCRIPT_NOT_LOADED' => 'Script integration is valid but widget is not currently loaded on website',
                'TOKEN_NOT_FOUND' => 'Invalid site token or site is inactive',
                'INVALID_TOKEN_FORMAT' => 'Invalid token format',
                'VERIFICATION_ERROR' => 'Failed to verify script integration',
            );

            $message = $error_messages[$data['status']] ?? ($data['error'] ?? 'Verification failed');

            return array(
                'success' => false,
                'message' => $message,
                'status' => $data['status'],
                'data' => $data,
            );
        }

        return array(
            'success' => false,
            'message' => isset($data['error']) ? $data['error'] : 'Verification failed',
            'data' => $data,
        );
    }

    /**
     * Auto-verify script with smart scheduling and retry limits
     *
     * @return void
     */
    public function auto_verify_script()
    {
        // Reset attempt counter when starting new verification cycle
        update_option('surefeedback_verification_attempts', 0);
        update_option('surefeedback_verification_status', 'pending');
        
        // Schedule initial verification after a short delay
        wp_schedule_single_event(time() + 1, 'surefeedback_auto_verify');
    }

    /**
     * Perform automatic verification with smart retry logic
     *
     * @return void
     */
    public function perform_auto_verification()
    {
        // Get current verification status and attempt count
        $current_status = get_option('surefeedback_verification_status', 'pending');
        $attempts = intval(get_option('surefeedback_verification_attempts', 0));
        
        // If already verified, schedule hourly update and exit
        if ($current_status === 'verified') {
            // Clear any existing scheduled events
            wp_clear_scheduled_hook('surefeedback_auto_verify');
            
            // Schedule hourly verification update to keep database fresh
            if (!wp_next_scheduled('surefeedback_hourly_verify')) {
                wp_schedule_event(time() + 3600, 'hourly', 'surefeedback_hourly_verify');
            }
            return;
        }
        
        // Check if we've exceeded maximum attempts (10)
        if ($attempts >= 10) {
            update_option('surefeedback_verification_status', 'failed');
            wp_clear_scheduled_hook('surefeedback_auto_verify');
            return;
        }
        
        // Increment attempt counter
        ++$attempts;
        update_option('surefeedback_verification_attempts', $attempts);
        
        // Perform verification
        $verification_result = $this->verify_script_integration();
        $verified = $verification_result['success'] && $verification_result['verified'];
        
        if ($verified) {
            // Success! Update status and switch to hourly updates
            update_option('surefeedback_verification_status', 'verified');
            
            // Clear minute-based scheduling
            wp_clear_scheduled_hook('surefeedback_auto_verify');
            
            // Schedule hourly updates to keep database fresh
            wp_schedule_event(time() + 3600, 'hourly', 'surefeedback_hourly_verify');
        } else {
            // Not yet verified, schedule next attempt in 1 minute
            $status = $verification_result['status'] ?? 'unknown';
            update_option('surefeedback_verification_status', 'pending');
            
            if ($attempts < 10) {
                // Schedule next attempt in 1 minute
                wp_schedule_single_event(time() + 1, 'surefeedback_auto_verify');
            } else {
                update_option('surefeedback_verification_status', 'failed');
            }
        }
    }

    /**
     * Perform hourly verification update to keep database fresh
     *
     * @return void
     */
    public function perform_hourly_verification_update()
    {
        // Perform verification to get fresh data
        $verification_result = $this->verify_script_integration();
        $verified = $verification_result['success'] && $verification_result['verified'];
        
        if ($verified) {
            update_option('surefeedback_verification_status', 'verified');
        } else {
            // Verification failed, switch back to retry mode
            update_option('surefeedback_verification_status', 'pending');
            update_option('surefeedback_verification_attempts', 0);
            
            // Cancel hourly updates and restart minute-based verification
            wp_clear_scheduled_hook('surefeedback_hourly_verify');
            wp_schedule_single_event(time() + 60, 'surefeedback_auto_verify');
            
        }
    }

    /**
     * Verify script integration with SaaS platform (comprehensive version)
     *
     * @return array
     */
    public function verify_script_integration()
    {
        
        $script_token = get_option('surefeedback_script_token');
        $site_token = get_option('surefeedback_api_key');
        $parent_url = get_option('surefeedback_parent_url');
        $verification_status = get_option('surefeedback_verification_status');
        $verification_attempts = get_option('surefeedback_verification_attempts');
        
        
        if ((!$script_token && !$site_token) || !$parent_url) {
            return array(
                'success' => false,
                'message' => 'Missing script token or parent URL',
            );
        }

        // Use script_token if available, otherwise fall back to site_token
        $token_to_use = $script_token ?: $site_token;

        // Call the widget verification endpoint using the parent URL directly
        // The parent URL should contain the actual domain/port of the SaaS service
        $verification_url = trailingslashit($parent_url) . 'api/v1/admin/verify-integration?script_token=' . $token_to_use;
        
        
        $response = wp_remote_get($verification_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'SureFeedback-WordPress-Plugin/1.0',
                'Authorization' => 'Bearer ' . $token_to_use,
            ),
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            return array(
                'success' => false,
                'message' => 'Failed to connect to verification service: [' . $error_code . '] ' . $error_message,
                'error_code' => $error_code,
                'full_error' => $response->get_error_messages(),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $data = json_decode($response_body, true);
        

        // The widget verification endpoint returns 'integrated' instead of 'success'
        if ($response_code === 200 && isset($data['integrated']) && $data['integrated']) {
            // Store verification result
            update_option('surefeedback_last_verification', current_time('mysql'));
            update_option('surefeedback_verification_status', 'verified');
            
            // Extract verification details
            $verification_data = $data['verification'] ?? array();
            
            return array(
                'success' => true,
                'verified' => true,
                'message' => $data['message'] ?? 'Integration verified successfully',
                'data' => $data,
                'verification' => $verification_data,
            );
        }

        // Handle specific error cases
        if (isset($data['status'])) {
            $error_messages = array(
                'SCRIPT_NOT_LOADED' => 'Script integration is valid but widget is not currently loaded on website',
                'TOKEN_NOT_FOUND' => 'Invalid site token or site is inactive',
                'INVALID_TOKEN_FORMAT' => 'Invalid token format',
                'VERIFICATION_ERROR' => 'Failed to verify script integration',
            );
            
            $message = $error_messages[$data['status']] ?? ($data['error'] ?? 'Verification failed');
            
            
            return array(
                'success' => false,
                'message' => $message,
                'status' => $data['status'],
                'data' => $data,
            );
        }


        return array(
            'success' => false,
            'message' => isset($data['error']) ? $data['error'] : 'Verification failed',
            'data' => $data,
        );
    }
}