<?php
/**
 * Application Configuration
 *
 * Configuration settings for the SureFeedback plugin application.
 * This file contains all the core configuration settings that
 * control the behavior of the application.
 *
 * @package SureFeedback
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
    
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */
    
    'name' => 'SureFeedback',
    
    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes.
    |
    */
    
    'env' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'development' : 'production' ),
    
    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */
    
    'debug' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
    
    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the WordPress command line tool. You should set this to the root of
    | your application so that it is used when running WordPress commands.
    |
    */
    
    'url' => get_site_url(),
    
    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value is the version of your application. This value is used for
    | cache busting, API versioning, and display purposes throughout your
    | application.
    |
    */
    
    'version' => defined('SUREFEEDBACK_VERSION') ? SUREFEEDBACK_VERSION : '1.0.0',
    
    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */
    
    'timezone' => get_option('timezone_string', 'UTC'),
    
    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */
    
    'locale' => get_locale(),
    
    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */
    
    'fallback_locale' => 'en_US',
    
    /*
    |--------------------------------------------------------------------------
    | Application Base Path
    |--------------------------------------------------------------------------
    |
    | This value is the absolute path to the application directory. This is
    | used for resolving file paths and directory structures throughout
    | the application.
    |
    */
    
    'base_path' => defined('SUREFEEDBACK_PLUGIN_DIR') ? SUREFEEDBACK_PLUGIN_DIR : dirname(__DIR__),
    
    /*
    |--------------------------------------------------------------------------
    | Application Plugin URL
    |--------------------------------------------------------------------------
    |
    | This value is the URL to the plugin directory. This is used for
    | generating URLs to assets and other resources within the plugin.
    |
    */
    
    'plugin_url' => defined('SUREFEEDBACK_PLUGIN_URL') ? SUREFEEDBACK_PLUGIN_URL : plugin_dir_url(dirname(__DIR__)),
    
    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database configuration for the application. Uses WordPress database
    | connection settings and table prefix.
    |
    */
    
    'database' => [
        'prefix' => $GLOBALS['wpdb']->prefix . 'surefeedback_',
        'charset' => DB_CHARSET,
        'collate' => DB_COLLATE,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching options for the application. This includes cache
    | drivers, default cache times, and cache prefixes.
    |
    */
    
    'cache' => [
        'driver' => 'wordpress', // WordPress transients
        'prefix' => 'surefeedback_',
        'default_ttl' => 3600, // 1 hour
        'long_ttl' => 86400, // 24 hours
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging options for the application. This includes log
    | levels, file locations, and rotation settings.
    |
    */
    
    'logging' => [
        'default' => 'wordpress',
        'channels' => [
            'wordpress' => [
                'driver' => 'wordpress',
                'level' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'debug' : 'error' ),
            ],
            'file' => [
                'driver' => 'file',
                'path' => WP_CONTENT_DIR . '/debug.log',
                'level' => 'debug',
                'max_files' => 5,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the REST API endpoints, including rate limiting,
    | authentication settings, and CORS policies.
    |
    */
    
    'api' => [
        'namespace' => 'surefeedback/v1',
        'rate_limit' => [
            'enabled' => true,
            'requests_per_minute' => 60,
            'burst_limit' => 100,
        ],
        'cors' => [
            'enabled' => true,
            'allowed_origins' => [],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'X-SureFeedback-Token', 'Authorization', 'X-WP-Nonce'],
            'max_age' => 86400,
        ],
        'authentication' => [
            'token_header' => 'X-SureFeedback-Token',
            'token_length' => 32,
            'signature_algorithm' => 'sha256',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for the application including token management,
    | encryption settings, and access controls.
    |
    */
    
    'security' => [
        'token_expiry' => 2592000, // 30 days
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'require_https' => false, // Set to true in production
        'allowed_capabilities' => [
            'manage_options',
            'edit_pages',
            'edit_posts',
            'publish_pages',
            'publish_posts',
        ],
        'guest_access' => [
            'enabled' => false,
            'allowed_actions' => ['view', 'comment'],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Feature flags to enable or disable specific functionality throughout
    | the application. Useful for gradual rollouts and A/B testing.
    |
    */
    
    'features' => [
        'dashboard_analytics' => true,
        'white_label_support' => true,
        'multi_site_support' => true,
        'guest_comments' => false,
        'real_time_updates' => false,
        'webhook_support' => false,
        'advanced_permissions' => true,
        'backup_restore' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings including memory limits,
    | execution timeouts, and resource management.
    |
    */
    
    'performance' => [
        'memory_limit' => '256M',
        'max_execution_time' => 30,
        'max_input_vars' => 1000,
        'optimize_autoloader' => true,
        'enable_opcache' => true,
        'compress_output' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for file upload handling including size limits,
    | allowed file types, and storage settings.
    |
    */
    
    'uploads' => [
        'max_file_size' => 5242880, // 5MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'storage_path' => WP_CONTENT_DIR . '/uploads/surefeedback/',
        'url_path' => WP_CONTENT_URL . '/uploads/surefeedback/',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Email configuration for notifications and communications. Uses
    | WordPress mail functions with customizable templates.
    |
    */
    
    'mail' => [
        'from' => [
            'address' => get_option('admin_email'),
            'name' => get_bloginfo('name'),
        ],
        'templates' => [
            'new_feedback' => 'emails.new-feedback',
            'feedback_resolved' => 'emails.feedback-resolved',
            'connection_established' => 'emails.connection-established',
        ],
        'notifications' => [
            'new_feedback' => true,
            'status_changes' => true,
            'system_alerts' => true,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the frontend feedback widget including appearance,
    | behavior, and integration settings.
    |
    */
    
    'widget' => [
        'default_position' => 'bottom-right',
        'default_theme' => 'light',
        'animation_duration' => 300,
        'auto_hide_delay' => 5000,
        'mobile_responsive' => true,
        'keyboard_shortcuts' => true,
        'analytics_tracking' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for third-party integrations and external services.
    |
    */
    
    'integrations' => [
        'analytics' => [
            'google_analytics' => [
                'enabled' => false,
                'tracking_id' => '',
                'track_events' => true,
            ],
        ],
        'webhooks' => [
            'enabled' => false,
            'timeout' => 10,
            'retry_attempts' => 3,
            'retry_delay' => 5,
        ],
    ],
    
];