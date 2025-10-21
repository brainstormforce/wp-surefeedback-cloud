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
use SureFeedback\Http\Controllers\Api\VerificationController;
use SureFeedback\Http\Controllers\Api\PageSettingsController;

// Connection management endpoints
$router->group(['prefix' => 'connection', 'namespace' => 'Api'], function ($router) {
    $router->get('status', [ConnectionController::class, 'status']);
    $router->post('connect', [ConnectionController::class, 'connect']);
    $router->post('reset', [ConnectionController::class, 'reset']);
    $router->get('health', [ConnectionController::class, 'health']);
});

$router->group(['prefix' => 'remote', 'namespace' => 'Api'], function ($router) {
    $router->getJWT('validate-token', [ConnectionController::class, 'validate_token']);
});

// Webhook endpoint for SureFeedback API callbacks
$router->post('webhook', [ConnectionController::class, 'webhook']);

// Secure disconnect webhook endpoint (JWT protected)
$router->postJWT('webhook/disconnect', [ConnectionController::class, 'disconnect_webhook']);

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

// Settings management endpoints
$router->group(['prefix' => 'settings', 'namespace' => 'Api'], function ($router) {
    $router->get('/', [SettingsController::class, 'index']);
    $router->post('/', [SettingsController::class, 'update']);
    $router->get('general', [SettingsController::class, 'general']);
    $router->post('general', [SettingsController::class, 'updateGeneral']);
});

// Page settings endpoints
$router->group(['prefix' => 'page-settings'], function ($router) {
    $router->get('/', [PageSettingsController::class, 'index']);
    $router->post('/', [PageSettingsController::class, 'update']);
    $router->post('enable', [PageSettingsController::class, 'enablePage']);
    $router->post('disable', [PageSettingsController::class, 'disablePage']);
    $router->post('enable-all', [PageSettingsController::class, 'enableAll']);
    $router->post('disable-all', [PageSettingsController::class, 'disableAll']);
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