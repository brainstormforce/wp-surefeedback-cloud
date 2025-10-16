<?php

/**
 * Bootstrap the application
 *
 * @package SureFeedback
 */

use SureFeedback\Application;
use SureFeedback\App\Providers\AppServiceProvider;
use SureFeedback\App\Providers\RouteServiceProvider;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

// Set up autoloading
/*
|--------------------------------------------------------------------------
| Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for this application. We just need to utilize it! We'll require it
| into the script here so we don't need to manually load any classes.
|
*/

require_once __DIR__ . '/../vendor/autoload.php';
$autoloader = new SureFeedback_Autoloader();

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Next we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/

$app->register(AppServiceProvider::class);
$app->register(RouteServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;