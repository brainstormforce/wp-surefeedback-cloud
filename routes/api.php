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

use SureFeedback\Http\Controllers\Api\ConnectionController;
use SureFeedback\Http\Controllers\Api\SettingsController;
use SureFeedback\Http\Controllers\Api\DashboardController;
use SureFeedback\Http\Controllers\VerificationController;
use SureFeedback\Http\Controllers\DisconnectController;

// Connection management endpoints
$router->group(['prefix' => 'connection', 'namespace' => 'Api'], function ($router) {
    $router->get('status', [ConnectionController::class, 'status']);
    $router->post('verify', [ConnectionController::class, 'verify']);
    $router->post('connect', [ConnectionController::class, 'connect']);
    $router->delete('disconnect', [ConnectionController::class, 'disconnect']);
    $router->post('reset', [ConnectionController::class, 'reset']);
    $router->get('health', [ConnectionController::class, 'health']);
});

$router->group(['prefix' => 'remote', 'namespace' => 'Api'], function ($router) {
    $router->getJWT('validate-token', [ConnectionController::class, 'validate_token']);
    $router->postJWT('disconnect', [ConnectionController::class, 'disconnect_website']);
});

// Webhook endpoint for SureFeedback API callbacks
$router->post('webhook', [ConnectionController::class, 'webhook']);

// Plugin activation endpoint (for SaaS auto-installation)
$router->post('plugin/activate', function () {
    // Check if user is authenticated and has admin capabilities
    if (!is_user_logged_in() || !current_user_can('activate_plugins')) {
        return new WP_Error(
            'rest_forbidden',
            __('You do not have permission to activate plugins.', 'surefeedback'),
            ['status' => rest_authorization_required_code()]
        );
    }

    try {
        $plugin_file = SUREFEEDBACK_PLUGIN_BASENAME;

        // Check if plugin is already active
        if (is_plugin_active($plugin_file)) {
            return rest_ensure_response([
                'success' => true,
                'already_active' => true,
                'message' => __('Plugin is already active.', 'surefeedback'),
                'plugin' => $plugin_file,
                'status' => 'active',
            ]);
        }

        // Activate the plugin
        $result = activate_plugin($plugin_file, '', false, true);

        if (is_wp_error($result)) {
            return new WP_Error(
                'activation_failed',
                $result->get_error_message(),
                ['status' => 500]
            );
        }

        // Run activation hook manually if needed
        do_action('activate_' . $plugin_file);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Plugin activated successfully.', 'surefeedback'),
            'plugin' => $plugin_file,
            'status' => 'active',
            'activated_at' => current_time('mysql'),
        ]);

    } catch (\Exception $e) {
        return new WP_Error(
            'activation_exception',
            $e->getMessage(),
            ['status' => 500]
        );
    }
});

// Plugin status endpoint
$router->get('plugin/status', function () {
    $plugin_file = SUREFEEDBACK_PLUGIN_BASENAME;
    
    return rest_ensure_response([
        'plugin' => $plugin_file,
        'is_active' => is_plugin_active($plugin_file),
        'version' => SUREFEEDBACK_VERSION,
        'name' => 'SureFeedback Client',
        'status' => is_plugin_active($plugin_file) ? 'active' : 'inactive',
    ]);
});

// Verification endpoints
$router->group(['prefix' => 'verification'], function ($router) {
    $router->post('verify', [VerificationController::class, 'verify_connection']);
});

// Disconnect endpoints
$router->group(['prefix' => 'disconnect'], function ($router) {
    $router->post('master-disconnect', [DisconnectController::class, 'master_disconnect']);
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