<?php

/**
 * App Service Provider - Main application services
 *
 * @package SureFeedback\App\Providers
 */

namespace SureFeedback\App\Providers;

use SureFeedback\Infrastructure\Config\Config_Manager;
use SureFeedback\Infrastructure\Logging\WordPress_Logger;
use SureFeedback\Contracts\Config_Interface;
use SureFeedback\Contracts\Logger_Interface;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * App Service Provider class
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered
     *
     * @var array
     */
    public $bindings = [
        // Add any basic bindings here
    ];

    /**
     * All of the container singletons that should be registered
     *
     * @var array
     */
    public $singletons = [
        Config_Interface::class => Config_Manager::class,
        Logger_Interface::class => WordPress_Logger::class,
    ];

    /**
     * Register services in the container
     *
     * @return void
     */
    public function register(): void
    {
        // Register core services
        $this->registerCoreServices();
        $this->registerApiServices();
        $this->registerUtilityServices();
    }

    /**
     * Register core application services
     *
     * @return void
     */
    private function registerCoreServices(): void
    {
        // Security Service (singleton)
        $this->app->singleton(
            'SureFeedback\App\Services\SecurityService',
            function () {
                return new \SureFeedback\App\Services\SecurityService();
            }
        );

        // Admin Service (singleton)
        $this->app->singleton(
            'SureFeedback\App\Services\AdminService',
            function () {
                return new \SureFeedback\App\Services\AdminService();
            }
        );

        // Frontend Service (singleton)
        $this->app->singleton(
            'SureFeedback\App\Services\FrontendService',
            function () {
                return new \SureFeedback\App\Services\FrontendService();
            }
        );

        // SaaS Client Service (singleton)
        $this->app->singleton(
            'SureFeedback\App\Services\SaasClientService',
            function () {
                return new \SureFeedback\App\Services\SaasClientService();
            }
        );
    }

    /**
     * Register API-related services
     *
     * @return void
     */
    private function registerApiServices(): void
    {
        // REST Controller
        $this->app->bind(
            'SureFeedback\App\Api\RestController',
            function () {
                return new \SureFeedback\App\Api\RestController();
            }
        );

        // API Controllers
        $this->app->bind(
            'SureFeedback\App\Http\Controllers\Api\ConnectionController',
            function () {
                return new \SureFeedback\App\Http\Controllers\Api\ConnectionController();
            }
        );

        $this->app->bind(
            'SureFeedback\App\Http\Controllers\Api\SettingsController',
            function () {
                return new \SureFeedback\App\Http\Controllers\Api\SettingsController();
            }
        );

        $this->app->bind(
            'SureFeedback\App\Http\Controllers\Api\DashboardController',
            function () {
                return new \SureFeedback\App\Http\Controllers\Api\DashboardController();
            }
        );

        $this->app->bind(
            'SureFeedback\App\Http\Controllers\Api\AdminApiController',
            function () {
                return new \SureFeedback\App\Http\Controllers\Api\AdminApiController();
            }
        );
    }

    /**
     * Register utility services
     *
     * @return void
     */
    private function registerUtilityServices(): void
    {
        // Register service aliases for easier access
        $this->app->alias('SureFeedback\App\Services\SecurityService', 'security');
        $this->app->alias('SureFeedback\App\Services\AdminService', 'admin');
        $this->app->alias('SureFeedback\App\Services\FrontendService', 'frontend');
        $this->app->alias('SureFeedback\App\Services\SaasClientService', 'saas_client');
    }    
    
    /**
     * Bootstrap any application services
     *
     * @return void
     */
    public function boot(): void
    {
        $this->bootConfig();
        $this->setupErrorHandling();
    }

    /**
     * Register configuration service
     *
     * @return void
     */
    protected function registerConfig(): void
    {
        $this->singleton(Config_Interface::class, function ($app) {
            $defaults = $this->getDefaultConfig();
            return new Config_Manager($defaults);
        });

        $this->singleton('config', Config_Interface::class);
    }

    /**
     * Register logger service
     *
     * @return void
     */
    protected function registerLogger(): void
    {
        $this->singleton(Logger_Interface::class, function ($app) {
            $config = $app->resolve(Config_Interface::class);
            return new WordPress_Logger($config);
        });

        $this->singleton('log', Logger_Interface::class);
    }

    /**
     * Register helper functions and utilities
     *
     * @return void
     */
    protected function registerHelpers(): void
    {
        // Register any helper functions or utilities
        $this->bind('path', function ($app) {
            return new class {
                public function plugin(string $path = ''): string {
                    return SUREFEEDBACK_PLUGIN_DIR . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
                }

                public function url(string $path = ''): string {
                    return SUREFEEDBACK_PLUGIN_URL . ($path ? '/' . ltrim($path, '/') : '');
                }

                public function asset(string $path = ''): string {
                    return SUREFEEDBACK_PLUGIN_URL . 'assets/' . ltrim($path, '/');
                }

                public function config(string $path = ''): string {
                    return SUREFEEDBACK_PLUGIN_DIR . 'config/' . ltrim($path, '/');
                }
            };
        });
    }

    /**
     * Boot configuration service
     *
     * @return void
     */
    protected function bootConfig(): void
    {
        $config = $this->app->resolve('config');
        
        // Load environment-specific configuration
        $environment = $this->detectEnvironment();
        $this->app->setEnvironment($environment);
        
        if ($environment !== 'production') {
            $env_config = $config->get_environment_config($environment);
            $config->load($env_config);
        }
    }

    /**
     * Setup error handling
     *
     * @return void
     */
    protected function setupErrorHandling(): void
    {
        // Set up global exception handler
        set_exception_handler([$this->app, 'handleException']);

        // Register WordPress error handling
        $this->addAction('wp_die_handler', function ($handler) {
            return function ($message, $title = '', $args = []) use ($handler) {
                $logger = $this->app->resolve('log');
                $logger->error('WordPress died', [
                    'message' => $message,
                    'title' => $title,
                    'args' => $args,
                ]);

                return call_user_func($handler, $message, $title, $args);
            };
        });
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'app' => [
                'name' => 'SureFeedback',
                'version' => SUREFEEDBACK_VERSION,
                'url' => home_url(),
            ],
            'api' => [
                'base_url' => 'https://api.surefeedback.com',
                'timeout' => 30,
                'retries' => 3,
            ],
            'features' => [
                'white_label' => false,
                'guest_comments' => false,
                'admin_comments' => true,
            ],
            'ui' => [
                'position' => 'bottom-right',
                'theme' => 'default',
            ],
            'logging' => [
                'enabled' => defined('WP_DEBUG') && WP_DEBUG,
                'level' => 'error',
            ],
        ];
    }

    /**
     * Detect current environment
     *
     * @return string
     */
    protected function detectEnvironment(): string
    {
        // Check for explicit environment constant
        if (defined('SUREFEEDBACK_ENV')) {
            return SUREFEEDBACK_ENV;
        }

        // Check WordPress debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }

        // Check for local development
        if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
            return 'development';
        }

        // Check for staging environment
        if (strpos($_SERVER['HTTP_HOST'] ?? '', 'staging') !== false) {
            return 'staging';
        }

        return 'production';
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            Config_Interface::class,
            Logger_Interface::class,
            'config',
            'log',
            'path',
        ];
    }
}