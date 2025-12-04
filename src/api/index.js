/**
 * API Services Index
 * 
 * Central export point for all API services.
 * Provides unified access to all SureFeedback API functionality.
 * 
 * @package SureFeedback
 */

// Core API infrastructure
export { default as apiGateway } from './gateway.js';
export { default as httpClient } from './client.js';

// Service exports
export { default as adminService } from '../services/admin.js';
export { default as connectionService } from '../services/connection.js';
export { default as dashboardService } from '../services/dashboard.js';
export { default as settingsService } from '../services/settings.js';
export { 
    verificationService,
    verifyConnection as verifyConnectionStatus
} from '../services/verification.js';

// Utility exports
export { default as errorHandler } from '../utils/errors.js';
export { default as cacheManager } from '../utils/cache.js';
export { default as authManager } from '../utils/auth.js';

/**
 * API Services Registry
 * Provides centralized access to all services
 */
export const apiServices = {
    admin: adminService,
    connection: connectionService,
    dashboard: dashboardService,
    settings: settingsService,
    verification: verificationService
};

/**
 * Initialize all API services
 */
export const initializeServices = () => {
    // Initialize each service
    adminService.init?.();
    connectionService.init?.();
    dashboardService.init?.();
    settingsService.init?.();
    verificationService.init?.();
};

/**
 * Verification API convenience methods
 */
export const verification = {
    verify: verifyConnectionStatus      // Laravel API - Direct verification
};

/**
 * Health check for all services
 */
export const healthCheck = async () => {
    const results = {};
    
    try {
        // Check each service
        results.connection = await connectionService.getStatus().catch(e => ({ error: e.message }));
        results.verification = await verificationService.getStatus().catch(e => ({ error: e.message }));
        results.settings = await settingsService.getSettings().catch(e => ({ error: e.message }));
        
        return {
            healthy: Object.values(results).every(r => !r.error),
            results,
            timestamp: new Date().toISOString()
        };
    } catch (error) {
        return {
            healthy: false,
            error: error.message,
            timestamp: new Date().toISOString()
        };
    }
};

// Auto-initialize services when module is loaded
if (typeof window !== 'undefined' && window.sureFeedbackAdmin) {
    initializeServices();
}