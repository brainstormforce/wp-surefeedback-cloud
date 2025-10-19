/**
 * Disconnect Service
 * 
 * Handles site disconnection from SureFeedback parent site.
 * Provides secure disconnection functionality with proper error handling
 * and authentication following WordPress REST API standards.
 * 
 * @package SureFeedback
 * @since 1.0.0
 */

import { apiGateway } from '../api/gateway.js';
import { ApiError, ValidationError, ConnectionError } from '../utils/errors.js';

// Constants
const ENDPOINTS = {
    DISCONNECT: '/disconnect/master-disconnect'
};

const ERROR_MESSAGES = {
    MISSING_TOKEN: 'Site token is required for disconnect. Please check your connection settings.',
    MISSING_DOMAIN: 'Site domain is required for disconnect. Please check your settings.',
    MISSING_NONCE: 'Authentication error: Missing security token. Please refresh the page and try again.',
    GENERIC_FAIL: 'Failed to disconnect from parent site. Please try again.',
    NETWORK_ERROR: 'Network error occurred during disconnect operation.'
};

const DISCONNECT_STATUS = {
    SUCCESS: 'disconnected',
    FAILED: 'failed',
    ERROR: 'error'
};

/**
 * Disconnect service class
 * Manages site disconnection operations with proper error handling
 */
class DisconnectService {
    constructor() {
        this.endpoints = ENDPOINTS;
        this.isInitialized = false;
        this.lastError = null;
    }

    /**
     * Initialize service
     * @returns {DisconnectService} Instance for chaining
     */
    init() {
        if (this.isInitialized) {
            return this;
        }

        this.isInitialized = true;
        this.lastError = null;
        
        return this;
    }

    /**
     * Validate admin data availability
     * @private
     * @throws {ValidationError} If admin data is not available
     */
    _validateAdminData() {
        if (!window.sureFeedbackAdmin) {
            throw new ValidationError('SureFeedback admin data not available. Please refresh the page.');
        }
    }

    /**
     * Get site token from WordPress admin data
     * @private
     * @returns {string} Site token
     * @throws {ValidationError} If token is not found
     */
    _getSiteToken() {
        this._validateAdminData();
        
        const token = window.sureFeedbackAdmin?.site_token || 
                     window.sureFeedbackAdmin?.connection?.site_token ||
                     window.sureFeedbackAdmin?.connection?.access_token;

        if (!token) {
            throw new ValidationError(ERROR_MESSAGES.MISSING_TOKEN);
        }

        return token;
    }

    /**
     * Get site domain for disconnect payload
     * @private
     * @returns {string} Site domain
     * @throws {ValidationError} If domain is not found
     */
    _getSiteDomain() {
        this._validateAdminData();
        
        const domain = window.sureFeedbackAdmin?.site_domain || 
                      window.sureFeedbackAdmin?.domain ||
                      window.location.hostname;

        if (!domain) {
            throw new ValidationError(ERROR_MESSAGES.MISSING_DOMAIN);
        }

        return domain;
    }

    /**
     * Get authentication nonce for WordPress REST API
     * @private
     * @returns {string} Authentication nonce
     * @throws {ValidationError} If nonce is not found
     */
    _getAuthNonce() {
        const nonce = window.sureFeedbackAdmin?.nonce || 
                     window.sureFeedbackAdmin?.rest_nonce || 
                     window.wpApiSettings?.nonce;

        if (!nonce) {
            throw new ValidationError(ERROR_MESSAGES.MISSING_NONCE);
        }

        return nonce;
    }

    /**
     * Prepare disconnect payload
     * @private
     * @param {Object} options - Additional disconnect options
     * @returns {Object} Disconnect payload
     */
    _preparePayload(options = {}) {
        return {
            site_token: this._getSiteToken(),
            domain: this._getSiteDomain(),
            timestamp: Date.now(),
            user_initiated: true,
            ...options
        };
    }

    /**
     * Process API response and normalize result
     * @private
     * @param {Object} response - API response
     * @returns {Object} Normalized disconnect result
     */
    _processResponse(response) {
        const success = response.success ?? (response.data && response.data.success) ?? false;
        const message = response.message || response.data?.message || '';
        const data = response.data || response;

        if (success) {
            return {
                success: true,
                status: DISCONNECT_STATUS.SUCCESS,
                message: message || 'Site disconnected successfully from parent site',
                data: data,
                timestamp: Date.now()
            };
        }

        return {
            success: false,
            status: DISCONNECT_STATUS.FAILED,
            message: message || ERROR_MESSAGES.GENERIC_FAIL,
            data: data,
            timestamp: Date.now()
        };
    }

    /**
     * Execute disconnect operation with authentication
     * @private
     * @param {Object} payload - Disconnect payload
     * @returns {Promise<Object>} API response
     */
    async _executeDisconnect(payload) {
        const nonce = this._getAuthNonce();
        
        // Backup current headers
        const originalHeaders = { ...apiGateway.defaultHeaders };
        
        try {
            // Set authentication for this request
            apiGateway.setAuthToken(nonce);

            // Execute disconnect request
            const response = await apiGateway.post(this.endpoints.DISCONNECT, payload);
            
            return response;
        } finally {
            // Always restore original headers
            apiGateway.defaultHeaders = originalHeaders;
        }
    }

    /**
     * Disconnect site from SureFeedback parent site
     * 
     * Performs secure disconnection by calling WordPress REST API endpoint
     * with proper authentication and error handling.
     * 
     * @param {Object} options - Disconnect options
     * @param {boolean} options.force - Force disconnect even if errors occur
     * @param {string} options.reason - Reason for disconnection (optional)
     * @param {Object} options.metadata - Additional metadata (optional)
     * @returns {Promise<Object>} Disconnect result with status and message
     * @throws {ValidationError} For validation errors
     * @throws {ConnectionError} For network/API errors
     * @throws {ApiError} For general API errors
     * 
     * @example
     * const result = await disconnectService.disconnect();
     * if (result.success) {
     *   console.log('Disconnected successfully:', result.message);
     * }
     */
    async disconnect(options = {}) {
        try {
            // Initialize if not already done
            this.init();

            // Prepare payload with validation
            const payload = this._preparePayload(options);

            // Execute disconnect request
            const response = await this._executeDisconnect(payload);

            // Process and return normalized result
            const result = this._processResponse(response);
            
            // Clear any previous errors on success
            if (result.success) {
                this.lastError = null;
            }

            return result;

        } catch (error) {
            // Store error for debugging
            this.lastError = error;

            // Handle different error types
            if (error instanceof ValidationError) {
                throw error;
            }

            if (error.name === 'TypeError' || error.message.includes('fetch')) {
                throw new ConnectionError(ERROR_MESSAGES.NETWORK_ERROR);
            }

            // Wrap unknown errors
            throw new ApiError(`Disconnect operation failed: ${error.message}`);
        }
    }

    /**
     * Get service health status
     * @returns {Object} Service status information
     */
    getStatus() {
        return {
            initialized: this.isInitialized,
            hasError: this.lastError !== null,
            lastError: this.lastError?.message || null,
            endpoints: this.endpoints
        };
    }

    /**
     * Reset service state
     * @returns {DisconnectService} Instance for chaining
     */
    reset() {
        this.lastError = null;
        return this;
    }

    /**
     * Cleanup service resources
     */
    destroy() {
        this.isInitialized = false;
        this.lastError = null;
    }
}

// Create singleton instance
const disconnectService = new DisconnectService();

/**
 * Disconnect site from parent (convenience function)
 * @param {Object} options - Disconnect options
 * @returns {Promise<Object>} Disconnect result
 */
export const disconnect = (options) => disconnectService.disconnect(options);

/**
 * Get disconnect service status
 * @returns {Object} Service status
 */
export const getDisconnectStatus = () => disconnectService.getStatus();

// Export service instance and class
export { disconnectService };
export default DisconnectService;