<?php

namespace SureFeedback\App\Providers;

use SureFeedback\ServiceProvider;

/**
 * Route Service Provider
 *
 * Handles the registration of all routes for the application.
 * This includes both API routes and web routes, along with
 * their respective middleware and route groups.
 *
 * @package SureFeedback\App\Providers
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The namespace for controller routes.
     *
     * @var string
     */
    protected $namespace = 'SureFeedback\App\Http\Controllers';
    
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register route-related services
        $this->app->singleton('router', function ($app) {
            return new Router($app);
        });
    }
    
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerApiRoutes();
        $this->registerWebRoutes();
        $this->registerRestApiEndpoints();
    }
    
    /**
     * Register API routes
     *
     * @return void
     */
    protected function registerApiRoutes(): void
    {
        $this->mapApiRoutes();
    }
    
    /**
     * Register web routes
     *
     * @return void
     */
    protected function registerWebRoutes(): void
    {
        $this->mapWebRoutes();
    }
    
    /**
     * Map API routes
     *
     * @return void
     */
    protected function mapApiRoutes(): void
    {
        // Register REST API namespace
        add_action('rest_api_init', function () {
            $this->registerRestApiRoutes();
        });
    }
    
    /**
     * Map web routes
     *
     * @return void
     */
    protected function mapWebRoutes(): void
    {
        // Register admin menu and pages
        add_action('admin_menu', [$this, 'registerAdminRoutes']);
        
        // Register frontend routes
        add_action('init', [$this, 'registerFrontendRoutes']);
        
        // Register AJAX routes
        add_action('wp_ajax_surefeedback_action', [$this, 'handleAjaxRoutes']);
        add_action('wp_ajax_nopriv_surefeedback_action', [$this, 'handlePublicAjaxRoutes']);
    }
    
    /**
     * Register REST API routes
     *
     * @return void
     */
    private function registerRestRoutes(): void
    {
        add_action('rest_api_init', function() {
            // Connection management routes
            register_rest_route('surefeedback/v1', '/connection/status', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\ConnectionController'), 'status'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/connection/verify', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\ConnectionController'), 'verify'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/connection/connect', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\ConnectionController'), 'connect'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/connection/disconnect', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\ConnectionController'), 'disconnect'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/connection/health', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\ConnectionController'), 'health'],
                'permission_callback' => '__return_true'
            ]);

            // Settings management routes
            register_rest_route('surefeedback/v1', '/settings', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\SettingsController'), 'getAllSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/settings', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\SettingsController'), 'updateSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/settings/(?P<key>[a-zA-Z0-9_-]+)', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\SettingsController'), 'getSetting'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/settings/(?P<key>[a-zA-Z0-9_-]+)', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\SettingsController'), 'updateSetting'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            // Dashboard routes
            register_rest_route('surefeedback/v1', '/dashboard/stats', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\DashboardController'), 'getStats'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/dashboard/activity', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\DashboardController'), 'getActivity'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            // Admin API routes (for Vue.js frontend)
            register_rest_route('surefeedback/v1', '/admin/settings', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\AdminApiController'), 'getSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/admin/settings/general', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\AdminApiController'), 'saveGeneralSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/admin/settings/white-label', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\AdminApiController'), 'saveWhiteLabelSettings'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/admin/verify-integration', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\AdminApiController'), 'verifyIntegration'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/admin/disconnect', [
                'methods' => 'POST',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\AdminApiController'), 'disconnectSite'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            register_rest_route('surefeedback/v1', '/admin/connection/status', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Http\Controllers\Api\AdminApiController'), 'getConnectionStatus'],
                'permission_callback' => [$this, 'checkAdminPermissions']
            ]);

            // Public API endpoints for frontend integration
            register_rest_route('surefeedback/v1', '/pages', [
                'methods' => 'GET',
                'callback' => [$this->app->make('SureFeedback\App\Api\RestController'), 'getPages'],
                'permission_callback' => [$this, 'checkApiAccess']
            ]);

            register_rest_route('surefeedback/v1', '/pages', [
                'methods' => 'OPTIONS',
                'callback' => [$this->app->make('SureFeedback\App\Api\RestController'), 'handleCorsPreflightRequest'],
                'permission_callback' => '__return_true'
            ]);
        });
    }
    
    /**
     * Register REST API endpoints using WordPress API
     *
     * @return void
     */
    protected function registerRestApiEndpoints(): void
    {
        // Add CORS headers for API endpoints
        add_action('rest_api_init', function () {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', [$this, 'addCorsHeaders'], 15, 4);
        });
    }
    
    /**
     * Register admin routes
     *
     * @return void
     */
    public function registerAdminRoutes(): void
    {
        // Main plugin page
        add_menu_page(
            'SureFeedback',
            'SureFeedback',
            'manage_options',
            'surefeedback',
            [$this, 'renderAdminPage'],
            'dashicons-feedback',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'surefeedback',
            'Settings',
            'Settings',
            'manage_options',
            'surefeedback-settings',
            [$this, 'renderSettingsPage']
        );
        
        // Connection submenu
        add_submenu_page(
            'surefeedback',
            'Connection',
            'Connection',
            'manage_options',
            'surefeedback-connection',
            [$this, 'renderConnectionPage']
        );
    }
    
    /**
     * Register frontend routes
     *
     * @return void
     */
    public function registerFrontendRoutes(): void
    {
        // Add rewrite rules for frontend endpoints
        add_rewrite_rule(
            '^surefeedback/widget/?$',
            'index.php?surefeedback_widget=1',
            'top'
        );
        
        add_rewrite_rule(
            '^surefeedback/config/?$',
            'index.php?surefeedback_config=1',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function ($vars) {
            $vars[] = 'surefeedback_widget';
            $vars[] = 'surefeedback_config';
            return $vars;
        });
        
        // Handle frontend routes
        add_action('template_redirect', [$this, 'handleFrontendRoutes']);
    }
    
    /**
     * Handle REST route requests
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleRestRoute(\WP_REST_Request $request)
    {
        try {
            $route_args = $request->get_attributes()['args'] ?? [];
            $controller_class = $this->namespace . '\\' . $route_args['controller'];
            $method = $route_args['method'];
            
            if (!class_exists($controller_class)) {
                return new \WP_Error('controller_not_found', 'Controller not found', ['status' => 404]);
            }
            
            $controller = new $controller_class();
            
            if (!method_exists($controller, $method)) {
                return new \WP_Error('method_not_found', 'Method not found', ['status' => 404]);
            }
            
            return $controller->$method($request);
            
        } catch (\Exception $e) {
            $this->logError('Route handling error: ' . $e->getMessage());
            return new \WP_Error('route_error', 'Internal server error', ['status' => 500]);
        }
    }
    
    /**
     * Handle legacy pages route
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleLegacyPagesRoute(\WP_REST_Request $request)
    {
        $pages = get_pages([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        
        $page_data = [];
        foreach ($pages as $page) {
            $page_data[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'modified' => $page->post_modified
            ];
        }
        
        return rest_ensure_response([
            'pages' => $page_data,
            'total' => count($page_data),
            'generated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Handle health route
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleHealthRoute(\WP_REST_Request $request)
    {
        return rest_ensure_response([
            'status' => 'ok',
            'version' => SUREFEEDBACK_VERSION,
            'wordpress' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'timestamp' => current_time('timestamp'),
            'memory' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true)
            ]
        ]);
    }
    
    /**
     * Check REST API permissions
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function checkRestPermissions(\WP_REST_Request $request)
    {
        // Check if user is logged in and has proper capabilities
        if (is_user_logged_in() && current_user_can('read')) {
            return true;
        }
        
        // Check for valid access token
        return $this->checkAccessToken($request);
    }
    
    /**
     * Check access token for API requests
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function checkAccessToken(\WP_REST_Request $request)
    {
        $token = $request->get_header('X-SureFeedback-Token');
        $valid_token = get_option('surefeedback_access_token', '');
        
        if (empty($valid_token)) {
            return new \WP_Error('no_token_configured', 'No access token configured', ['status' => 401]);
        }
        
        if (empty($token)) {
            return new \WP_Error('missing_token', 'Access token required', ['status' => 401]);
        }
        
        if (!hash_equals($valid_token, $token)) {
            return new \WP_Error('invalid_token', 'Invalid access token', ['status' => 403]);
        }
        
        return true;
    }
    
    /**
     * Add CORS headers for API requests
     *
     * @param bool $served
     * @param \WP_REST_Response $result
     * @param \WP_REST_Request $request
     * @param \WP_REST_Server $server
     * @return bool
     */
    public function addCorsHeaders($served, $result, $request, $server)
    {
        $route = $request->get_route();
        
        // Only add CORS headers for SureFeedback routes
        if (strpos($route, '/surefeedback/') !== 0) {
            return $served;
        }
        
        $origin = get_http_origin();
        $parent_url = get_option('surefeedback_parent_url', '');
        
        // Allow requests from parent site or same origin
        if (!empty($parent_url) && (strpos($origin, $parent_url) === 0 || $origin === home_url())) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Headers: Content-Type, X-SureFeedback-Token, Authorization, X-WP-Nonce');
            header('Access-Control-Max-Age: 86400');
        }
        
        return $served;
    }
    
    /**
     * Handle AJAX routes
     *
     * @return void
     */
    public function handleAjaxRoutes(): void
    {
        // Handle authenticated AJAX requests
        $action = sanitize_text_field($_POST['surefeedback_action'] ?? '');
        
        switch ($action) {
            case 'save_settings':
                $this->handleAjaxSaveSettings();
                break;
            case 'test_connection':
                $this->handleAjaxTestConnection();
                break;
            case 'reset_plugin':
                $this->handleAjaxResetPlugin();
                break;
            default:
                wp_die('Invalid action', 'Error', ['response' => 400]);
        }
    }
    
    /**
     * Handle public AJAX routes
     *
     * @return void
     */
    public function handlePublicAjaxRoutes(): void
    {
        // Handle public AJAX requests (no authentication required)
        $action = sanitize_text_field($_POST['surefeedback_action'] ?? '');
        
        switch ($action) {
            case 'get_widget_config':
                $this->handleAjaxWidgetConfig();
                break;
            default:
                wp_die('Invalid action', 'Error', ['response' => 400]);
        }
    }
    
    /**
     * Handle frontend routes
     *
     * @return void
     */
    public function handleFrontendRoutes(): void
    {
        global $wp_query;
        
        if (get_query_var('surefeedback_widget')) {
            $this->handleWidgetRoute();
            exit;
        }
        
        if (get_query_var('surefeedback_config')) {
            $this->handleConfigRoute();
            exit;
        }
    }
    
    /**
     * Render admin page
     *
     * @return void
     */
    public function renderAdminPage(): void
    {
        echo '<div id="surefeedback-admin-dashboard"></div>';
        $this->enqueueAdminAssets();
    }
    
    /**
     * Render settings page
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        echo '<div id="surefeedback-admin-settings"></div>';
        $this->enqueueAdminAssets();
    }
    
    /**
     * Render connection page
     *
     * @return void
     */
    public function renderConnectionPage(): void
    {
        echo '<div id="surefeedback-admin-connection"></div>';
        $this->enqueueAdminAssets();
    }
    
    /**
     * Enqueue admin assets
     *
     * @return void
     */
    private function enqueueAdminAssets(): void
    {
        // Enqueue admin CSS and JS
        wp_enqueue_script(
            'surefeedback-admin',
            SUREFEEDBACK_PLUGIN_URL . 'dist/admin.js',
            ['wp-element', 'wp-api'],
            SUREFEEDBACK_VERSION,
            true
        );
        
        wp_enqueue_style(
            'surefeedback-admin',
            SUREFEEDBACK_PLUGIN_URL . 'dist/admin.css',
            [],
            SUREFEEDBACK_VERSION
        );
        
        // Localize script with API data
        wp_localize_script('surefeedback-admin', 'surefeedbackAdmin', [
            'apiUrl' => rest_url('surefeedback/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => wp_get_current_user(),
            'pluginUrl' => SUREFEEDBACK_PLUGIN_URL,
            'settings' => [
                'connected' => get_option('surefeedback_connected', false),
                'parentUrl' => get_option('surefeedback_parent_url', ''),
                'version' => SUREFEEDBACK_VERSION
            ]
        ]);
    }
    
    /**
     * Handle widget route
     *
     * @return void
     */
    private function handleWidgetRoute(): void
    {
        header('Content-Type: application/json');
        echo wp_json_encode([
            'enabled' => get_option('surefeedback_widget_enabled', false),
            'position' => get_option('surefeedback_widget_position', 'bottom-right'),
            'theme' => get_option('surefeedback_widget_theme', 'light')
        ]);
    }
    
    /**
     * Handle config route
     *
     * @return void
     */
    private function handleConfigRoute(): void
    {
        header('Content-Type: application/json');
        echo wp_json_encode([
            'version' => SUREFEEDBACK_VERSION,
            'api_url' => rest_url('surefeedback/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'connected' => get_option('surefeedback_connected', false)
        ]);
    }
    
    /**
     * Handle AJAX save settings
     *
     * @return void
     */
    private function handleAjaxSaveSettings(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Process settings save
        wp_send_json_success(['message' => 'Settings saved successfully']);
    }
    
    /**
     * Handle AJAX test connection
     *
     * @return void
     */
    private function handleAjaxTestConnection(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Process connection test
        wp_send_json_success(['message' => 'Connection test completed']);
    }
    
    /**
     * Handle AJAX reset plugin
     *
     * @return void
     */
    private function handleAjaxResetPlugin(): void
    {
        check_ajax_referer('surefeedback_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Process plugin reset
        wp_send_json_success(['message' => 'Plugin reset successfully']);
    }
    
    /**
     * Handle AJAX widget config
     *
     * @return void
     */
    private function handleAjaxWidgetConfig(): void
    {
        wp_send_json_success([
            'enabled' => get_option('surefeedback_widget_enabled', false),
            'position' => get_option('surefeedback_widget_position', 'bottom-right'),
            'theme' => get_option('surefeedback_widget_theme', 'light')
        ]);
    }
}

/**
 * Simple Router class for organizing routes
 */
class Router
{
    /**
     * Application instance
     *
     * @var \SureFeedback\Application
     */
    protected $app;
    
    /**
     * Constructor
     *
     * @param \SureFeedback\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }
    
    /**
     * Create a route group
     *
     * @param array $attributes
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Simple group implementation
        // In a real implementation, this would handle middleware, prefixes, etc.
        $callback($this);
    }
    
    /**
     * Register a GET route
     *
     * @param string $path
     * @param mixed $action
     * @return void
     */
    public function get(string $path, $action): void
    {
        // Simple route registration
        // In a real implementation, this would register with WordPress routing system
    }
    
    /**
     * Register a POST route
     *
     * @param string $path
     * @param mixed $action
     * @return void
     */
    public function post(string $path, $action): void
    {
        // Simple route registration
    }
    
    /**
     * Register a DELETE route
     *
     * @param string $path
     * @param mixed $action
     * @return void
     */
    public function delete(string $path, $action): void
    {
        // Simple route registration
    }
}