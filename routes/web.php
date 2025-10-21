<?php

/**
 * Web Routes
 *
 * Here is where you can register web routes for your plugin. These
 * routes are loaded by the RouteServiceProvider and all of them will
 * be assigned to the "web" middleware group. Make something great!
 *
 * @package SureFeedback
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your plugin. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use SureFeedback\App\Http\Controllers\AdminController;
use SureFeedback\App\Http\Controllers\SettingsController;

// Admin dashboard routes
$router->group(['prefix' => 'admin', 'middleware' => 'auth'], function ($router) {

    // Settings pages
    $router->get('settings', [SettingsController::class, 'index']);
    $router->get('settings/general', [SettingsController::class, 'general']);
    $router->get('settings/connection', [SettingsController::class, 'connection']);
    
    // Admin actions
    $router->post('settings/save', [SettingsController::class, 'save']);
    $router->post('connection/test', [AdminController::class, 'testConnection']);
    $router->post('connection/reset', [AdminController::class, 'resetConnection']);
});

// Setup wizard routes (accessible without full auth)
$router->group(['prefix' => 'setup'], function ($router) {
    $router->get('/', [AdminController::class, 'setupWizard']);
    $router->get('step/{step}', [AdminController::class, 'setupStep']);
    $router->post('complete', [AdminController::class, 'completeSetup']);
});

// Public routes (no authentication required)
$router->group(['prefix' => 'public'], function ($router) {
    
    // Widget endpoints
    $router->get('widget/config', function () {
        return wp_json_encode([
            'connected' => !empty(get_option('surefeedback_access_token')),
        ]);
    });
    
    // Health check
    $router->get('health', function () {
        return wp_json_encode([
            'status' => 'healthy',
            'plugin_version' => SUREFEEDBACK_VERSION,
            'wp_version' => get_bloginfo('version')
        ]);
    });
});

// Ajax handlers for WordPress admin
add_action('wp_ajax_surefeedback_save_settings', [SettingsController::class, 'ajaxSaveSettings']);
add_action('wp_ajax_surefeedback_test_connection', [AdminController::class, 'ajaxTestConnection']);
add_action('wp_ajax_surefeedback_reset_plugin', [AdminController::class, 'ajaxResetPlugin']);

// Frontend hooks
add_action('wp_enqueue_scripts', function () {
    $access_token = get_option('surefeedback_access_token');
    if (!empty($access_token)) {
        wp_enqueue_script(
            'surefeedback-widget',
            SUREFEEDBACK_PLUGIN_URL . 'assets/widget.js',
            [],
            SUREFEEDBACK_VERSION,
            true
        );
        
        wp_localize_script('surefeedback-widget', 'surefeedbackConfig', [
            'apiUrl' => rest_url('surefeedback/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'siteId' => get_option('surefeedback_site_id'),
            'accessToken' => $access_token
        ]);
    }
});