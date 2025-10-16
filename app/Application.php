<?php

/**
 * Application Container - Laravel-inspired application class
 *
 * @package SureFeedback
 */

namespace SureFeedback;

use SureFeedback\Infrastructure\Container\Service_Container;
use SureFeedback\Contracts\Container_Interface;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Application class - Laravel-inspired container
 */
class Application extends Service_Container
{
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
     * Application path
     *
     * @var string
     */
    protected $appPath;

    /**
     * Environment
     *
     * @var string
     */
    protected $environment;

    /**
     * Booted status
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Service providers
     *
     * @var array
     */
    protected $serviceProviders = [];

    /**
     * Loaded providers
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * Create a new application instance
     *
     * @param string|null $basePath Base path of the application.
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
    }

    /**
     * Get the version number of the application
     *
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Set the base path for the application
     *
     * @param string $basePath Base path.
     * @return $this
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind all paths in the container
     *
     * @return void
     */
    protected function bindPathsInContainer(): void
    {
        $this->instance('path.base', $this->basePath);
        $this->instance('path.app', $this->appPath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.bootstrap', $this->bootstrapPath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.storage', $this->storagePath());
    }

    /**
     * Get the path to the application "app" directory
     *
     * @param string $path Additional path.
     * @return string
     */
    public function appPath(string $path = ''): string
    {
        $appPath = $this->appPath ?: $this->basePath . DIRECTORY_SEPARATOR . 'app';

        return $appPath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the application configuration files
     *
     * @param string $path Additional path.
     * @return string
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the bootstrap directory
     *
     * @param string $path Additional path.
     * @return string
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the resources directory
     *
     * @param string $path Additional path.
     * @return string
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the storage directory
     *
     * @param string $path Additional path.
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the environment name
     *
     * @return string
     */
    public function environment(): string
    {
        return $this->environment ?: 'production';
    }

    /**
     * Set the environment for the application
     *
     * @param string $environment Environment name.
     * @return $this
     */
    public function setEnvironment(string $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * Determine if the application is in a given environment
     *
     * @param string|array $environments Environment names.
     * @return bool
     */
    public function isEnvironment($environments): bool
    {
        return in_array($this->environment(), (array) $environments, true);
    }

    /**
     * Register the basic bindings into the container
     *
     * @return void
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container_Interface::class, $this);
        $this->instance(Application::class, $this);
    }

    /**
     * Register all the base service providers
     *
     * @return void
     */
    protected function registerBaseServiceProviders(): void
    {
        // Base service providers will be registered here
    }

    /**
     * Register a service provider
     *
     * @param string|object $provider Service provider class or instance.
     * @param bool          $force    Force registration.
     * @return object
     */
    public function register($provider, bool $force = false): object
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists
     *
     * @param string|object $provider Service provider.
     * @return object|null
     */
    public function getProvider($provider): ?object
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist
     *
     * @param string|object $provider Service provider.
     * @return array
     */
    public function getProviders($provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_filter($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }

    /**
     * Resolve a service provider instance from the class name
     *
     * @param string $provider Provider class name.
     * @return object
     */
    public function resolveProvider(string $provider): object
    {
        return new $provider($this);
    }

    /**
     * Mark the given provider as registered
     *
     * @param object $provider Service provider instance.
     * @return void
     */
    protected function markAsRegistered(object $provider): void
    {
        $this->serviceProviders[] = $provider;
        $this->loadedProviders[get_class($provider)] = true;
    }

    /**
     * Boot the application's service providers
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;
    }

    /**
     * Boot the given service provider
     *
     * @param object $provider Service provider instance.
     * @return void
     */
    protected function bootProvider(object $provider): void
    {
        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
    }

    /**
     * Determine if the application has booted
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get or check the current application instance
     *
     * @param Application|null $app Application instance.
     * @return static
     */
    public static function getInstance(?Application $app = null): self
    {
        if (is_null(static::$instance)) {
            static::$instance = $app;
        }

        return static::$instance;
    }

    /**
     * Set the shared instance of the container
     *
     * @param Container_Interface|null $container Container instance.
     * @return static
     */
    public static function setInstance(?Container_Interface $container = null): self
    {
        return static::$instance = $container;
    }

    /**
     * Call the given callback and inject its dependencies
     *
     * @param callable|array $callback Callback to call.
     * @param array          $parameters Additional parameters.
     * @return mixed
     */
    public function call($callback, array $parameters = [])
    {
        if (is_array($callback) && count($callback) === 2) {
            [$class, $method] = $callback;
            
            if (is_string($class)) {
                $class = $this->resolve($class);
            }

            return $this->callMethod($class, $method, $parameters);
        }

        if (is_callable($callback)) {
            return call_user_func_array($callback, $parameters);
        }

        throw new \InvalidArgumentException('Invalid callback provided');
    }

    /**
     * Call a method on an object with dependency injection
     *
     * @param object $object     Object instance.
     * @param string $method     Method name.
     * @param array  $parameters Additional parameters.
     * @return mixed
     * @throws \ReflectionException If method doesn't exist.
     */
    protected function callMethod(object $object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);

        return $reflection->invokeArgs($object, $dependencies);
    }

    /**
     * Get the globally available instance of the application
     *
     * @return static
     */
    public static function app(): self
    {
        return static::getInstance();
    }

    /**
     * Flush the container of all bindings and resolved instances
     *
     * @return void
     */
    public function flush(): void
    {
        parent::flush();

        $this->serviceProviders = [];
        $this->loadedProviders = [];
        $this->booted = false;
    }

    /**
     * Terminate the application
     *
     * @return void
     */
    public function terminate(): void
    {
        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'terminate')) {
                $this->call([$provider, 'terminate']);
            }
        }
    }

    /**
     * Handle uncaught exceptions
     *
     * @param \Throwable $e Exception.
     * @return void
     */
    public function handleException(\Throwable $e): void
    {
        // Log the exception
        if ($this->bound('log')) {
            $this->resolve('log')->error($e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        // In development, show detailed error
        if ($this->isEnvironment('development')) {
            throw $e;
        }

        // In production, show user-friendly error
        wp_die(__('An error occurred. Please try again later.', 'surefeedback'));
    }
}