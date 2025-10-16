<?php

namespace SureFeedback\App\Http\Middleware;

use WP_REST_Request;
use WP_Error;

/**
 * Middleware Manager
 *
 * Manages and executes middleware stack for API requests.
 *
 * @package SureFeedback\App\Http\Middleware
 */
class MiddlewareManager
{
    /**
     * Registered middleware stack
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Global middleware applied to all routes
     *
     * @var array
     */
    protected $globalMiddleware = [
        RateLimitMiddleware::class,
        AuthMiddleware::class,
        ValidationMiddleware::class,
    ];

    /**
     * Route-specific middleware
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * Middleware instances cache
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Add global middleware
     *
     * @param string $middleware
     * @return void
     */
    public function addGlobalMiddleware(string $middleware): void
    {
        if (!in_array($middleware, $this->globalMiddleware)) {
            $this->globalMiddleware[] = $middleware;
        }
    }

    /**
     * Add route-specific middleware
     *
     * @param string $route
     * @param string|array $middleware
     * @return void
     */
    public function addRouteMiddleware(string $route, $middleware): void
    {
        if (!isset($this->routeMiddleware[$route])) {
            $this->routeMiddleware[$route] = [];
        }

        $middlewareList = is_array($middleware) ? $middleware : [$middleware];
        
        foreach ($middlewareList as $mw) {
            if (!in_array($mw, $this->routeMiddleware[$route])) {
                $this->routeMiddleware[$route][] = $mw;
            }
        }
    }

    /**
     * Process middleware stack for a request
     *
     * @param WP_REST_Request $request
     * @param callable $controller
     * @return mixed
     */
    public function process(WP_REST_Request $request, callable $controller)
    {
        $route = $request->get_route();
        $middlewareStack = $this->getMiddlewareStack($route);

        return $this->executeMiddleware($middlewareStack, $request, $controller);
    }

    /**
     * Get middleware stack for a route
     *
     * @param string $route
     * @return array
     */
    protected function getMiddlewareStack(string $route): array
    {
        $stack = $this->globalMiddleware;

        // Add route-specific middleware
        if (isset($this->routeMiddleware[$route])) {
            $stack = array_merge($stack, $this->routeMiddleware[$route]);
        }

        // Check for pattern-based middleware
        foreach ($this->routeMiddleware as $pattern => $middleware) {
            if ($pattern !== $route && $this->matchesPattern($route, $pattern)) {
                $stack = array_merge($stack, $middleware);
            }
        }

        return array_unique($stack);
    }

    /**
     * Execute middleware stack
     *
     * @param array $middlewareStack
     * @param WP_REST_Request $request
     * @param callable $controller
     * @return mixed
     */
    protected function executeMiddleware(array $middlewareStack, WP_REST_Request $request, callable $controller)
    {
        $index = 0;

        $next = function($request) use (&$middlewareStack, &$index, &$next, $controller) {
            if ($index >= count($middlewareStack)) {
                // All middleware executed, call the controller
                return $controller($request);
            }

            $middlewareClass = $middlewareStack[$index++];
            $middleware = $this->getMiddlewareInstance($middlewareClass);

            if (!$middleware) {
                // Skip invalid middleware
                return $next($request);
            }

            return $middleware->handle($request, $next);
        };

        return $next($request);
    }

    /**
     * Get middleware instance
     *
     * @param string $middlewareClass
     * @return Middleware|null
     */
    protected function getMiddlewareInstance(string $middlewareClass): ?Middleware
    {
        if (isset($this->instances[$middlewareClass])) {
            return $this->instances[$middlewareClass];
        }

        if (!class_exists($middlewareClass)) {
            error_log("SureFeedback: Middleware class not found: {$middlewareClass}");
            return null;
        }

        try {
            $instance = new $middlewareClass();
            
            if (!$instance instanceof Middleware) {
                error_log("SureFeedback: Invalid middleware class: {$middlewareClass}");
                return null;
            }

            $this->instances[$middlewareClass] = $instance;
            return $instance;
            
        } catch (\Exception $e) {
            error_log("SureFeedback: Failed to instantiate middleware {$middlewareClass}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if route matches pattern
     *
     * @param string $route
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $route, string $pattern): bool
    {
        // Convert pattern to regex
        $regex = str_replace(
            ['*', '/'],
            ['[^/]*', '\/'],
            preg_quote($pattern, '/')
        );

        return preg_match("/^{$regex}$/", $route) === 1;
    }

    /**
     * Remove middleware from global stack
     *
     * @param string $middleware
     * @return void
     */
    public function removeGlobalMiddleware(string $middleware): void
    {
        $key = array_search($middleware, $this->globalMiddleware);
        if ($key !== false) {
            unset($this->globalMiddleware[$key]);
            $this->globalMiddleware = array_values($this->globalMiddleware);
        }
    }

    /**
     * Remove route-specific middleware
     *
     * @param string $route
     * @param string|null $middleware
     * @return void
     */
    public function removeRouteMiddleware(string $route, ?string $middleware = null): void
    {
        if ($middleware === null) {
            // Remove all middleware for the route
            unset($this->routeMiddleware[$route]);
        } else {
            // Remove specific middleware
            if (isset($this->routeMiddleware[$route])) {
                $key = array_search($middleware, $this->routeMiddleware[$route]);
                if ($key !== false) {
                    unset($this->routeMiddleware[$route][$key]);
                    $this->routeMiddleware[$route] = array_values($this->routeMiddleware[$route]);
                }
            }
        }
    }

    /**
     * Get all registered middleware
     *
     * @return array
     */
    public function getAllMiddleware(): array
    {
        return [
            'global' => $this->globalMiddleware,
            'route' => $this->routeMiddleware,
        ];
    }

    /**
     * Clear all middleware instances cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->instances = [];
    }

    /**
     * Get middleware for specific route
     *
     * @param string $route
     * @return array
     */
    public function getRouteMiddleware(string $route): array
    {
        return $this->getMiddlewareStack($route);
    }

    /**
     * Check if middleware is enabled
     *
     * @param string $middlewareClass
     * @param string|null $route
     * @return bool
     */
    public function isMiddlewareEnabled(string $middlewareClass, ?string $route = null): bool
    {
        if (in_array($middlewareClass, $this->globalMiddleware)) {
            return true;
        }

        if ($route && isset($this->routeMiddleware[$route])) {
            return in_array($middlewareClass, $this->routeMiddleware[$route]);
        }

        return false;
    }
}