/**
 * API Configuration Constants
 * 
 * Central configuration for all API-related constants,
 * endpoints, and settings.
 * 
 * @package SureFeedback
 */

// Get WordPress REST API settings
const wpApiSettings = window.wpApiSettings || {
    root: window.location.origin + '/wp-json/',
    nonce: window.sureFeedbackAdmin?.rest_nonce || window.sureFeedbackAdmin?.nonce || '',
};

// Get SureFeedback API settings from admin data
const sureFeedbackApiSettings = window.sureFeedbackAdmin || {};

// API Configuration
export const API_CONFIG = {
    // Base URLs
    BASE_URL: wpApiSettings.root + 'surefeedback/v1',
    WP_API_BASE: wpApiSettings.root + 'wp/v2',
    
    // Request settings
    TIMEOUT: 30000, // 30 seconds
    RETRY_ATTEMPTS: 3,
    RETRY_DELAY: 1000, // 1 second
    
    // Authentication
    NONCE: wpApiSettings.nonce,
    
    // Headers
    HEADERS: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }
};

// API Endpoints
export const API_ENDPOINTS = {
    // Connection endpoints
    CONNECTION: {
        STATUS: 'connection/status',
        VERIFY: 'connection/verify',
        CONNECT: 'connection/connect',
        HEALTH: 'connection/health',
    },
    
    // Settings endpoints
    SETTINGS: {
        INDEX: 'settings',
        UPDATE: 'settings',
        GENERAL: 'settings/general',
        UPDATE_GENERAL: 'settings/general',
    },
    
    // Admin API endpoints
    ADMIN: {
        SETTINGS: 'admin/settings',
        SAVE_GENERAL: 'admin/general-settings',
        VERIFY_INTEGRATION: 'admin/verify-integration',
        CONNECTION_STATUS: 'admin/connection-status',
    },
    
    // Public API endpoints (for frontend widget)
    PUBLIC: {
        PAGES: 'pages',
        FEEDBACK: 'feedback',
    }
};

// HTTP Status Codes
export const HTTP_STATUS = {
    OK: 200,
    CREATED: 201,
    NO_CONTENT: 204,
    BAD_REQUEST: 400,
    UNAUTHORIZED: 401,
    FORBIDDEN: 403,
    NOT_FOUND: 404,
    METHOD_NOT_ALLOWED: 405,
    CONFLICT: 409,
    UNPROCESSABLE_ENTITY: 422,
    TOO_MANY_REQUESTS: 429,
    INTERNAL_SERVER_ERROR: 500,
    BAD_GATEWAY: 502,
    SERVICE_UNAVAILABLE: 503,
    GATEWAY_TIMEOUT: 504,
};

// Error Codes
export const ERROR_CODES = {
    // Network errors
    NETWORK_ERROR: 'network_error',
    TIMEOUT_ERROR: 'timeout_error',
    
    // Authentication errors
    INVALID_TOKEN: 'rest_forbidden',
    INVALID_NONCE: 'rest_forbidden_context',
    INVALID_CREDENTIALS: 'invalid_credentials',
    
    // Validation errors
    VALIDATION_ERROR: 'rest_invalid_param',
    MISSING_PARAMETER: 'rest_missing_callback_param',
    
    // Connection errors
    CONNECTION_FAILED: 'connection_failed',
    PARENT_SITE_UNREACHABLE: 'parent_site_unreachable',
    INVALID_PARENT_URL: 'invalid_parent_url',
    
    // General errors
    INTERNAL_ERROR: 'internal_error',
    NOT_FOUND: 'rest_not_found',
    METHOD_NOT_ALLOWED: 'rest_no_route',
};

// Cache Configuration
export const CACHE_CONFIG = {
    // Cache keys
    KEYS: {
        CONNECTION_STATUS: 'surefeedback_connection_status',
        SETTINGS: 'surefeedback_settings',
        USER_PREFERENCES: 'surefeedback_user_preferences',
    },
    
    // Cache durations (in milliseconds)
    DURATIONS: {
        SHORT: 5 * 60 * 1000,      // 5 minutes
        MEDIUM: 15 * 60 * 1000,    // 15 minutes
        LONG: 60 * 60 * 1000,      // 1 hour
        VERY_LONG: 24 * 60 * 60 * 1000, // 24 hours
    }
};

// Request Configuration
export const REQUEST_CONFIG = {
    // Default timeouts for different types of requests
    TIMEOUTS: {
        FAST: 5000,      // 5 seconds - for quick status checks
        NORMAL: 15000,   // 15 seconds - for normal API calls
        SLOW: 30000,     // 30 seconds - for uploads/large operations
        VERY_SLOW: 60000, // 60 seconds - for sync operations
    },
    
    // Retry configuration
    RETRY: {
        MAX_ATTEMPTS: 3,
        INITIAL_DELAY: 1000,  // 1 second
        MAX_DELAY: 10000,     // 10 seconds
        BACKOFF_FACTOR: 2,    // Exponential backoff
    }
};

// UI Configuration
export const UI_CONFIG = {
    // Notification durations
    NOTIFICATION_DURATION: {
        SUCCESS: 3000,    // 3 seconds
        ERROR: 5000,      // 5 seconds
        WARNING: 4000,    // 4 seconds
        INFO: 3000,       // 3 seconds
    },
    
    // Loading states
    LOADING_DELAY: 200,   // Delay before showing loading indicator
    
    // Polling intervals
    POLLING: {
        CONNECTION_STATUS: 30000,  // 30 seconds
    }
};

// Environment Detection
export const ENVIRONMENT = {
    IS_DEVELOPMENT: window.location.hostname === 'localhost' || 
                   window.location.hostname.includes('dev') ||
                   window.location.hostname.includes('staging'),
    
    IS_WORDPRESS_ADMIN: window.location.pathname.includes('/wp-admin/'),
    
    HAS_CONSOLE: typeof console !== 'undefined',
    
    SUPPORTS_FETCH: typeof fetch !== 'undefined',
    
    SUPPORTS_LOCAL_STORAGE: typeof Storage !== 'undefined',
};