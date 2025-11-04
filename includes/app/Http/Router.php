<?php

namespace SureFeedback\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Router - REST API route registration
 *
 * @package SureFeedback\Http
 * @author Anurag Singh <anurags@bsf.io>
 */
class Router {

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
	public $routes = array();

	/**
	 * Get a route with GET method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function get( string $route, $callback ): void {
		$this->addRoute( 'GET', $route, $callback );
	}

	/**
	 * Post a route with POST method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function post( string $route, $callback ): void {
		$this->addRoute( 'POST', $route, $callback );
	}

	/**
	 * Put a route with PUT method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function put( string $route, $callback ): void {
		$this->addRoute( 'PUT', $route, $callback );
	}

	/**
	 * Delete a route with DELETE method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function delete( string $route, $callback ): void {
		$this->addRoute( 'DELETE', $route, $callback );
	}

	/**
	 * Add a route group with prefix
	 *
	 * @param array    $attributes Group attributes (prefix, namespace, etc.)
	 * @param callable $callback Callback function to define routes
	 * @return void
	 */
	public function group( array $attributes, callable $callback ): void {
		$previousPrefix = $this->prefix;

		if ( isset( $attributes['prefix'] ) ) {
			$this->prefix = trim( $previousPrefix . '/' . $attributes['prefix'], '/' );
		}

		$callback( $this );

		$this->prefix = $previousPrefix;
	}

	/**
	 * Add a route to the collection
	 *
	 * @param string         $method HTTP method
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @param array          $options Route options (middleware, etc.)
	 * @return void
	 */
	protected function addRoute( string $method, string $route, $callback, array $options = array() ): void {
		$route = trim( $this->prefix . '/' . $route, '/' );

		$this->routes[] = array(
			'method'   => $method,
			'route'    => $route ?: '/',
			'callback' => $callback,
			'options'  => $options,
		);
	}

	/**
	 * Add a JWT-protected route with POST method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function postJWT( string $route, $callback ): void {
		$this->addRoute( 'POST', $route, $callback, array( 'jwt' => true ) );
	}

	/**
	 * Add a JWT-protected route with GET method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function getJWT( string $route, $callback ): void {
		$this->addRoute( 'GET', $route, $callback, array( 'jwt' => true ) );
	}

	/**
	 * Add a JWT-protected route with PUT method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function putJWT( string $route, $callback ): void {
		$this->addRoute( 'PUT', $route, $callback, array( 'jwt' => true ) );
	}

	/**
	 * Add a JWT-protected route with DELETE method
	 *
	 * @param string         $route Route path
	 * @param array|callable $callback Callback function or controller array
	 * @return void
	 */
	public function deleteJWT( string $route, $callback ): void {
		$this->addRoute( 'DELETE', $route, $callback, array( 'jwt' => true ) );
	}

	/**
	 * Register all routes with WordPress REST API
	 *
	 * @return void
	 */
	public function register(): void {
		// If rest_api_init has already happened, register immediately
		if ( did_action( 'rest_api_init' ) ) {
			$this->registerRoutes();
		} else {
			// Otherwise, hook into rest_api_init
			add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		}
	}

	/**
	 * Actually register the routes
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		foreach ( $this->routes as $route ) {
			$args = array(
				'methods'             => $route['method'],
				'callback'            => $this->prepareCallback( $route['callback'], $route['options'] ?? array() ),
				'permission_callback' => array( $this, 'checkPermissions' ),
			);

			// Add JWT permission callback if needed
			if ( isset( $route['options']['jwt'] ) && $route['options']['jwt'] ) {
				$args['permission_callback'] = array( $this, 'checkJWTPermissions' );
			}

			register_rest_route(
				$this->namespace,
				$route['route'],
				$args
			);
		}
	}

	/**
	 * Check permissions for REST API requests
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function checkPermissions( \WP_REST_Request $request ): bool {
		// Allow all requests - handle authentication in controllers
		// This prevents WordPress from doing cookie-based authentication checks
		// Individual controllers handle their own authentication and authorization
		return true;
	}

	/**
	 * Check JWT permissions for protected routes
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function checkJWTPermissions( \WP_REST_Request $request ) {
		try {
			$jwtService = new \SureFeedback\Services\JWTService();
			$token_data = $jwtService->validate_token_from_request( $request );

			if ( ! $token_data ) {
				return new \WP_Error(
					'jwt_auth_invalid_token',
					'Invalid or missing JWT token.',
					array( 'status' => 401 )
				);
			}

			// Check if token has manage_options permission
			if ( ! $jwtService->check_permission( $token_data, 'manage_options' ) ) {
				return new \WP_Error(
					'jwt_auth_insufficient_permissions',
					'Insufficient permissions.',
					array( 'status' => 403 )
				);
			}

			// Store token data in request for use in controller
			$request->set_param( '_jwt_token_data', $token_data );

			return true;

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'jwt_auth_error',
				'Authentication error: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Prepare callback for WordPress REST API
	 *
	 * @param array|callable $callback Controller method or callback
	 * @param array          $options Route options
	 * @return callable
	 */
	protected function prepareCallback( $callback, array $options = array() ): callable {
		if ( is_array( $callback ) && count( $callback ) === 2 ) {
			// Controller@method format
			list($controllerClass, $method) = $callback;

			return function ( $request ) use ( $controllerClass, $method, $options ) {
				$controller = new $controllerClass();
				return $controller->$method( $request );
			};
		}

		return $callback;
	}

	/**
	 * Get the API namespace
	 *
	 * @return string
	 */
	public function getNamespace(): string {
		return $this->namespace;
	}

	/**
	 * Set the API namespace
	 *
	 * @param string $namespace API namespace
	 * @return void
	 */
	public function setNamespace( string $namespace ): void {
		$this->namespace = $namespace;
	}
}
