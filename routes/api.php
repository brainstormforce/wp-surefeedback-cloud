<?php

/**
 * API Routes
 *
 * Here is where you can register API routes for your plugin. These
 * routes are loaded by the RouteServiceProvider and all of them will
 * be assigned to the "api" middleware group. Make something great!
 *
 * @package SureFeedback
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your plugin. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use SureFeedback\App\Http\Controllers\Api\ConnectionController;
use SureFeedback\App\Http\Controllers\Api\SettingsController;
use SureFeedback\App\Http\Controllers\Api\DashboardController;

// Connection management endpoints
$router->group(['prefix' => 'connection', 'namespace' => 'Api'], function ($router) {
    $router->get('status', [ConnectionController::class, 'status']);
    $router->post('verify', [ConnectionController::class, 'verify']);
    $router->post('connect', [ConnectionController::class, 'connect']);
    $router->delete('disconnect', [ConnectionController::class, 'disconnect']);
    $router->get('health', [ConnectionController::class, 'health']);
});

// Settings management endpoints
$router->group(['prefix' => 'settings', 'namespace' => 'Api'], function ($router) {
    $router->get('/', [SettingsController::class, 'index']);
    $router->post('/', [SettingsController::class, 'update']);
    $router->get('general', [SettingsController::class, 'general']);
    $router->post('general', [SettingsController::class, 'updateGeneral']);
    $router->get('white-label', [SettingsController::class, 'whiteLabel']);
    $router->post('white-label', [SettingsController::class, 'updateWhiteLabel']);
});

// Dashboard data endpoints
$router->group(['prefix' => 'dashboard', 'namespace' => 'Api'], function ($router) {
    $router->get('stats', [DashboardController::class, 'stats']);
    $router->get('quick-access', [DashboardController::class, 'quickAccess']);
    $router->get('recent-activity', [DashboardController::class, 'recentActivity']);
});

// Legacy compatibility routes
$router->get('pages', function () {
    // Legacy route for backward compatibility
    return rest_ensure_response([
        'pages' => get_pages([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1
        ])
    ]);
});

// Health check endpoint
$router->get('health', function () {
    return rest_ensure_response([
        'status' => 'ok',
        'version' => SUREFEEDBACK_VERSION,
        'wordpress' => get_bloginfo('version'),
        'php' => PHP_VERSION,
        'timestamp' => current_time('timestamp')
    ]);
});