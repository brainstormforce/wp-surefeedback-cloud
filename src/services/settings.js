/**
 * Settings Service
 * 
 * Handles all settings-related API calls and state management.
 * Provides methods for getting and updating general settings and white label configuration.
 * 
 * @package SureFeedback
 */

import { apiGateway } from '../api/gateway.js';
import { API_ENDPOINTS, CACHE_CONFIG } from '../constants/api.js';
import { ValidationError, withErrorHandling } from '../utils/errors.js';
import { cacheManager } from '../utils/cache.js';

/**
 * Settings Service class
 */
class SettingsService {
    constructor() {
        this.settings = null;
        this.generalSettings = null;
        this.whiteLabelSettings = null;
        this.listeners = [];
    }

    /**
     * Initialize settings service
     */
    init() {
        this.loadCachedSettings();
    }

    /**
     * Load cached settings
     */
    loadCachedSettings() {
        const cached = cacheManager.get(CACHE_CONFIG.KEYS.SETTINGS);
        if (cached) {
            this.updateSettingsState(cached);
        }
    }

    /**
     * Get all settings
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getSettings(forceRefresh = false) {
        const cacheKey = CACHE_CONFIG.KEYS.SETTINGS;
        
        if (!forceRefresh) {
            const cached = cacheManager.get(cacheKey);
            if (cached) {
                return cached;
            }
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.SETTINGS.INDEX);
            
            // Update internal state
            this.updateSettingsState(response);
            
            // Cache the response
            cacheManager.set(cacheKey, response, CACHE_CONFIG.DURATIONS.LONG);
            
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to get settings');
            
            // Return fallback settings to prevent UI crashes
            const fallbackSettings = this.getFallbackSettings();
            this.updateSettingsState(fallbackSettings);
            return fallbackSettings;
        }
    }

    /**
     * Get fallback settings when API fails
     * @returns {Object}
     */
    getFallbackSettings() {
        return {
            success: false,
            data: {
                general: {
                    enabled: false,
                    site_url: window.location.origin,
                    display_name: 'SureFeedback',
                    description: '',
                    roles: ['administrator'],
                    guest_comments: false,
                    admin_comments: true
                },
                white_label: {
                    enabled: false,
                    company_name: '',
                    company_logo: '',
                    primary_color: '#0073aa',
                    hide_branding: false
                },
                availableRoles: [
                    { value: 'administrator', label: 'Administrator' },
                    { value: 'editor', label: 'Editor' },
                    { value: 'author', label: 'Author' }
                ]
            },
            message: 'Settings loaded from fallback (API unavailable)'
        };
    }

    /**
     * Update settings
     * @param {Object} settingsData - Settings data to update
     * @returns {Promise<Object>}
     */
    async updateSettings(settingsData) {
        if (!settingsData || typeof settingsData !== 'object') {
            throw new ValidationError('settings', 'Settings data must be an object', settingsData);
        }

        try {
            const response = await apiGateway.put(API_ENDPOINTS.SETTINGS.UPDATE, settingsData);
            
            // Update internal state
            this.updateSettingsState(response);
            
            // Clear cache to force refresh
            cacheManager.delete(CACHE_CONFIG.KEYS.SETTINGS);
            
            this.notifyListeners('settings_updated', response);
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to update settings');
            throw error;
        }
    }

    /**
     * Get general settings
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getGeneralSettings(forceRefresh = false) {
        if (!forceRefresh && this.generalSettings) {
            return this.generalSettings;
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.SETTINGS.GENERAL);
            
            this.generalSettings = response;
            this.notifyListeners('general_settings_updated', response);
            
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to get general settings');
            throw error;
        }
    }

    /**
     * Update general settings
     * @param {Object} generalData - General settings data
     * @returns {Promise<Object>}
     */
    async updateGeneralSettings(generalData) {
        this.validateGeneralSettings(generalData);

        try {
            const response = await apiGateway.put(API_ENDPOINTS.SETTINGS.UPDATE_GENERAL, generalData);
            
            this.generalSettings = response;
            
            // Clear main settings cache
            cacheManager.delete(CACHE_CONFIG.KEYS.SETTINGS);
            
            this.notifyListeners('general_settings_updated', response);
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to update general settings');
            throw error;
        }
    }

    /**
     * Get white label settings
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getWhiteLabelSettings(forceRefresh = false) {
        if (!forceRefresh && this.whiteLabelSettings) {
            return this.whiteLabelSettings;
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.SETTINGS.WHITE_LABEL);
            
            this.whiteLabelSettings = response;
            this.notifyListeners('white_label_settings_updated', response);
            
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to get white label settings');
            throw error;
        }
    }

    /**
     * Update white label settings
     * @param {Object} whiteLabelData - White label settings data
     * @returns {Promise<Object>}
     */
    async updateWhiteLabelSettings(whiteLabelData) {
        this.validateWhiteLabelSettings(whiteLabelData);

        try {
            const response = await apiGateway.put(API_ENDPOINTS.SETTINGS.UPDATE_WHITE_LABEL, whiteLabelData);
            
            this.whiteLabelSettings = response;
            
            // Clear main settings cache
            cacheManager.delete(CACHE_CONFIG.KEYS.SETTINGS);
            
            this.notifyListeners('white_label_settings_updated', response);
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to update white label settings');
            throw error;
        }
    }

    /**
     * Reset settings to defaults
     * @param {string} section - Settings section to reset ('general', 'white_label', or 'all')
     * @returns {Promise<Object>}
     */
    async resetSettings(section = 'all') {
        try {
            const response = await apiGateway.post(API_ENDPOINTS.SETTINGS.INDEX, {
                action: 'reset',
                section: section
            });
            
            // Update internal state
            this.updateSettingsState(response);
            
            // Clear cache
            cacheManager.delete(CACHE_CONFIG.KEYS.SETTINGS);
            
            this.notifyListeners('settings_reset', { section, data: response });
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to reset settings');
            throw error;
        }
    }

    /**
     * Import settings from file or URL
     * @param {Object} importData - Import configuration
     * @returns {Promise<Object>}
     */
    async importSettings(importData) {
        const { source, data, url } = importData;

        if (!source || !['file', 'url', 'data'].includes(source)) {
            throw new ValidationError('source', 'Import source must be file, url, or data');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.SETTINGS.INDEX, {
                action: 'import',
                source,
                data,
                url
            });
            
            // Update internal state
            this.updateSettingsState(response);
            
            // Clear cache
            cacheManager.delete(CACHE_CONFIG.KEYS.SETTINGS);
            
            this.notifyListeners('settings_imported', response);
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to import settings');
            throw error;
        }
    }

    /**
     * Export settings
     * @param {string} format - Export format ('json', 'php')
     * @param {Array} sections - Sections to export
     * @returns {Promise<Object>}
     */
    async exportSettings(format = 'json', sections = ['general', 'white_label']) {
        try {
            const response = await apiGateway.get(API_ENDPOINTS.SETTINGS.INDEX, {
                headers: {
                    'Accept': format === 'json' ? 'application/json' : 'text/plain'
                },
                params: {
                    action: 'export',
                    format,
                    sections: sections.join(',')
                }
            });
            
            this.notifyListeners('settings_exported', { format, sections, data: response });
            return response;
        } catch (error) {
            this.handleSettingsError(error, 'Failed to export settings');
            throw error;
        }
    }

    /**
     * Validate general settings
     * @param {Object} data 
     */
    validateGeneralSettings(data) {
        const required = ['plugin_name', 'parent_url'];
        const missing = required.filter(field => !data[field]);
        
        if (missing.length > 0) {
            throw new ValidationError('general_settings', `Missing required fields: ${missing.join(', ')}`);
        }

        // Validate parent URL
        if (data.parent_url && !this.isValidUrl(data.parent_url)) {
            throw new ValidationError('parent_url', 'Invalid parent URL format');
        }

        // Validate email if provided
        if (data.admin_email && !this.isValidEmail(data.admin_email)) {
            throw new ValidationError('admin_email', 'Invalid email format');
        }
    }

    /**
     * Validate white label settings
     * @param {Object} data 
     */
    validateWhiteLabelSettings(data) {
        // Validate URLs if provided
        const urlFields = ['logo_url', 'website_url', 'support_url'];
        urlFields.forEach(field => {
            if (data[field] && !this.isValidUrl(data[field])) {
                throw new ValidationError(field, `Invalid ${field} format`);
            }
        });

        // Validate colors if provided
        const colorFields = ['primary_color', 'secondary_color', 'accent_color'];
        colorFields.forEach(field => {
            if (data[field] && !this.isValidColor(data[field])) {
                throw new ValidationError(field, `Invalid ${field} format`);
            }
        });
    }

    /**
     * Validate URL format
     * @param {string} url 
     * @returns {boolean}
     */
    isValidUrl(url) {
        try {
            new URL(url);
            return true;
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
     * Validate color format (hex, rgb, rgba, color names)
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

        // Test color names (basic set)
        const colorNames = ['red', 'blue', 'green', 'yellow', 'purple', 'orange', 'pink', 'brown', 'black', 'white', 'gray', 'grey'];
        return colorNames.includes(color.toLowerCase());
    }

    /**
     * Update internal settings state
     * @param {Object} data 
     */
    updateSettingsState(data) {
        this.settings = data;
        
        if (data.general) {
            this.generalSettings = data.general;
        }
        
        if (data.white_label) {
            this.whiteLabelSettings = data.white_label;
        }

        this.notifyListeners('settings_state_updated', data);
    }

    /**
     * Handle settings errors
     * @param {Error} error 
     * @param {string} context 
     */
    handleSettingsError(error, context) {
        const errorInfo = {
            error,
            context,
            timestamp: new Date().toISOString(),
            status: error.status || 'unknown',
            message: error.message || 'Unknown error'
        };

        this.notifyListeners('settings_error', errorInfo);
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
     * Get settings summary
     * @returns {Object}
     */
    getSettingsSummary() {
        return {
            hasSettings: Boolean(this.settings),
            hasGeneralSettings: Boolean(this.generalSettings),
            hasWhiteLabelSettings: Boolean(this.whiteLabelSettings),
            isConfigured: this.isConfigured(),
            isWhiteLabelEnabled: this.isWhiteLabelEnabled()
        };
    }

    /**
     * Check if plugin is configured
     * @returns {boolean}
     */
    isConfigured() {
        return Boolean(
            this.generalSettings?.parent_url && 
            this.generalSettings?.plugin_name
        );
    }

    /**
     * Check if white label is enabled
     * @returns {boolean}
     */
    isWhiteLabelEnabled() {
        return Boolean(this.whiteLabelSettings?.enabled);
    }

    /**
     * Cleanup service
     */
    destroy() {
        this.listeners = [];
        this.settings = null;
        this.generalSettings = null;
        this.whiteLabelSettings = null;
    }
}

// Create wrapped methods with error handling
const settingsService = new SettingsService();

// Export wrapped methods
export const {
    getSettings,
    updateSettings,
    getGeneralSettings,
    updateGeneralSettings,
    getWhiteLabelSettings,
    updateWhiteLabelSettings,
    resetSettings,
    importSettings,
    exportSettings
} = Object.fromEntries(
    ['getSettings', 'updateSettings', 'getGeneralSettings', 'updateGeneralSettings', 
     'getWhiteLabelSettings', 'updateWhiteLabelSettings', 'resetSettings', 
     'importSettings', 'exportSettings']
        .map(method => [
            method, 
            withErrorHandling(
                settingsService[method].bind(settingsService),
                { service: 'settings', method }
            )
        ])
);

// Export service instance
export { settingsService };
export default settingsService;