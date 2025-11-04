<?php
/**
 * Route Service Provider
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
 * Route Service Provider
 *
 * @package SureFeedback\Providers
 * @author Anurag Singh <anurags@bsf.io>
 */
class RouteServiceProvider
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
        // Register route-related services
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Boot routes when needed
    }
}
