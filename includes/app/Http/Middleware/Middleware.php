<?php

namespace SureFeedback\Http\Middleware;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;

/**
 * Base Middleware Class
 *
 * Provides base functionality for request middleware processing.
 *
 * @package SureFeedback\App\Http\Middleware
 * @author Anurag Singh <anurags@bsf.io>
 */
abstract class Middleware
{
    /**
     * Handle the middleware
     *
     * @param WP_REST_Request $request
     * @param callable $next
     * @return mixed
     */
    abstract public function handle(WP_REST_Request $request, callable $next);

    /**
     * Create error response
     *
     * @param string $message
     * @param int $code
     * @param array $data
     * @return WP_Error
     */
    protected function error(string $message, int $code = 400, array $data = []): WP_Error
    {
        return new WP_Error('middleware_error', $message, array_merge(['status' => $code], $data));
    }

    /**
     * Check if user has required capability
     *
     * @param string $capability
     * @param int|null $userId
     * @return bool
     */
    protected function userCan(string $capability, ?int $userId = null): bool
    {
        if ($userId) {
            return user_can($userId, $capability);
        }

        return current_user_can($capability);
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    protected function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Get request IP address
     *
     * @param WP_REST_Request $request
     * @return string
     */
    protected function getClientIp(WP_REST_Request $request): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Log middleware action
     *
     * @param string $action
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $action, string $message, array $context = []): void
    {
        // Logging disabled
    }
}
