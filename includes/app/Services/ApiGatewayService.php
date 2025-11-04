<?php

namespace SureFeedback\Services;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * API Gateway Service
 *
 * Centralized service for handling all external API requests to SureFeedback API.
 * Provides consistent request handling, error management, and response formatting.
 *
 * @package SureFeedback\Services
 * @author Anurag Singh <anurags@bsf.io>
 */
class ApiGatewayService
{
    /**
     * Base API URL
     *
     * @var string
     */
    private $base_url;

    /**
     * Default request timeout in seconds
     *
     * @var int
     */
    private $timeout = 30;

    /**
     * Default headers for all requests
     *
     * @var array
     */
    private $default_headers;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->base_url = $this->getBaseApiUrl();
        $this->default_headers = $this->buildDefaultHeaders();
    }

    /**
     * Verify integration with SureFeedback Laravel API
     *
     * Calls the Laravel backend to verify if the site integration is complete
     * and if the widget script is properly loaded.
     *
     * @param string $site_token Site access token
     * @param string|null $jwt_token Optional JWT token for authentication
     * @return array|WP_Error
     */
    public function verifyIntegration(string $site_token, ?string $jwt_token = null)
    {
        $endpoint = '/api/v1/admin/verify-integration';
        
        $query_params = [
            'script_token' => $site_token
        ];

        // Add JWT token to headers for authentication if provided
        $headers = [];
        if (!empty($jwt_token)) {
            $headers['Authorization'] = 'Bearer ' . $jwt_token;
        }

        return $this->get($endpoint, $query_params, $headers);
    }

    /**
     * Disconnect site from SureFeedback Laravel API
     *
     * Notifies the Laravel backend to disconnect the site and clean up data
     *
     * @param string $site_id Site ID
     * @param string $site_token Site access token
     * @param string $domain Site domain
     * @param string $jwt_token JWT token for authentication
     * @return array|WP_Error
     */
    public function disconnectSite(string $site_id, string $site_token, string $domain, string $jwt_token)
    {
        $endpoint = '/api/v1/sites/wordpress/disconnect';
        
        $data = [
            'site_id' => $site_id,
            'site_token' => $site_token,
            'domain' => $domain,
            'website_url' => home_url(),
            'force' => true,
        ];

        // Add JWT token to headers for authentication
        $headers = [
            'Authorization' => 'Bearer ' . $jwt_token,
        ];

        return $this->post($endpoint, $data, $headers);
    }

    /**
     * Perform GET request
     *
     * @param string $endpoint API endpoint (relative path)
     * @param array $query_params Query parameters
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function get(string $endpoint, array $query_params = [], array $headers = [])
    {
        $url = $this->buildUrl($endpoint, $query_params);
        $headers = array_merge($this->default_headers, $headers);

        $args = [
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => $this->timeout,
            'sslverify' => $this->shouldVerifySsl(),
        ];

        return $this->executeRequest($url, $args, 'GET');
    }

    /**
     * Perform POST request
     *
     * @param string $endpoint API endpoint (relative path)
     * @param array $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function post(string $endpoint, array $data = [], array $headers = [])
    {
        $url = $this->buildUrl($endpoint);
        $headers = array_merge($this->default_headers, $headers);

        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $this->timeout,
            'sslverify' => $this->shouldVerifySsl(),
        ];

        return $this->executeRequest($url, $args, 'POST');
    }

    /**
     * Perform PUT request
     *
     * @param string $endpoint API endpoint (relative path)
     * @param array $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function put(string $endpoint, array $data = [], array $headers = [])
    {
        $url = $this->buildUrl($endpoint);
        $headers = array_merge($this->default_headers, $headers);

        $args = [
            'method' => 'PUT',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $this->timeout,
            'sslverify' => $this->shouldVerifySsl(),
        ];

        return $this->executeRequest($url, $args, 'PUT');
    }

    /**
     * Perform DELETE request
     *
     * @param string $endpoint API endpoint (relative path)
     * @param array $query_params Query parameters
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function delete(string $endpoint, array $query_params = [], array $headers = [])
    {
        $url = $this->buildUrl($endpoint, $query_params);
        $headers = array_merge($this->default_headers, $headers);

        $args = [
            'method' => 'DELETE',
            'headers' => $headers,
            'timeout' => $this->timeout,
            'sslverify' => $this->shouldVerifySsl(),
        ];

        return $this->executeRequest($url, $args, 'DELETE');
    }

    /**
     * Execute HTTP request
     *
     * @param string $url Full URL
     * @param array $args Request arguments
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    private function executeRequest(string $url, array $args, string $method)
    {
        // Log request details (sanitize sensitive data)
        $this->logRequest($method, $url, $args);

        // Execute request based on method
        switch ($method) {
            case 'GET':
                $response = wp_remote_get($url, $args);
                break;
            case 'POST':
                $response = wp_remote_post($url, $args);
                break;
            default:
                $response = wp_remote_request($url, $args);
                break;
        }

        // Handle WordPress HTTP API errors
        if (is_wp_error($response)) {
            $this->logError($method, $url, $response->get_error_message());
            return new WP_Error(
                'api_request_failed',
                sprintf('API request failed: %s', $response->get_error_message()),
                ['status' => 500]
            );
        }

        // Parse response
        return $this->parseResponse($response, $method, $url);
    }

    /**
     * Parse HTTP response
     *
     * @param array|WP_Error $response WordPress HTTP response
     * @param string $method HTTP method
     * @param string $url Request URL
     * @return array|WP_Error
     */
    private function parseResponse($response, string $method, string $url)
    {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Decode JSON response
        $decoded = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError($method, $url, 'Invalid JSON response', [
                'response_code' => $response_code,
                'json_error' => json_last_error_msg(),
            ]);

            return new WP_Error(
                'invalid_response',
                'Invalid JSON response from API',
                ['status' => 500, 'raw_response' => $response_body]
            );
        }

        // Log response
        $this->logResponse($method, $url, $response_code, $decoded);

        // Handle HTTP error codes
        if ($response_code >= 400) {
            $error_message = $decoded['message'] ?? 'API request failed';
            $error_code = $decoded['error_code'] ?? 'api_error';

            return new WP_Error(
                $error_code,
                $error_message,
                [
                    'status' => $response_code,
                    'response' => $decoded
                ]
            );
        }

        // Return successful response
        return [
            'success' => $decoded['success'] ?? true,
            'data' => $decoded,
            'status_code' => $response_code,
        ];
    }

    /**
     * Build full URL from endpoint and query parameters
     *
     * @param string $endpoint API endpoint
     * @param array $query_params Query parameters
     * @return string
     */
    private function buildUrl(string $endpoint, array $query_params = []): string
    {
        // Remove leading slash from endpoint if present
        $endpoint = ltrim($endpoint, '/');
        
        // Build base URL
        $url = trailingslashit($this->base_url) . $endpoint;

        // Add query parameters if present
        if (!empty($query_params)) {
            $url = add_query_arg($query_params, $url);
        }

        return $url;
    }

    /**
     * Build default headers for all requests
     *
     * @return array
     */
    private function buildDefaultHeaders(): array
    {
        return [
            'accept' => '*/*',
            'accept-language' => 'en-GB,en-US;q=0.9,en;q=0.8',
            'content-type' => 'application/json',
            'origin' => home_url(),
            'referer' => home_url('/'),
            'sec-ch-ua' => '"Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"' . $this->getPlatform() . '"',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-site',
            'user-agent' => $this->getUserAgent(),
            'x-requested-with' => 'XMLHttpRequest',
            'x-wp-version' => get_bloginfo('version'),
            'x-plugin-version' => SUREFEEDBACK_VERSION ?? '1.0.0',
        ];
    }

    /**
     * Get base API URL
     *
     * @return string
     */
    private function getBaseApiUrl(): string
    {
        if (function_exists('surefeedback_get_base_api_url')) {
            return surefeedback_get_base_api_url();
        }

        if (function_exists('surefeedback_get_app_url')) {
            $app_url = surefeedback_get_app_url();
            return str_replace('app.', 'api.', $app_url);
        }

        // Fallback to localhost for development
        return 'http://localhost:8000';
    }

    /**
     * Get user agent string
     *
     * @return string
     */
    private function getUserAgent(): string
    {
        // Use server's user agent if available
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }

        // Fallback to default user agent
        return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
    }

    /**
     * Get platform identifier
     *
     * @return string
     */
    private function getPlatform(): string
    {
        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 'mac') !== false) {
            return 'macOS';
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 'windows') !== false) {
            return 'Windows';
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 'linux') !== false) {
            return 'Linux';
        }

        return 'Unknown';
    }

    /**
     * Determine if SSL should be verified
     *
     * @return bool
     */
    private function shouldVerifySsl(): bool
    {
        // Disable SSL verification in local development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return false;
        }

        // Always verify SSL in production
        return true;
    }

    /**
     * Log API request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $args Request arguments
     * @return void
     */
    private function logRequest(string $method, string $url, array $args): void
    {
        // Logging disabled
    }

    /**
     * Log API response
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param int $status_code Response status code
     * @param array $data Response data
     * @return void
     */
    private function logResponse(string $method, string $url, int $status_code, array $data): void
    {
        // Logging disabled
    }

    /**
     * Log API error
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param string $error_message Error message
     * @param array $context Additional context
     * @return void
     */
    private function logError(string $method, string $url, string $error_message, array $context = []): void
    {
        // Logging disabled
    }

    /**
     * Set custom timeout
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Add custom header
     *
     * @param string $key Header key
     * @param string $value Header value
     * @return self
     */
    public function addHeader(string $key, string $value): self
    {
        $this->default_headers[$key] = $value;
        return $this;
    }

    /**
     * Remove header
     *
     * @param string $key Header key
     * @return self
     */
    public function removeHeader(string $key): self
    {
        if (isset($this->default_headers[$key])) {
            unset($this->default_headers[$key]);
        }
        return $this;
    }

    /**
     * Get current headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->default_headers;
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->base_url;
    }
}
