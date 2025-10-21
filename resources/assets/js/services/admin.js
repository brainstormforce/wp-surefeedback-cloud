/**
 * Admin Service
 * 
 * Handles all admin-related API calls and functionality.
 * Provides methods for admin settings, verification, and site management.
 * 
 * @package SureFeedback
 */

import { apiGateway } from '../api/gateway.js';
import { API_ENDPOINTS, CACHE_CONFIG } from '../constants/api.js';
import { ValidationError, withErrorHandling } from '../utils/errors.js';
import { cacheManager } from '../utils/cache.js';
import { authManager } from '../utils/auth.js';

/**
 * Admin Service class
 */
class AdminService {
    constructor() {
        this.adminSettings = null;
        this.listeners = [];
    }

    /**
     * Initialize admin service
     */
    init() {
        this.loadCachedSettings();
    }

    /**
     * Load cached admin settings
     */
    loadCachedSettings() {
        const cached = cacheManager.get('admin_settings');
        if (cached) {
            this.adminSettings = cached;
        }
    }

    /**
     * Get admin settings
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getSettings(forceRefresh = false) {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to access admin settings');
        }

        const cacheKey = 'admin_settings';
        
        if (!forceRefresh) {
            const cached = cacheManager.get(cacheKey);
            if (cached) {
                return cached;
            }
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.ADMIN.SETTINGS);
            
            this.adminSettings = response;
            
            // Cache the response
            cacheManager.set(cacheKey, response, CACHE_CONFIG.DURATIONS.LONG);
            
            this.notifyListeners('admin_settings_updated', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to get admin settings');
            throw error;
        }
    }

    /**
     * Save general settings
     * @param {Object} generalData - General settings data
     * @returns {Promise<Object>}
     */
    async saveGeneralSettings(generalData) {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to save settings');
        }

        this.validateGeneralSettings(generalData);

        try {
            const response = await apiGateway.post(API_ENDPOINTS.ADMIN.SAVE_GENERAL, generalData);
            
            // Update cached settings
            if (this.adminSettings) {
                this.adminSettings.general = { ...this.adminSettings.general, ...response };
                cacheManager.set('admin_settings', this.adminSettings, CACHE_CONFIG.DURATIONS.LONG);
            }
            
            this.notifyListeners('general_settings_saved', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to save general settings');
            throw error;
        }
    }

    /**
     * Save white label settings
     * @param {Object} whiteLabelData - White label settings data
     * @returns {Promise<Object>}
     */
    async saveWhiteLabelSettings(whiteLabelData) {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to save white label settings');
        }

        this.validateWhiteLabelSettings(whiteLabelData);

        try {
            const response = await apiGateway.post(API_ENDPOINTS.ADMIN.SAVE_WHITE_LABEL, whiteLabelData);
            
            // Update cached settings
            if (this.adminSettings) {
                this.adminSettings.white_label = { ...this.adminSettings.white_label, ...response };
                cacheManager.set('admin_settings', this.adminSettings, CACHE_CONFIG.DURATIONS.LONG);
            }
            
            this.notifyListeners('white_label_settings_saved', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to save white label settings');
            throw error;
        }
    }

    /**
     * Verify integration with parent site
     * @param {Object} integrationData - Integration data to verify
     * @returns {Promise<Object>}
     */
    async verifyIntegration(integrationData = {}) {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to verify integration');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.ADMIN.VERIFY_INTEGRATION, integrationData);
            
            this.notifyListeners('integration_verified', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Integration verification failed');
            throw error;
        }
    }

    /**
     * Get connection status (admin perspective)
     * @returns {Promise<Object>}
     */
    async getConnectionStatus() {
        if (!this.canViewStatus()) {
            throw new Error('Insufficient permissions to view connection status');
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.ADMIN.CONNECTION_STATUS);
            
            this.notifyListeners('connection_status_updated', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to get connection status');
            throw error;
        }
    }

    /**
     * Test parent site connectivity
     * @param {string} parentUrl - Parent site URL to test
     * @returns {Promise<Object>}
     */
    async testParentSite(parentUrl) {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to test parent site');
        }

        if (!parentUrl || !this.isValidUrl(parentUrl)) {
            throw new ValidationError('parent_url', 'Valid parent URL is required');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.ADMIN.VERIFY_INTEGRATION, {
                parent_url: parentUrl,
                test_only: true
            });
            
            this.notifyListeners('parent_site_tested', { url: parentUrl, result: response });
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Parent site test failed');
            throw error;
        }
    }

    /**
     * Generate new access token
     * @returns {Promise<Object>}
     */
    async generateAccessToken() {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to generate access token');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.ADMIN.SETTINGS, {
                action: 'generate_token'
            });
            
            this.notifyListeners('access_token_generated', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to generate access token');
            throw error;
        }
    }

    /**
     * Reset plugin settings
     * @param {string} section - Section to reset ('all', 'general', 'white_label', 'connection')
     * @returns {Promise<Object>}
     */
    async resetSettings(section = 'all') {
        if (!this.canManageSettings()) {
            throw new Error('Insufficient permissions to reset settings');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.ADMIN.SETTINGS, {
                action: 'reset',
                section
            });
            
            // Clear relevant caches
            cacheManager.delete('admin_settings');
            if (section === 'all' || section === 'connection') {
                cacheManager.delete(CACHE_CONFIG.KEYS.CONNECTION_STATUS);
            }
            if (section === 'all' || section === 'general' || section === 'white_label') {
                cacheManager.delete(CACHE_CONFIG.KEYS.SETTINGS);
            }
            
            this.notifyListeners('settings_reset', { section, result: response });
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to reset settings');
            throw error;
        }
    }

    /**
     * Get system information
     * @returns {Promise<Object>}
     */
    async getSystemInfo() {
        if (!this.canViewStatus()) {
            throw new Error('Insufficient permissions to view system information');
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.ADMIN.SETTINGS, {
                params: { action: 'system_info' }
            });
            
            this.notifyListeners('system_info_updated', response);
            return response;
        } catch (error) {
            this.handleAdminError(error, 'Failed to get system information');
            throw error;
        }
    }

    /**
     * Validate general settings
     * @param {Object} data 
     */
    validateGeneralSettings(data) {
        if (!data || typeof data !== 'object') {
            throw new ValidationError('general_settings', 'Settings data must be an object');
        }

        // Required fields validation
        const requiredFields = ['plugin_name'];
        const missing = requiredFields.filter(field => !data[field]);
        
        if (missing.length > 0) {
            throw new ValidationError('general_settings', `Missing required fields: ${missing.join(', ')}`);
        }

        // URL validation
        if (data.parent_url && !this.isValidUrl(data.parent_url)) {
            throw new ValidationError('parent_url', 'Invalid parent URL format');
        }

        // Email validation
        if (data.admin_email && !this.isValidEmail(data.admin_email)) {
            throw new ValidationError('admin_email', 'Invalid email format');
        }
    }

    /**
     * Validate white label settings
     * @param {Object} data 
     */
    validateWhiteLabelSettings(data) {
        if (!data || typeof data !== 'object') {
            throw new ValidationError('white_label_settings', 'White label data must be an object');
        }

        // URL validation
        const urlFields = ['logo_url', 'website_url', 'support_url'];
        urlFields.forEach(field => {
            if (data[field] && !this.isValidUrl(data[field])) {
                throw new ValidationError(field, `Invalid ${field} format`);
            }
        });

        // Color validation
        const colorFields = ['primary_color', 'secondary_color', 'accent_color'];
        colorFields.forEach(field => {
            if (data[field] && !this.isValidColor(data[field])) {
                throw new ValidationError(field, `Invalid ${field} format`);
            }
        });

        // Text length validation
        const textFields = {
            'company_name': 100,
            'tagline': 200,
            'description': 500
        };
        
        Object.entries(textFields).forEach(([field, maxLength]) => {
            if (data[field] && data[field].length > maxLength) {
                throw new ValidationError(field, `${field} must be ${maxLength} characters or less`);
            }
        });
    }

    /**
     * Check if user can manage settings
     * @returns {boolean}
     */
    canManageSettings() {
        return authManager.canManage() || authManager.canConfigureSettings();
    }

    /**
     * Check if user can view status
     * @returns {boolean}
     */
    canViewStatus() {
        return authManager.canViewDashboard();
    }

    /**
     * Validate URL format
     * @param {string} url 
     * @returns {boolean}
     */
    isValidUrl(url) {
        try {
            const urlObj = new URL(url);
            return ['http:', 'https:'].includes(urlObj.protocol);
        } catch {
            return false;
        }
    }

    /**
     * Validate email format
     * @param {string} email 
     * @returns {boolean}
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate color format
     * @param {string} color 
     * @returns {boolean}
     */
    isValidColor(color) {
        // Test hex colors
        if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(color)) {
            return true;
        }

        // Test rgb/rgba colors
        if (/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/.test(color) ||
            /^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)$/.test(color)) {
            return true;
        }

        return false;
    }

    /**
     * Handle admin errors
     * @param {Error} error 
     * @param {string} context 
     */
    handleAdminError(error, context) {
        this.notifyListeners('admin_error', { error, context });
    }

    /**
     * Add event listener
     * @param {Function} callback 
     */
    addListener(callback) {
        this.listeners.push(callback);
    }

    /**
     * Remove event listener
     * @param {Function} callback 
     */
    removeListener(callback) {
        const index = this.listeners.indexOf(callback);
        if (index > -1) {
            this.listeners.splice(index, 1);
        }
    }

    /**
     * Notify listeners of events
     * @param {string} event 
     * @param {any} data 
     */
    notifyListeners(event, data = null) {
        this.listeners.forEach(listener => {
            try {
                listener(event, data);
            } catch (error) {
                // Error handled silently
            }
        });
    }

    /**
     * Get admin summary
     * @returns {Object}
     */
    getAdminSummary() {
        return {
            hasSettings: Boolean(this.adminSettings),
            canManage: this.canManageSettings(),
            canView: this.canViewStatus(),
            isConfigured: Boolean(this.adminSettings?.general?.parent_url),
            isWhiteLabelEnabled: Boolean(this.adminSettings?.white_label?.enabled)
        };
    }

    /**
     * Cleanup service
     */
    destroy() {
        this.listeners = [];
        this.adminSettings = null;
    }
}

// Create wrapped methods with error handling
const adminService = new AdminService();

// Export wrapped methods
export const {
    getSettings: getAdminSettings,
    saveGeneralSettings,
    saveWhiteLabelSettings,
    verifyIntegration,
    getConnectionStatus: getAdminConnectionStatus,
    testParentSite,
    generateAccessToken,
    resetSettings: resetAdminSettings,
    getSystemInfo
} = Object.fromEntries(
    ['getSettings', 'saveGeneralSettings', 'saveWhiteLabelSettings', 'verifyIntegration',
     'getConnectionStatus', 'testParentSite', 'generateAccessToken',
     'resetSettings', 'getSystemInfo']
        .map(method => [
            method,
            withErrorHandling(
                adminService[method].bind(adminService),
                { service: 'admin', method }
            )
        ])
);

// Export service instance
export { adminService };
export default adminService;