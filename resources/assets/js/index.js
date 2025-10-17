/**
 * API Services Index
 * 
 * Central export point for all API services and utilities.
 * Provides a unified interface for importing and using API functionality.
 * 
 * @package SureFeedback
 */

// Core API Gateway
import { apiGateway as _apiGateway, ApiGateway as _ApiGateway } from './api/gateway.js';
export const apiGateway = _apiGateway;
export const ApiGateway = _ApiGateway;

// Services
import {
    connectionService as _connectionService,
    getConnectionStatus,
    verifyConnection,
    connectToParent,
    disconnectFromParent,
    getConnectionHealth,
    testConnection
} from './services/connection.js';
export const connectionService = _connectionService;
export { getConnectionStatus, verifyConnection, connectToParent, disconnectFromParent, getConnectionHealth, testConnection };

import {
    settingsService as _settingsService,
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
export const settingsService = _settingsService;
export { getSettings, updateSettings, getGeneralSettings, updateGeneralSettings, getWhiteLabelSettings, updateWhiteLabelSettings, resetSettings, importSettings, exportSettings };

import {
    verificationService as _verificationService,
    verifyConnection as verifyConnectionStatus
} from './services/verification.js';
export const verificationService = _verificationService;
export { verifyConnectionStatus };

import {
    dashboardService as _dashboardService,
    getDashboardStats,
    getDashboardQuickAccess,
    getDashboardRecentActivity,
    refreshDashboard,
    getDashboardOverview,
    getDashboardWidgetData
} from './services/dashboard.js';
export const dashboardService = _dashboardService;
export { getDashboardStats, getDashboardQuickAccess, getDashboardRecentActivity, refreshDashboard, getDashboardOverview, getDashboardWidgetData };

import {
    adminService as _adminService,
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
export const adminService = _adminService;
export { getAdminSettings, saveGeneralSettings, saveWhiteLabelSettings, verifyIntegration, disconnectSite, getAdminConnectionStatus, testParentSite, generateAccessToken, resetAdminSettings, getSystemInfo };

// Utilities
import {
    ApiError,
    ValidationError,
    ConnectionError,
    errorHandler as _errorHandler,
    withErrorHandling,
    safeAsync
} from './utils/errors.js';
export const errorHandler = _errorHandler;
export { ApiError, ValidationError, ConnectionError, withErrorHandling, safeAsync };

import {
    tokenManager as _tokenManager,
    authManager as _authManager,
    authUtils,
    TokenManager,
    AuthManager
} from './utils/auth.js';
export const tokenManager = _tokenManager;
export const authManager = _authManager;
export { authUtils, TokenManager, AuthManager };

import {
    cacheManager as _cacheManager,
    CacheManager,
    MemoryStorage
} from './utils/cache.js';
export const cacheManager = _cacheManager;
export { CacheManager, MemoryStorage };

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

export default {
    apiGateway: _apiGateway,
    connectionService: _connectionService,
    settingsService: _settingsService,
    dashboardService: _dashboardService,
    adminService: _adminService,
    tokenManager: _tokenManager,
    authManager: _authManager,
    cacheManager: _cacheManager,
    errorHandler: _errorHandler,
    apiServiceFactory,
    api,
    initializeServices,
    cleanupServices
};