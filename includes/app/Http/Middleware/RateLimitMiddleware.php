<?php

namespace SureFeedback\Http\Middleware;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;

/**
 * Rate Limiting Middleware
 *
 * Implements rate limiting to prevent abuse of API endpoints.
 *
 * @package SureFeedback\App\Http\Middleware
 * @author Anurag Singh <anurags@bsf.io>
 */
class RateLimitMiddleware extends Middleware
{
    /**
     * Rate limit configurations for different endpoints
     *
     * @var array
     */
    protected $rateLimits = [
        // Connection endpoints - more restrictive due to external calls
        '/surefeedback/v1/connection/connect' => ['limit' => 5, 'window' => 300],    // 5 per 5 min
        '/surefeedback/v1/connection/verify' => ['limit' => 10, 'window' => 300],    // 10 per 5 min
        '/surefeedback/v1/connection/test' => ['limit' => 10, 'window' => 300],      // 10 per 5 min
        
        // Settings endpoints
        '/surefeedback/v1/settings' => ['limit' => 30, 'window' => 300],             // 30 per 5 min
        
        // Dashboard endpoints - less restrictive for read operations
        '/surefeedback/v1/dashboard/*' => ['limit' => 100, 'window' => 300],         // 100 per 5 min
        
        // Default rate limit
        'default' => ['limit' => 60, 'window' => 300],                               // 60 per 5 min
    ];

    /**
     * Handle rate limiting middleware
     *
     * @param WP_REST_Request $request
     * @param callable $next
     * @return mixed
     */
    public function handle(WP_REST_Request $request, callable $next)
    {
        $route = $request->get_route();
        $clientId = $this->getClientIdentifier($request);
        
        // Get rate limit configuration for this endpoint
        $config = $this->getRateLimitConfig($route);
        
        // Check if rate limit is exceeded
        if ($this->isRateLimitExceeded($clientId, $route, $config)) {
            $this->log('rate_limit_exceeded', 'Rate limit exceeded', [
                'client_id' => $clientId,
                'route' => $route,
                'limit' => $config['limit'],
                'window' => $config['window']
            ]);
            
            return $this->error(
                sprintf(
                    'Rate limit exceeded. Maximum %d requests per %d seconds allowed.',
                    $config['limit'],
                    $config['window']
                ),
                429,
                [
                    'retry_after' => $this->getRetryAfter($clientId, $route, $config),
                    'limit' => $config['limit'],
                    'window' => $config['window']
                ]
            );
        }

        // Record this request
        $this->recordRequest($clientId, $route, $config);

        return $next($request);
    }

    /**
     * Get client identifier for rate limiting
     *
     * @param WP_REST_Request $request
     * @return string
     */
    protected function getClientIdentifier(WP_REST_Request $request): string
    {
        // Use user ID if logged in, otherwise use IP address
        $userId = $this->getCurrentUserId();
        
        if ($userId > 0) {
            return 'user_' . $userId;
        }
        
        return 'ip_' . $this->getClientIp($request);
    }

    /**
     * Get rate limit configuration for a route
     *
     * @param string $route
     * @return array
     */
    protected function getRateLimitConfig(string $route): array
    {
        // Check for exact match first
        if (isset($this->rateLimits[$route])) {
            return $this->rateLimits[$route];
        }

        // Check for wildcard matches
        foreach ($this->rateLimits as $pattern => $config) {
            if (strpos($pattern, '*') !== false) {
                $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regex . '$/', $route)) {
                    return $config;
                }
            }
        }

        // Return default configuration
        return $this->rateLimits['default'];
    }

    /**
     * Check if rate limit is exceeded
     *
     * @param string $clientId
     * @param string $route
     * @param array $config
     * @return bool
     */
    protected function isRateLimitExceeded(string $clientId, string $route, array $config): bool
    {
        $key = $this->getTransientKey($clientId, $route);
        $requests = get_transient($key);
        
        if ($requests === false) {
            return false; // No previous requests recorded
        }

        return count($requests) >= $config['limit'];
    }

    /**
     * Record a request for rate limiting
     *
     * @param string $clientId
     * @param string $route
     * @param array $config
     * @return void
     */
    protected function recordRequest(string $clientId, string $route, array $config): void
    {
        $key = $this->getTransientKey($clientId, $route);
        $requests = get_transient($key);
        
        if ($requests === false) {
            $requests = [];
        }

        // Add current timestamp
        $requests[] = time();
        
        // Remove old requests outside the window
        $cutoff = time() - $config['window'];
        $requests = array_filter($requests, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });

        // Store updated requests list
        set_transient($key, array_values($requests), $config['window']);
    }

    /**
     * Get retry after seconds
     *
     * @param string $clientId
     * @param string $route
     * @param array $config
     * @return int
     */
    protected function getRetryAfter(string $clientId, string $route, array $config): int
    {
        $key = $this->getTransientKey($clientId, $route);
        $requests = get_transient($key);
        
        if ($requests === false || empty($requests)) {
            return 0;
        }

        // Get the oldest request in the current window
        $oldestRequest = min($requests);
        $retryAfter = ($oldestRequest + $config['window']) - time();
        
        return max(0, $retryAfter);
    }

    /**
     * Get transient key for rate limiting
     *
     * @param string $clientId
     * @param string $route
     * @return string
     */
    protected function getTransientKey(string $clientId, string $route): string
    {
        return 'surefeedback_rate_limit_' . md5($clientId . '_' . $route);
    }

    /**
     * Reset rate limit for a client and route
     *
     * @param string $clientId
     * @param string $route
     * @return bool
     */
    public function resetRateLimit(string $clientId, string $route): bool
    {
        $key = $this->getTransientKey($clientId, $route);
        return delete_transient($key);
    }

    /**
     * Get current rate limit status for a client
     *
     * @param string $clientId
     * @param string $route
     * @return array
     */
    public function getRateLimitStatus(string $clientId, string $route): array
    {
        $config = $this->getRateLimitConfig($route);
        $key = $this->getTransientKey($clientId, $route);
        $requests = get_transient($key);
        
        if ($requests === false) {
            $requests = [];
        }

        $remaining = max(0, $config['limit'] - count($requests));
        $retryAfter = $this->getRetryAfter($clientId, $route, $config);

        return [
            'limit' => $config['limit'],
            'remaining' => $remaining,
            'window' => $config['window'],
            'retry_after' => $retryAfter,
            'exceeded' => $remaining === 0,
        ];
    }
}
