<?php

namespace SureFeedback\Http\Controllers\Api;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Http\Requests\ConnectionRequest;
use SureFeedback\Http\Requests\VerifyConnectionRequest;
use SureFeedback\Repositories\ConnectionRepository;
use SureFeedback\Repositories\SettingsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Connection Controller
 *
 * Handles all connection-related API endpoints including
 * status checks, verification, and connection management.
 *
 * @package SureFeedback\App\Http\Controllers\Api
 * @author Anurag Singh <anurags@bsf.io>
 */
class ConnectionController extends Controller
{
    /**
     * Connection Repository
     *
     * @var ConnectionRepository
     */
    protected $connectionRepository;

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
        $this->connectionRepository = new ConnectionRepository();
        $this->settingsRepository = new SettingsRepository();
    }
    /**
     * Get connection status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function status(WP_REST_Request $request)
    {
        try {
            $nonce_result = $this->validateNonce($request);
            if (is_wp_error($nonce_result)) {
                return $nonce_result;
            }
            
            $connectionData = $this->connectionRepository->getConnectionStatus();
            
            $connection_data = [
                'connected' => $this->isConnected(),
                'parent_url' => $connectionData['parent_url'],
                'access_token' => !empty($connectionData['access_token']),
                'last_check' => $connectionData['last_check'],
                'status' => $connectionData['connected'] ? 'connected' : 'disconnected',
                'health_score' => $this->calculateHealthScore()
            ];
            
            $this->logInfo('Connection status requested', $connection_data);
            
            return $this->success($connection_data);
            
        } catch (\Exception $e) {
            $this->logError('Connection status error: ' . $e->getMessage());
            return $this->error('Failed to get connection status', 500);
        }
    }
    
    /**
     * Verify connection with parent site
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function verify(WP_REST_Request $request)
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
            
            $validation = $this->validate($request, [
                'parent_url' => 'required|url',
                'access_token' => 'required|string|min:10'
            ]);
            
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            $parent_url = sanitize_url($request->get_param('parent_url'));
            $access_token = sanitize_text_field($request->get_param('access_token'));
            
            // Perform verification request
            $verification_result = $this->performVerification($parent_url, $access_token);
            
            if ($verification_result['success']) {
                // Save connection details
                update_option('surefeedback_parent_url', $parent_url);
                update_option('surefeedback_access_token', $access_token);
                update_option('surefeedback_connected', true);
                update_option('surefeedback_last_connection_check', time());
                
                $this->logInfo('Connection verified successfully', [
                    'parent_url' => $parent_url,
                    'token_length' => strlen($access_token)
                ]);
                
                return $this->success([
                    'message' => 'Connection verified successfully',
                    'connected' => true,
                    'parent_url' => $parent_url,
                    'verified_at' => current_time('mysql')
                ]);
            } else {
                $this->logError('Connection verification failed', $verification_result);
                return $this->error($verification_result['message'] ?? 'Verification failed', 400);
            }
            
        } catch (\Exception $e) {
            $this->logError('Connection verification error: ' . $e->getMessage());
            return $this->error('Verification process failed', 500);
        }
    }
    
    /**
     * Establish connection
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function connect(WP_REST_Request $request)
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
            
            $validation = $this->validate($request, [
                'parent_url' => 'required|url',
                'site_token' => 'required|string',
                'signature' => 'required|string'
            ]);
            
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            $parent_url = sanitize_url($request->get_param('parent_url'));
            $site_token = sanitize_text_field($request->get_param('site_token'));
            $signature = sanitize_text_field($request->get_param('signature'));
            
            // Verify signature
            if (!$this->verifySignature($site_token, $signature)) {
                $this->logError('Invalid signature during connection attempt');
                return $this->error('Invalid signature', 403);
            }
            
            // Generate access token
            $access_token = wp_generate_password(32, false);
            
            // Save connection data
            update_option('surefeedback_parent_url', $parent_url);
            update_option('surefeedback_access_token', $access_token);
            update_option('surefeedback_site_token', $site_token);
            update_option('surefeedback_connected', true);
            update_option('surefeedback_connection_date', current_time('mysql'));
            
            $this->logInfo('Connection established', [
                'parent_url' => $parent_url,
                'site_token' => substr($site_token, 0, 8) . '...'
            ]);
            
            return $this->success([
                'message' => 'Connection established successfully',
                'access_token' => $access_token,
                'connected' => true,
                'connected_at' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Connection establishment error: ' . $e->getMessage());
            return $this->error('Failed to establish connection', 500);
        }
    }
    
    /**
     * Disconnect from parent site
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function disconnect(WP_REST_Request $request)
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
            
            // Notify parent site about disconnection
            $this->notifyParentSiteDisconnection();
            
            // Clear connection data through repository
            $this->connectionRepository->disconnect();
            
            $this->logInfo('Connection disconnected');
            
            return $this->success([
                'message' => 'Disconnected successfully',
                'connected' => false,
                'disconnected_at' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Disconnection error: ' . $e->getMessage());
            return $this->error('Failed to disconnect', 500);
        }
    }
    
    /**
     * Health check endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function health(WP_REST_Request $request)
    {
        try {
            $health_data = [
                'status' => 'healthy',
                'plugin_version' => SUREFEEDBACK_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'connection_status' => $this->getConnectionStatus(),
                'last_check' => current_time('mysql'),
                'uptime' => $this->getUptime(),
                'memory_usage' => $this->getMemoryUsage()
            ];
            
            return $this->success($health_data);
            
        } catch (\Exception $e) {
            return $this->error('Health check failed', 500);
        }
    }
    
    /**
     * Check if site is connected
     *
     * @return bool
     */
    private function isConnected(): bool
    {
        $connectionData = $this->connectionRepository->getConnectionStatus();
        return $connectionData['connected'] &&
               !empty($connectionData['parent_url']) &&
               !empty($connectionData['access_token']);
    }
    
    /**
     * Get detailed connection status
     *
     * @return string
     */
    private function getConnectionStatus(): string
    {
        if (!$this->isConnected()) {
            return 'disconnected';
        }
        
        $last_check = get_option('surefeedback_last_connection_check', 0);
        $check_threshold = 5 * MINUTE_IN_SECONDS;
        
        if ($last_check && (time() - $last_check) > $check_threshold) {
            return 'stale';
        }
        
        return 'connected';
    }
    
    /**
     * Calculate connection health score
     *
     * @return int
     */
    private function calculateHealthScore(): int
    {
        $score = 0;
        
        // Base connection (40 points)
        if ($this->isConnected()) {
            $score += 40;
        }
        
        // Recent activity (30 points)
        $last_check = get_option('surefeedback_last_connection_check', 0);
        if ($last_check && (time() - $last_check) < HOUR_IN_SECONDS) {
            $score += 30;
        }
        
        // Valid configuration (20 points)
        if (!empty(get_option('surefeedback_parent_url', ''))) {
            $score += 20;
        }
        
        // Plugin health (10 points)
        if (defined('SUREFEEDBACK_VERSION')) {
            $score += 10;
        }
        
        return $score;
    }
    
    /**
     * Perform verification with parent site
     *
     * @param string $parent_url
     * @param string $access_token
     * @return array
     */
    private function performVerification(string $parent_url, string $access_token): array
    {
        $verification_url = trailingslashit($parent_url) . 'wp-json/surefeedback/v1/verify-site';
        
        $response = wp_remote_post($verification_url, [
            'headers' => [
                'X-SureFeedback-Token' => $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'plugin_version' => SUREFEEDBACK_VERSION
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return [
                'success' => false,
                'message' => 'Verification failed with status: ' . $response_code
            ];
        }
        
        $data = json_decode($response_body, true);
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Verify signature
     *
     * @param string $site_token
     * @param string $signature
     * @return bool
     */
    private function verifySignature(string $site_token, string $signature): bool
    {
        $expected_signature = hash_hmac('sha256', $site_token, SECURE_AUTH_KEY);
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Notify parent site about disconnection
     *
     * @return void
     */
    private function notifyParentSiteDisconnection(): void
    {
        $parent_url = get_option('surefeedback_parent_url', '');
        $access_token = get_option('surefeedback_access_token', '');
        
        if (empty($parent_url) || empty($access_token)) {
            return;
        }
        
        $disconnect_url = trailingslashit($parent_url) . 'wp-json/surefeedback/v1/site-disconnected';
        
        wp_remote_post($disconnect_url, [
            'headers' => [
                'X-SureFeedback-Token' => $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'site_url' => home_url(),
                'disconnected_at' => current_time('mysql')
            ]),
            'timeout' => 10,
            'blocking' => false
        ]);
    }
    
    /**
     * Get system uptime
     *
     * @return string
     */
    private function getUptime(): string
    {
        $connection_date = get_option('surefeedback_connection_date', '');
        if (empty($connection_date)) {
            return 'Unknown';
        }
        
        $connection_time = strtotime($connection_date);
        $uptime_seconds = time() - $connection_time;
        
        return human_time_diff($connection_time, time());
    }
    
    /**
     * Get memory usage information
     *
     * @return array
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Reset site connection completely
     * This removes all SureFeedback data from the WordPress database
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function reset(WP_REST_Request $request)
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

            // Notify parent site about disconnection (if connected)
            if ($this->connectionRepository->isConnected()) {
                $this->notifyParentSiteDisconnection();
            }

            // Delete all SureFeedback options from the database
            $surefeedback_options = [
                'surefeedback_access_token',
                'surefeedback_admin_can_comment',
                'surefeedback_api_url',
                'surefeedback_connection_status',
                'surefeedback_domain',
                'surefeedback_id',
                'surefeedback_installation_date',
                'surefeedback_last_verification',
                'surefeedback_organization_id',
                'surefeedback_parent_url',
                'surefeedback_role_can_comment',
                'surefeedback_script_token',
                'surefeedback_settings',
                'surefeedback_site_name',
                'surefeedback_verification_status',
                'surefeedback_white_label_settings',
                'surefeedback_widget_enabled',
                'surefeedback_connected',
                'surefeedback_signature',
                'surefeedback_user_id',
                'surefeedback_user_email',
                'surefeedback_connection_time',
                'surefeedback_last_check',
                'surefeedback_guest_comments',
                'surefeedback_connection_date',
                'surefeedback_last_connection_check',
                'surefeedback_site_token',
            ];

            // Delete each option
            foreach ($surefeedback_options as $option) {
                delete_option($option);
            }

            // Clear any cached data
            wp_cache_delete('surefeedback_connection_status');
            wp_cache_delete('surefeedback_settings');

            // Clear any transients
            delete_transient('surefeedback_connection_check');
            delete_transient('surefeedback_verification_status');

            $this->logInfo('Site connection reset completely');

            return $this->success([
                'message' => 'Site connection reset successfully',
                'connected' => false,
                'reset_at' => current_time('mysql'),
                'status' => 'reset'
            ]);

        } catch (\Exception $e) {
            $this->logError('Reset error: ' . $e->getMessage());
            return $this->error('Failed to reset site connection', 500);
        }
    }

    /**
     * Handle webhook from SureFeedback API
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function webhook(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $data = $request->get_json_params();
            
            if (empty($data)) {
                $data = $request->get_params();
            }

            $this->logInfo('Webhook received', ['data' => $data]);

            // Validate required fields
            if (empty($data['success']) || empty($data['site_token']) || empty($data['site_id'])) {
                $this->logError('Webhook missing required fields', ['received_data' => $data]);
                return $this->error('Missing required webhook data', 400);
            }

            // Check if this is a successful connection
            if ($data['success'] === '1' || $data['success'] === 1 || $data['success'] === true) {
                // Save connection data
                update_option('surefeedback_connection_status', 'connected');
                update_option('surefeedback_id', sanitize_text_field($data['site_id']));
                update_option('surefeedback_access_token', sanitize_text_field($data['site_token']));
                
                // Save additional fields from the API response
                if (!empty($data['script_token'])) {
                    update_option('surefeedback_script_token', sanitize_text_field($data['script_token']));
                }
                
                if (!empty($data['parent_url'])) {
                    update_option('surefeedback_parent_url', esc_url_raw($data['parent_url']));
                }
                
                if (!empty($data['organization_id'])) {
                    update_option('surefeedback_organization_id', sanitize_text_field($data['organization_id']));
                }
                
                if (!empty($data['site_name'])) {
                    update_option('surefeedback_site_name', sanitize_text_field($data['site_name']));
                }
                
                if (!empty($data['domain'])) {
                    update_option('surefeedback_domain', esc_url_raw($data['domain']));
                }

                // Save API URL from environment
                $base_api_url = surefeedback_get_base_api_url();
                update_option('surefeedback_api_url', $base_api_url);

                // Enable widget by default
                update_option('surefeedback_widget_enabled', true);
                
                // Update last verification time
                update_option('surefeedback_last_verification', current_time('mysql'));
                update_option('surefeedback_verification_status', 'verified');

                // Clear any cached data
                wp_cache_delete('surefeedback_connection_status');
                wp_cache_delete('surefeedback_settings');
                delete_transient('surefeedback_connection_check');

                $this->logInfo('Webhook processed successfully - site connected', [
                    'site_id' => $data['site_id'],
                    'organization_id' => $data['organization_id'] ?? null,
                    'domain' => $data['domain'] ?? null
                ]);

                return $this->success([
                    'message' => 'Webhook processed successfully',
                    'connected' => true,
                    'site_id' => $data['site_id'],
                    'processed_at' => current_time('mysql')
                ]);
            } else {
                $this->logError('Webhook indicated failure', ['webhook_data' => $data]);
                return $this->error('Webhook indicated connection failure', 400);
            }

        } catch (\Exception $e) {
            $this->logError('Webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->get_params()
            ]);
            return $this->error('Failed to process webhook', 500);
        }
    }
}