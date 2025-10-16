<?php

namespace SureFeedback\Http;

/**
 * Router - REST API route registration
 *
 * @package SureFeedback\Http
 */
class Router
{
    /**
     * API namespace
     *
     * @var string
     */
    protected $namespace = 'surefeedback/v1';

    /**
     * Route prefix
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Registered routes
     *
     * @var array
     */
    public $routes = [];

    /**
     * Get a route with GET method
     *
     * @param string $route Route path
     * @param array|callable $callback Callback function or controller array
     * @return void
     */
    public function get(string $route, $callback): void
    {
        $this->addRoute('GET', $route, $callback);
    }

    /**
     * Post a route with POST method
     *
     * @param string $route Route path
     * @param array|callable $callback Callback function or controller array
     * @return void
     */
    public function post(string $route, $callback): void
    {
        $this->addRoute('POST', $route, $callback);
    }

    /**
     * Put a route with PUT method
     *
     * @param string $route Route path
     * @param array|callable $callback Callback function or controller array
     * @return void
     */
    public function put(string $route, $callback): void
    {
        $this->addRoute('PUT', $route, $callback);
    }

    /**
     * Delete a route with DELETE method
     *
     * @param string $route Route path
     * @param array|callable $callback Callback function or controller array
     * @return void
     */
    public function delete(string $route, $callback): void
    {
        $this->addRoute('DELETE', $route, $callback);
    }

    /**
     * Add a route group with prefix
     *
     * @param array $attributes Group attributes (prefix, namespace, etc.)
     * @param callable $callback Callback function to define routes
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->prefix;

        if (isset($attributes['prefix'])) {
            $this->prefix = trim($previousPrefix . '/' . $attributes['prefix'], '/');
        }

        $callback($this);

        $this->prefix = $previousPrefix;
    }

    /**
     * Add a route to the collection
     *
     * @param string $method HTTP method
     * @param string $route Route path
     * @param array|callable $callback Callback function or controller array
     * @return void
     */
    protected function addRoute(string $method, string $route, $callback): void
    {
        $route = trim($this->prefix . '/' . $route, '/');

        $this->routes[] = [
            'method' => $method,
            'route' => $route ?: '/',
            'callback' => $callback
        ];
    }

    /**
     * Register all routes with WordPress REST API
     *
     * @return void
     */
    public function register(): void
    {
        // If rest_api_init has already happened, register immediately
        if (did_action('rest_api_init')) {
            $this->registerRoutes();
        } else {
            // Otherwise, hook into rest_api_init
            add_action('rest_api_init', [$this, 'registerRoutes']);
        }
    }

    /**
     * Actually register the routes
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        foreach ($this->routes as $route) {
            register_rest_route(
                $this->namespace,
                $route['route'],
                [
                    'methods' => $route['method'],
                    'callback' => $this->prepareCallback($route['callback']),
                    'permission_callback' => [$this, 'checkPermissions'],
                ]
            );
        }
    }

    /**
     * Check permissions for REST API requests
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermissions(\WP_REST_Request $request): bool
    {
        // Allow all requests - handle authentication in controllers
        // This prevents WordPress from doing cookie-based authentication checks
        // Individual controllers handle their own authentication and authorization
        return true;
    }

    /**
     * Prepare callback for WordPress REST API
     *
     * @param array|callable $callback Controller method or callback
     * @return callable
     */
    protected function prepareCallback($callback): callable
    {
        if (is_array($callback) && count($callback) === 2) {
            // Controller@method format
            list($controllerClass, $method) = $callback;

            return function($request) use ($controllerClass, $method) {
                $controller = new $controllerClass();
                return $controller->$method($request);
            };
        }

        return $callback;
    }

    /**
     * Get the API namespace
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Set the API namespace
     *
     * @param string $namespace API namespace
     * @return void
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }
}
