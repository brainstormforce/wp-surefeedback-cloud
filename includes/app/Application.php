<?php
/**
 * Application - Main plugin application class
 *
 * @package SureFeedback
 * @author Anurag Singh <anurags@bsf.io>
 */

namespace SureFeedback;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Application class - Main plugin application
 */
class Application {

	/**
	 * Application version
	 *
	 * @var string
	 */
	const VERSION = SUREFEEDBACK_VERSION;

	/**
	 * Application instance
	 *
	 * @var static
	 */
	protected static $instance;

	/**
	 * Base path of the application
	 *
	 * @var string
	 */
	protected $basePath;

	/**
	 * Services registry
	 *
	 * @var array
	 */
	protected $services = array();

	/**
	 * Booted status
	 *
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * Create a new application instance
	 *
	 * @param string|null $basePath Base path of the application.
	 */
	public function __construct( ?string $basePath = null ) {
		if ( $basePath ) {
			$this->basePath = rtrim( $basePath, '\/' );
		}

		static::$instance = $this;
	}

	/**
	 * Register a service
	 *
	 * @param string $name Service name.
	 * @param mixed  $service Service instance or callable.
	 * @return void
	 */
	public function register( $name, $service = null ): void {
		if ( is_object( $name ) && method_exists( $name, 'register' ) ) {
			// It's a service provider
			$name->register();
			if ( $this->booted && method_exists( $name, 'boot' ) ) {
				$name->boot();
			}
			return;
		}

		$this->services[ $name ] = $service;
	}

	/**
	 * Get a service
	 *
	 * @param string $name Service name.
	 * @return mixed
	 */
	public function make( string $name ) {
		if ( ! isset( $this->services[ $name ] ) ) {
			return null;
		}

		$service = $this->services[ $name ];

		if ( is_callable( $service ) ) {
			$this->services[ $name ] = $service( $this );
			return $this->services[ $name ];
		}

		return $service;
	}

	/**
	 * Boot the application
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		// Register and initialize core services
		$this->bootServices();

		$this->booted = true;
	}

	/**
	 * Boot core services
	 *
	 * @return void
	 */
	protected function bootServices(): void {
		// Setup CORS for development
		$this->setupCors();

		// Register REST API routes first (must be done early)
		$this->registerApiRoutes();

		// Initialize AdminService only in admin area
		if ( is_admin() ) {
			$this->register(
				'adminService',
				function () {
					return new \SureFeedback\Services\AdminService();
				}
			);
			// Instantiate the service to trigger its hooks
			$this->make( 'adminService' );
		}

		// Initialize FrontendService for frontend
		if ( ! is_admin() ) {
			$this->register(
				'frontendService',
				function () {
					return new \SureFeedback\Services\FrontendService();
				}
			);
			// Instantiate the service to trigger its hooks
			$this->make( 'frontendService' );
		}

		// Initialize SecurityService (always needed)
		$this->register(
			'securityService',
			function () {
				return new \SureFeedback\Services\SecurityService();
			}
		);
	}

	/**
	 * Setup CORS headers for development
	 *
	 * @return void
	 */
	protected function setupCors(): void {
		// Add CORS support for development
		add_action(
			'rest_api_init',
			function () {
				// Remove default CORS filters to prevent conflicts
				remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

				// Add custom CORS handling
				add_filter(
					'rest_pre_serve_request',
					function ( $value ) {
						$origin = get_http_origin();

						// Allow requests from development server
						if ( $origin ) {
							// Parse the origin to check if it's localhost or a dev server
							$parsed = wp_parse_url( $origin );
							$host   = $parsed['host'] ?? '';

							// Allow localhost and local dev domains
							$allowed_patterns = array(
								'localhost',
								'127.0.0.1',
								'.local',
								'.test',
								'.dev',
							);

							$is_dev = false;
							foreach ( $allowed_patterns as $pattern ) {
								if ( strpos( $host, $pattern ) !== false ) {
									$is_dev = true;
									break;
								}
							}

							if ( $is_dev ) {
								header( 'Access-Control-Allow-Origin: ' . $origin );
								header( 'Access-Control-Allow-Credentials: true' );
								header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH' );
								header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With' );
								header( 'Access-Control-Max-Age: 86400' );
							}
						}

						// Handle preflight requests
						$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
						if ( $request_method === 'OPTIONS' ) {
							status_header( 200 );
							exit;
						}

						return $value;
					}
				);
			},
			15
		);
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	protected function registerApiRoutes(): void {
		$router = new \SureFeedback\Http\Router();

		// Load the routes file
		$routesFile = $this->basePath . '/routes/api.php';
		if ( file_exists( $routesFile ) ) {
			require $routesFile;
		}

		// Register all routes with WordPress
		$router->register();
	}

	/**
	 * Get the application instance
	 *
	 * @return static
	 */
	public static function getInstance(): self {
		return static::$instance;
	}
}
