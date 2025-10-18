<?php
/**
 * Simple Environment Helper
 * 
 * Loads environment variables from .env file or uses defaults
 * 
 * @package SureFeedback
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load environment variables from .env file
 */
function surefeedback_load_env() {
    $env_file = SUREFEEDBACK_PLUGIN_DIR . '.env';
    
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Skip comments
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                if (!empty($key)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
}

/**
 * Get environment variable with fallback
 */
function surefeedback_env($key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }

    return $value;
}

/**
 * Get environment variable with fallback
 *
 * @param string $var_name The variable name (e.g., 'SUREFEEDBACK_APP_URL')
 * @param mixed $default Default value if not found
 * @return mixed The environment value
 */
function surefeedback_get_env_var($var_name, $default = null) {
    $value = surefeedback_env($var_name);

    if ($value !== null) {
        return $value;
    }

    return $default;
}

/**
 * Get SureFeedback App URL
 */
function surefeedback_get_app_url() {
    // Check for custom parent URL first
    $custom_url = get_option('surefeedback_parent_url', '');
    if (!empty($custom_url)) {
        return $custom_url;
    }

    // Check environment variable (with ACTIVE_ENV support)
    $env_url = surefeedback_get_env_var('SUREFEEDBACK_APP_URL');
    if ($env_url) {
        return $env_url;
    }

    // Auto-detect based on current site URL
    $site_url = home_url();

    if (strpos($site_url, 'localhost') !== false || strpos($site_url, '.local') !== false) {
        return 'http://localhost:3000';
    }

    if (strpos($site_url, 'staging') !== false) {
        return 'https://app-staging.surefeedback.com';
    }

    return 'https://app.surefeedback.com'; // Production default
}

/**
 * Get SureFeedback API URL (with /api/v1 suffix)
 */
function surefeedback_get_api_url() {
    // Check for custom API URL first
    $custom_url = get_option('surefeedback_api_url', '');
    if (!empty($custom_url)) {
        return $custom_url;
    }

    // Check environment variable (with ACTIVE_ENV support)
    $env_url = surefeedback_get_env_var('SUREFEEDBACK_API_URL');
    if ($env_url) {
        return $env_url;
    }

    // Auto-detect based on current site URL
    $site_url = home_url();

    if (strpos($site_url, 'localhost') !== false || strpos($site_url, '.local') !== false) {
        return 'http://localhost:8000/api/v1';
    }

    if (strpos($site_url, 'staging') !== false) {
        return 'https://api-staging.surefeedback.com/api/v1';
    }

    return 'https://api.surefeedback.com/api/v1'; // Production default
}

/**
 * Get SureFeedback Base API URL (without /api/v1 suffix)
 */
function surefeedback_get_base_api_url() {
    // Check for custom API URL first
    $custom_url = get_option('surefeedback_api_url', '');
    if (!empty($custom_url)) {
        return str_replace('/api/v1', '', $custom_url);
    }

    // Check environment variable (with ACTIVE_ENV support)
    $env_url = surefeedback_get_env_var('SUREFEEDBACK_API_URL');
    if ($env_url) {
        return str_replace('/api/v1', '', $env_url);
    }

    // Auto-detect based on current site URL
    $site_url = home_url();

    if (strpos($site_url, 'localhost') !== false || strpos($site_url, '.local') !== false) {
        return 'http://localhost:8000';
    }

    if (strpos($site_url, 'staging') !== false) {
        return 'https://api-staging.surefeedback.com';
    }

    return 'https://api.surefeedback.com'; // Production default
}

/**
 * Get current environment
 */
function surefeedback_get_environment() {
    // Check environment variable (with ACTIVE_ENV support)
    $env = surefeedback_get_env_var('SUREFEEDBACK_ENV');
    if ($env) {
        return $env;
    }

    // Auto-detect
    $site_url = home_url();

    if (strpos($site_url, 'localhost') !== false || strpos($site_url, '.local') !== false) {
        return 'development';
    }

    if (strpos($site_url, 'staging') !== false) {
        return 'staging';
    }

    return 'production';
}

// Load environment variables when this file is included
surefeedback_load_env();