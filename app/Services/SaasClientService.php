<?php

namespace SureFeedback\Services;

use SureFeedback\Exceptions\API_Exception;
use WP_Error;

/**
 * SaaS Client Service
 *
 * Handles all SaaS platform communications and integrations.
 * This service manages connection verification, script integration,
 * and data synchronization with the parent SaaS platform.
 *
 * @package SureFeedback\App\Services
 * @author Anurag Singh <anurags@bsf.io>
 */
class SaasClientService
{
    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url;

    /**
     * Authentication token
     *
     * @var string
     */
    private $access_token;

    /**
     * Request timeout
     *
     * @var int
     */
    private $timeout;

    /**
     * Max retry attempts
     *
     * @var int
     */
    private $max_retries;

    /**
     * Environment mode
     *
     * @var string
     */
    private $environment;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setup_configuration();
        $this->init_hooks();
    }

    /**
     * Setup service configuration
     *
     * @return void
     */
    private function setup_configuration(): void
    {
        $this->environment = $this->get_environment_mode();
        $this->api_base_url = $this->get_api_url();
        $this->access_token = get_option('surefeedback_access_token', '');
        $this->timeout = 30;
        $this->max_retries = 3;
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Schedule verification events
        add_action('init', [$this, 'schedule_verification_events']);
        
        // Handle AJAX requests
        add_action('wp_ajax_surefeedback_verify_connection', [$this, 'ajax_verify_connection']);
        add_action('wp_ajax_surefeedback_disconnect', [$this, 'ajax_disconnect']);
    }

    /**
     * Schedule automatic verification events
     *
     * @return void
     */
    public function schedule_verification_events(): void
    {
        // Schedule hourly verification if not already scheduled
        if (!wp_next_scheduled('surefeedback_hourly_verify')) {
            wp_schedule_event(time(), 'hourly', 'surefeedback_hourly_verify');
        }

        // Schedule auto-verification if connection exists but verification is pending
        $verification_status = get_option('surefeedback_verification_status', 'pending');
        if ($verification_status === 'pending' && !wp_next_scheduled('surefeedback_auto_verify')) {
            wp_schedule_single_event(time() + 60, 'surefeedback_auto_verify');
        }
    }

    /**
     * Auto-verify script with smart scheduling and retry limits
     *
     * @return void
     */
    public function auto_verify_script(): void
    {
        $retry_count = get_option('surefeedback_retry_count', 0);
        $max_retries = 5;

        if ($retry_count >= $max_retries) {
            update_option('surefeedback_verification_status', 'failed');
            return;
        }

        $result = $this->verify_script_integration();

        if ($result['success']) {
            delete_option('surefeedback_retry_count');
            update_option('surefeedback_verification_status', 'verified');
        } else {
            $retry_count++;
            update_option('surefeedback_retry_count', $retry_count);

            // Schedule next retry with exponential backoff
            $delay = min(300 * pow(2, $retry_count - 1), 3600); // Max 1 hour delay
            wp_schedule_single_event(time() + $delay, 'surefeedback_auto_verify');
        }
    }

    /**
     * Perform automatic verification with smart retry logic
     *
     * @return void
     */
    public function perform_auto_verification(): void
    {
        $site_id = get_option('surefeedback_id');
        $access_token = get_option('surefeedback_access_token');

        if (empty($site_id) || empty($access_token)) {
            return;
        }

        $verification_data = [
            'site_id' => $site_id,
            'site_url' => home_url(),
            'plugin_version' => SUREFEEDBACK_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'verification_time' => current_time('mysql')
        ];

        $response = $this->make_api_request('verify-connection', $verification_data, 'POST');

        if (is_wp_error($response)) {
            $this->handle_verification_failure();
        } else {
            $this->handle_verification_success($response);
        }
    }

    /**
     * Perform hourly verification update to keep database fresh
     *
     * @return void
     */
    public function perform_hourly_verification_update(): void
    {
        $connection_status = get_option('surefeedback_connection_status', 'disconnected');
        
        if ($connection_status !== 'connected') {
            return;
        }

        $site_id = get_option('surefeedback_id');
        if (empty($site_id)) {
            return;
        }

        $heartbeat_data = [
            'site_id' => $site_id,
            'last_seen' => current_time('mysql'),
            'plugin_version' => SUREFEEDBACK_VERSION,
            'active_users' => $this->get_active_users_count(),
            'page_count' => wp_count_posts('page')->publish,
            'post_count' => wp_count_posts('post')->publish
        ];

        $response = $this->make_api_request('heartbeat', $heartbeat_data, 'POST');

        if (is_wp_error($response)) {
            // Error handled silently
        } else {
            update_option('surefeedback_last_heartbeat', time());
        }
    }

    /**
     * Verify script integration with SaaS platform
     *
     * @return array
     */
    public function verify_script_integration(): array
    {
        $site_id = get_option('surefeedback_id');
        $parent_url = get_option('surefeedback_parent_url');

        if (empty($site_id) || empty($parent_url)) {
            return [
                'success' => false,
                'message' => 'Missing site configuration'
            ];
        }

        $verification_data = [
            'site_id' => $site_id,
            'site_url' => home_url(),
            'site_name' => get_bloginfo('name'),
            'plugin_version' => SUREFEEDBACK_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme' => get_template(),
            'admin_email' => get_option('admin_email')
        ];

        $response = $this->make_api_request('verify-script-integration', $verification_data, 'POST');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        // Update local options based on response
        if (isset($response['access_token'])) {
            update_option('surefeedback_access_token', sanitize_text_field($response['access_token']));
        }

        if (isset($response['script_url'])) {
            update_option('surefeedback_script_url', esc_url_raw($response['script_url']));
        }

        update_option('surefeedback_last_verification', current_time('mysql'));
        update_option('surefeedback_verification_status', 'verified');

        return [
            'success' => true,
            'message' => 'Script integration verified successfully',
            'data' => $response
        ];
    }

    /**
     * Connect to SaaS platform
     *
     * @param array $connection_data Connection parameters
     * @return array
     */
    public function connect(array $connection_data): array
    {
        try {
            $required_fields = ['parent_url', 'site_token'];
            foreach ($required_fields as $field) {
                if (empty($connection_data[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }

            $parent_url = esc_url_raw($connection_data['parent_url']);
            $site_token = sanitize_text_field($connection_data['site_token']);

            // Validate parent URL
            if (!filter_var($parent_url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Invalid parent URL');
            }

            $connect_data = [
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'site_token' => $site_token,
                'plugin_version' => SUREFEEDBACK_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'admin_email' => get_option('admin_email')
            ];

            // Use parent URL for this specific request
            $old_api_url = $this->api_base_url;
            $this->api_base_url = trailingslashit($parent_url) . 'wp-json/surefeedback/v1/';

            $response = $this->make_api_request('connect-site', $connect_data, 'POST');

            // Restore original API URL
            $this->api_base_url = $old_api_url;

            if (is_wp_error($response)) {
                throw new API_Exception('Connection failed: ' . $response->get_error_message());
            }

            // Store connection details
            update_option('surefeedback_parent_url', $parent_url);
            update_option('surefeedback_site_token', $site_token);
            
            if (isset($response['site_id'])) {
                update_option('surefeedback_id', sanitize_text_field($response['site_id']));
            }

            if (isset($response['access_token'])) {
                update_option('surefeedback_access_token', sanitize_text_field($response['access_token']));
                $this->access_token = $response['access_token'];
            }

            update_option('surefeedback_connection_status', 'connected');
            update_option('surefeedback_connection_date', current_time('mysql'));

            return [
                'success' => true,
                'message' => 'Connected successfully',
                'data' => $response
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Disconnect from SaaS platform
     *
     * @return array
     */
    public function disconnect(): array
    {
        try {
            $site_id = get_option('surefeedback_id');
            
            if (!empty($site_id)) {
                // Notify parent site about disconnection
                $disconnect_data = [
                    'site_id' => $site_id,
                    'site_url' => home_url(),
                    'disconnected_at' => current_time('mysql')
                ];

                $this->make_api_request('disconnect-site', $disconnect_data, 'POST');
            }

            // Clear all connection data
            $options_to_delete = [
                'surefeedback_parent_url',
                'surefeedback_site_token',
                'surefeedback_id',
                'surefeedback_access_token',
                'surefeedback_script_url',
                'surefeedback_connection_status',
                'surefeedback_connection_date',
                'surefeedback_last_verification',
                'surefeedback_verification_status',
                'surefeedback_retry_count',
                'surefeedback_last_heartbeat'
            ];

            foreach ($options_to_delete as $option) {
                delete_option($option);
            }

            // Clear scheduled events
            wp_clear_scheduled_hook('surefeedback_auto_verify');
            wp_clear_scheduled_hook('surefeedback_hourly_verify');

            return [
                'success' => true,
                'message' => 'Disconnected successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Make API request to SaaS platform
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    private function make_api_request(string $endpoint, array $data = [], string $method = 'GET')
    {
        $url = trailingslashit($this->api_base_url) . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'SureFeedback-Client/' . SUREFEEDBACK_VERSION
            ]
        ];

        if (!empty($this->access_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
        }

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        } elseif (!empty($data) && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 400) {
            $error_message = "HTTP {$response_code}";
            if (!empty($response_body)) {
                $decoded = json_decode($response_body, true);
                if (isset($decoded['message'])) {
                    $error_message .= ': ' . $decoded['message'];
                }
            }
            return new WP_Error('api_error', $error_message);
        }

        $decoded_response = json_decode($response_body, true);
        return $decoded_response ?? [];
    }

    /**
     * Handle verification success
     *
     * @param array $response API response
     * @return void
     */
    private function handle_verification_success(array $response): void
    {
        update_option('surefeedback_verification_status', 'verified');
        update_option('surefeedback_last_verification', current_time('mysql'));
        delete_option('surefeedback_retry_count');

        // Update any additional data from response
        if (isset($response['script_url'])) {
            update_option('surefeedback_script_url', esc_url_raw($response['script_url']));
        }
    }

    /**
     * Handle verification failure
     *
     * @return void
     */
    private function handle_verification_failure(): void
    {
        $retry_count = get_option('surefeedback_retry_count', 0);
        $retry_count++;
        update_option('surefeedback_retry_count', $retry_count);

        if ($retry_count >= $this->max_retries) {
            update_option('surefeedback_verification_status', 'failed');
        } else {
            // Schedule retry with exponential backoff
            $delay = min(300 * pow(2, $retry_count - 1), 3600);
            wp_schedule_single_event(time() + $delay, 'surefeedback_auto_verify');
        }
    }

    /**
     * Get environment mode
     *
     * @return string
     */
    private function get_environment_mode(): string
    {
        return defined('SUREFEEDBACK_MODE') ? SUREFEEDBACK_MODE : 'development';
    }

    /**
     * Get API URL based on environment
     *
     * @return string
     */
    private function get_api_url(): string
    {
        return $this->environment === 'production' 
            ? 'https://api.surefeedback.com/v1/' 
            : 'http://localhost:8000/v1/';
    }

    /**
     * Get active users count
     *
     * @return int
     */
    private function get_active_users_count(): int
    {
        // Get users who have logged in within the last 30 days
        $users = get_users([
            'meta_key' => 'last_activity',
            'meta_value' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'meta_compare' => '>='
        ]);

        return count($users);
    }

    /**
     * AJAX handler for connection verification
     *
     * @return void
     */
    public function ajax_verify_connection(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $result = $this->verify_script_integration();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for disconnection
     *
     * @return void
     */
    public function ajax_disconnect(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $result = $this->disconnect();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}