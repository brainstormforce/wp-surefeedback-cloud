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
 * Get SureFeedback App URL
 */
function surefeedback_get_app_url() {
    // Check for custom parent URL first
    $custom_url = get_option('surefeedback_parent_url', '');
    if (!empty($custom_url)) {
        return $custom_url;
    }
    
    // Check environment variable
    $env_url = surefeedback_env('SUREFEEDBACK_APP_URL');
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
 * Get SureFeedback API URL
 */
function surefeedback_get_api_url() {
    // Check for custom API URL first
    $custom_url = get_option('surefeedback_api_url', '');
    if (!empty($custom_url)) {
        return $custom_url;
    }
    
    // Check environment variable
    $env_url = surefeedback_env('SUREFEEDBACK_API_URL');
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
 * Get current environment
 */
function surefeedback_get_environment() {
    $env = surefeedback_env('SUREFEEDBACK_ENV');
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