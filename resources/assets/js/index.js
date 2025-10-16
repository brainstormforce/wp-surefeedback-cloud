/**
 * API Services Index
 * 
 * Central export point for all API services and utilities.
 * Provides a unified interface for importing and using API functionality.
 * 
 * @package SureFeedback
 */

// Core API Gateway
export { apiGateway, ApiGateway } from './api/gateway.js';

// Services
export { 
    connectionService, 
    getConnectionStatus, 
    verifyConnection, 
    connectToParent, 
    disconnectFromParent, 
    getConnectionHealth, 
    testConnection 
} from './services/connection.js';

export { 
    settingsService, 
    getSettings, 
    updateSettings, 
    getGeneralSettings, 
    updateGeneralSettings, 
    getWhiteLabelSettings, 
    updateWhiteLabelSettings, 
    resetSettings, 
    importSettings, 
    exportSettings 
} from './services/settings.js';

export { 
    dashboardService, 
    getDashboardStats, 
    getDashboardQuickAccess, 
    getDashboardRecentActivity, 
    refreshDashboard, 
    getDashboardOverview, 
    getDashboardWidgetData 
} from './services/dashboard.js';

export { 
    adminService, 
    getAdminSettings, 
    saveGeneralSettings, 
    saveWhiteLabelSettings, 
    verifyIntegration, 
    disconnectSite, 
    getAdminConnectionStatus, 
    testParentSite, 
    generateAccessToken, 
    resetAdminSettings, 
    getSystemInfo 
} from './services/admin.js';

// Utilities
export { 
    ApiError, 
    ValidationError, 
    ConnectionError, 
    errorHandler, 
    withErrorHandling, 
    safeAsync 
} from './utils/errors.js';

export { 
    tokenManager, 
    authManager, 
    authUtils, 
    TokenManager, 
    AuthManager 
} from './utils/auth.js';

export { 
    cacheManager, 
    CacheManager, 
    MemoryStorage 
} from './utils/cache.js';

// Constants
export { 
    API_CONFIG, 
    API_ENDPOINTS, 
    HTTP_STATUS, 
    ERROR_CODES, 
    CACHE_CONFIG, 
    REQUEST_CONFIG, 
    UI_CONFIG, 
    ENVIRONMENT 
} from './constants/api.js';

/**
 * Initialize all services
 * Call this function to initialize all API services and utilities.
 */
export function initializeServices() {
    // Initialize services in order
    tokenManager.init();
    connectionService.init();
    settingsService.init();
    dashboardService.init();
    adminService.init();
}

/**
 * Cleanup all services
 * Call this function when the application is being destroyed.
 */
export function cleanupServices() {
    connectionService.destroy();
    settingsService.destroy();
    dashboardService.destroy();
    adminService.destroy();
}

/**
 * API Service Factory
 * Factory class for creating and managing API service instances.
 */
export class ApiServiceFactory {
    constructor() {
        this.services = new Map();
        this.initialized = false;
    }

    /**
     * Initialize factory and all services
     */
    init() {
        if (this.initialized) {
            return;
        }

        // Register services
        this.services.set('connection', connectionService);
        this.services.set('settings', settingsService);
        this.services.set('dashboard', dashboardService);
        this.services.set('admin', adminService);

        // Initialize all services
        initializeServices();
        
        this.initialized = true;
    }

    /**
     * Get service by name
     * @param {string} name - Service name
     * @returns {Object|null}
     */
    getService(name) {
        return this.services.get(name) || null;
    }

    /**
     * Check if service exists
     * @param {string} name - Service name
     * @returns {boolean}
     */
    hasService(name) {
        return this.services.has(name);
    }

    /**
     * Get all service names
     * @returns {Array<string>}
     */
    getServiceNames() {
        return Array.from(this.services.keys());
    }

    /**
     * Get service health status
     * @returns {Object}
     */
    getHealthStatus() {
        const status = {};
        
        this.services.forEach((service, name) => {
            status[name] = {
                initialized: Boolean(service),
                hasListeners: service.listeners?.length > 0,
                lastError: service.lastError || null
            };
        });

        return status;
    }

    /**
     * Cleanup factory and all services
     */
    destroy() {
        if (!this.initialized) {
            return;
        }

        cleanupServices();
        this.services.clear();
        this.initialized = false;
    }
}

// Create singleton factory instance
export const apiServiceFactory = new ApiServiceFactory();

/**
 * Convenience methods for common API operations
 */
export const api = {
    // Connection operations
    async connect(parentUrl, accessToken, signature) {
        return connectToParent({ parentUrl, accessToken, signature });
    },

    async disconnect() {
        return disconnectFromParent();
    },

    async checkConnection() {
        return getConnectionStatus();
    },

    // Settings operations
    async getSettings() {
        return getSettings();
    },

    async saveSettings(data) {
        return updateSettings(data);
    },

    async saveGeneralSettings(data) {
        return updateGeneralSettings(data);
    },

    async saveWhiteLabelSettings(data) {
        return updateWhiteLabelSettings(data);
    },

    // Dashboard operations
    async getDashboard() {
        return getDashboardOverview();
    },

    async refreshDashboard() {
        return refreshDashboard();
    },

    // Admin operations
    async verifyIntegration(data) {
        return verifyIntegration(data);
    },

    async testParentSite(url) {
        return testParentSite(url);
    },

    async resetSettings(section = 'all') {
        return resetAdminSettings(section);
    }
};

// Export default for easy importing
export default {
    apiGateway,
    connectionService,
    settingsService,
    dashboardService,
    adminService,
    tokenManager,
    authManager,
    cacheManager,
    errorHandler,
    apiServiceFactory,
    api,
    initializeServices,
    cleanupServices
};