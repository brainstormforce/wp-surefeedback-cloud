/**
 * Connection Service
 * 
 * Handles all connection-related API calls and state management.
 * Provides methods for checking status, connecting, disconnecting, and health monitoring.
 * 
 * @package SureFeedback
 */

import { apiGateway } from '../api/gateway.js';
import { API_ENDPOINTS, CACHE_CONFIG } from '../constants/api.js';
import { ConnectionError, withErrorHandling } from '../utils/errors.js';
import { cacheManager } from '../utils/cache.js';

/**
 * Connection Service class
 */
class ConnectionService {
    constructor() {
        this.isConnected = false;
        this.connectionData = null;
        this.healthScore = 0;
        this.listeners = [];
        this.statusCheckTimer = null;
    }

    /**
     * Initialize connection service
     */
    init() {
        this.loadCachedStatus();
        this.startStatusMonitoring();
    }

    /**
     * Load cached connection status
     */
    loadCachedStatus() {
        const cached = cacheManager.get(CACHE_CONFIG.KEYS.CONNECTION_STATUS);
        if (cached) {
            this.updateConnectionState(cached);
        }
    }

    /**
     * Get connection status
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getStatus(forceRefresh = false) {
        const cacheKey = CACHE_CONFIG.KEYS.CONNECTION_STATUS;
        
        if (!forceRefresh) {
            const cached = cacheManager.get(cacheKey);
            if (cached) {
                return cached;
            }
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.CONNECTION.STATUS);
            
            // Update internal state
            this.updateConnectionState(response);
            
            // Cache the response
            cacheManager.set(cacheKey, response, CACHE_CONFIG.DURATIONS.MEDIUM);
            
            return response;
        } catch (error) {
            this.handleConnectionError(error, 'Failed to get connection status');
            throw error;
        }
    }

    /**
     * Verify connection with parent site
     * @param {string} parentUrl - Parent site URL
     * @param {string} accessToken - Access token
     * @returns {Promise<Object>}
     */
    async verify(parentUrl, accessToken) {
        if (!parentUrl || !accessToken) {
            throw new ConnectionError('Parent URL and access token are required');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.CONNECTION.VERIFY, {
                parent_url: parentUrl,
                access_token: accessToken
            });

            this.notifyListeners('verification_success', response);
            return response;
        } catch (error) {
            this.handleConnectionError(error, 'Connection verification failed');
            throw error;
        }
    }

    /**
     * Connect to parent site
     * @param {Object} connectionData - Connection configuration
     * @returns {Promise<Object>}
     */
    async connect(connectionData) {
        const { parentUrl, accessToken, signature } = connectionData;

        if (!parentUrl || !accessToken) {
            throw new ConnectionError('Parent URL and access token are required');
        }

        try {
            const response = await apiGateway.post(API_ENDPOINTS.CONNECTION.CONNECT, {
                parent_url: parentUrl,
                access_token: accessToken,
                signature: signature
            });

            // Update connection state
            this.updateConnectionState(response);
            
            // Clear cache to force refresh
            cacheManager.delete(CACHE_CONFIG.KEYS.CONNECTION_STATUS);
            
            this.notifyListeners('connection_established', response);
            return response;
        } catch (error) {
            this.handleConnectionError(error, 'Failed to establish connection');
            throw error;
        }
    }

    /**
     * Disconnect from parent site
     * @returns {Promise<Object>}
     */
    async disconnect() {
        try {
            const response = await apiGateway.post(API_ENDPOINTS.CONNECTION.DISCONNECT);

            // Update connection state
            this.updateConnectionState({
                connected: false,
                parent_url: '',
                access_token: false,
                status: 'disconnected'
            });

            // Clear cache
            cacheManager.delete(CACHE_CONFIG.KEYS.CONNECTION_STATUS);
            
            this.notifyListeners('connection_disconnected', response);
            return response;
        } catch (error) {
            this.handleConnectionError(error, 'Failed to disconnect');
            throw error;
        }
    }

    /**
     * Get connection health status
     * @returns {Promise<Object>}
     */
    async getHealth() {
        try {
            const response = await apiGateway.get(API_ENDPOINTS.CONNECTION.HEALTH);
            
            // Update health score
            this.healthScore = response.health_score || 0;
            
            this.notifyListeners('health_updated', response);
            return response;
        } catch (error) {
            this.handleConnectionError(error, 'Failed to get health status');
            throw error;
        }
    }

    /**
     * Test connection to parent site
     * @param {string} parentUrl - Parent site URL to test
     * @returns {Promise<Object>}
     */
    async testConnection(parentUrl) {
        if (!parentUrl) {
            throw new ConnectionError('Parent URL is required');
        }

        try {
            // Test basic connectivity
            const response = await apiGateway.post(API_ENDPOINTS.CONNECTION.VERIFY, {
                parent_url: parentUrl,
                test_only: true
            });

            return response;
        } catch (error) {
            this.handleConnectionError(error, 'Connection test failed');
            throw error;
        }
    }

    /**
     * Start automatic status monitoring
     */
    startStatusMonitoring() {
        // Check status every 30 seconds if connected
        this.statusCheckTimer = setInterval(async () => {
            if (this.isConnected) {
                try {
                    await this.getStatus(true);
                } catch (error) {
                    console.warn('Status check failed:', error);
                }
            }
        }, 30000);
    }

    /**
     * Stop status monitoring
     */
    stopStatusMonitoring() {
        if (this.statusCheckTimer) {
            clearInterval(this.statusCheckTimer);
            this.statusCheckTimer = null;
        }
    }

    /**
     * Update internal connection state
     * @param {Object} data - Connection data
     */
    updateConnectionState(data) {
        this.isConnected = data.connected || false;
        this.connectionData = data;
        this.healthScore = data.health_score || 0;

        this.notifyListeners('status_updated', data);
    }

    /**
     * Handle connection errors
     * @param {Error} error 
     * @param {string} context 
     */
    handleConnectionError(error, context) {
        const connectionError = new ConnectionError(
            `${context}: ${error.message}`,
            this.connectionData?.parent_url,
            error
        );

        this.notifyListeners('connection_error', connectionError);
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
                console.error('Error in connection listener:', error);
            }
        });
    }

    /**
     * Get connection status summary
     * @returns {Object}
     */
    getStatusSummary() {
        return {
            isConnected: this.isConnected,
            parentUrl: this.connectionData?.parent_url || '',
            hasToken: Boolean(this.connectionData?.access_token),
            status: this.connectionData?.status || 'unknown',
            healthScore: this.healthScore,
            lastCheck: this.connectionData?.last_check || null
        };
    }

    /**
     * Check if connection is healthy
     * @returns {boolean}
     */
    isHealthy() {
        return this.isConnected && this.healthScore > 70;
    }

    /**
     * Get connection issues
     * @returns {Array}
     */
    getIssues() {
        const issues = [];

        if (!this.isConnected) {
            issues.push({
                type: 'error',
                message: 'Not connected to parent site',
                action: 'connect'
            });
        }

        if (this.healthScore < 50) {
            issues.push({
                type: 'warning',
                message: 'Poor connection health',
                action: 'check_health'
            });
        }

        if (!this.connectionData?.access_token) {
            issues.push({
                type: 'error',
                message: 'Missing access token',
                action: 'reconnect'
            });
        }

        return issues;
    }

    /**
     * Cleanup service
     */
    destroy() {
        this.stopStatusMonitoring();
        this.listeners = [];
        this.connectionData = null;
        this.isConnected = false;
    }
}

// Create wrapped methods with error handling
const connectionService = new ConnectionService();

// Export wrapped methods
export const {
    getStatus: getConnectionStatus,
    verify: verifyConnection,
    connect: connectToParent,
    disconnect: disconnectFromParent,
    getHealth: getConnectionHealth,
    testConnection
} = Object.fromEntries(
    ['getStatus', 'verify', 'connect', 'disconnect', 'getHealth', 'testConnection']
        .map(method => [
            method, 
            withErrorHandling(
                connectionService[method].bind(connectionService),
                { service: 'connection', method }
            )
        ])
);

// Export service instance and utilities
export { connectionService };
export default connectionService;