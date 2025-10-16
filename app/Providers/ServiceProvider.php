<?php

/**
 * Service Provider Base Class
 *
 * @package SureFeedback\App\Providers
 */

namespace SureFeedback\App\Providers;

use SureFeedback\Application;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Service Provider class
 */
abstract class ServiceProvider
{
    /**
     * Application instance
     *
     * @var Application
     */
    protected $app;

    /**
     * All of the registered service providers
     *
     * @var array
     */
    public $bindings = [];

    /**
     * All of the singletons that should be registered
     *
     * @var array
     */
    public $singletons = [];

    /**
     * Create a new service provider instance
     *
     * @param Application $app Application instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Determine if the provider is deferred
     *
     * @return bool
     */
    public function isDeferred(): bool
    {
        return false;
    }

    /**
     * Register a binding with the container
     *
     * @param string          $abstract Service identifier.
     * @param \Closure|string $concrete Service implementation.
     * @param bool            $shared   Whether to register as singleton.
     * @return void
     */
    protected function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->app->bind($abstract, $concrete, $shared);
    }

    /**
     * Register a shared binding in the container
     *
     * @param string          $abstract Service identifier.
     * @param \Closure|string $concrete Service implementation.
     * @return void
     */
    protected function singleton(string $abstract, $concrete = null): void
    {
        $this->app->singleton($abstract, $concrete);
    }

    /**
     * Register a service provider
     *
     * @param string $provider Service provider class name.
     * @return object
     */
    protected function register_provider(string $provider): object
    {
        return $this->app->register($provider);
    }

    /**
     * Call the given callback and inject its dependencies
     *
     * @param callable $callback  Callback to call.
     * @param array    $parameters Additional parameters.
     * @return mixed
     */
    protected function call($callback, array $parameters = [])
    {
        return $this->app->call($callback, $parameters);
    }

    /**
     * Merge the given configuration with the existing configuration
     *
     * @param string $path   Configuration path.
     * @param string $key    Configuration key.
     * @return void
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (! $this->app->bound('config')) {
            return;
        }

        $config = $this->app->resolve('config');
        
        if (file_exists($path)) {
            $file_config = include $path;
            if (is_array($file_config)) {
                $existing = $config->get($key, []);
                $config->set($key, array_merge($existing, $file_config));
            }
        }
    }

    /**
     * Load routes from the given file
     *
     * @param string $path Route file path.
     * @return void
     */
    protected function loadRoutesFrom(string $path): void
    {
        if (file_exists($path)) {
            include $path;
        }
    }

    /**
     * Register views
     *
     * @param string      $path      Views path.
     * @param string      $namespace View namespace.
     * @return void
     */
    protected function loadViewsFrom(string $path, string $namespace): void
    {
        // WordPress doesn't have a view system like Laravel
        // This could be extended with a custom view system if needed
    }

    /**
     * Register migrations
     *
     * @param string|array $paths Migration paths.
     * @return void
     */
    protected function loadMigrationsFrom($paths): void
    {
        // WordPress doesn't have migrations like Laravel
        // This could be extended with a custom migration system if needed
    }

    /**
     * Register the package's custom commands
     *
     * @param array $commands Command classes.
     * @return void
     */
    protected function commands(array $commands): void
    {
        if ($this->app->bound('wp-cli')) {
            foreach ($commands as $command) {
                $this->app->resolve('wp-cli')->add_command($command);
            }
        }
    }

    /**
     * Publish assets
     *
     * @param array  $paths   Asset paths.
     * @param string $group   Asset group.
     * @return void
     */
    protected function publishes(array $paths, string $group = null): void
    {
        // WordPress doesn't have asset publishing like Laravel
        // This could be used for copying assets to wp-content directories
    }

    /**
     * Add WordPress action hook
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    protected function addAction(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
        add_action($hook, $callback, $priority, $args);
    }

    /**
     * Add WordPress filter hook
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    protected function addFilter(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
        add_filter($hook, $callback, $priority, $args);
    }

    /**
     * Register a WordPress shortcode
     *
     * @param string   $tag      Shortcode tag.
     * @param callable $callback Callback function.
     * @return void
     */
    protected function addShortcode(string $tag, $callback): void
    {
        add_shortcode($tag, $callback);
    }

    /**
     * Enqueue WordPress script
     *
     * @param string      $handle    Script handle.
     * @param string      $src       Script source.
     * @param array       $deps      Dependencies.
     * @param string|bool $ver       Version.
     * @param bool        $in_footer Whether to enqueue in footer.
     * @return void
     */
    protected function enqueueScript(string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false): void
    {
        wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
    }

    /**
     * Enqueue WordPress style
     *
     * @param string      $handle Script handle.
     * @param string      $src    Script source.
     * @param array       $deps   Dependencies.
     * @param string|bool $ver    Version.
     * @param string      $media  Media type.
     * @return void
     */
    protected function enqueueStyle(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void
    {
        wp_enqueue_style($handle, $src, $deps, $ver, $media);
    }

    /**
     * Register WordPress custom post type
     *
     * @param string $post_type Post type name.
     * @param array  $args      Post type arguments.
     * @return void
     */
    protected function registerPostType(string $post_type, array $args = []): void
    {
        register_post_type($post_type, $args);
    }

    /**
     * Register WordPress taxonomy
     *
     * @param string       $taxonomy    Taxonomy name.
     * @param array|string $object_type Object type.
     * @param array        $args        Taxonomy arguments.
     * @return void
     */
    protected function registerTaxonomy(string $taxonomy, $object_type, array $args = []): void
    {
        register_taxonomy($taxonomy, $object_type, $args);
    }
}