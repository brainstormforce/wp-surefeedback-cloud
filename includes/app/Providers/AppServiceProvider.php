<?php
/**
 * App Service Provider - Main application services
 *
 * @package SureFeedback\Providers
 * @author Anurag Singh <anurags@bsf.io>
 */

namespace SureFeedback\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * App Service Provider class
 */
class AppServiceProvider
{
    /**
     * Application instance
     *
     * @var \SureFeedback\Application
     */
    protected $app;

    /**
     * Create a new service provider instance
     *
     * @param \SureFeedback\Application $app Application instance.
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Services can be registered here if needed
    }

    /**
     * Bootstrap any application services
     *
     * @return void
     */
    public function boot(): void
    {
        // Boot logic will be added when needed
    }
}