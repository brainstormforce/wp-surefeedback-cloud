/**
 * SureFeedback API Integration Helper
 * 
 * Modern API integration utilities for React components.
 * Provides context-based API access and component enhancement.
 * 
 * @package SureFeedback
 */

import { createContext, useContext, useEffect, useState } from 'react';
import {
    initializeServices,
    cleanupServices,
    apiServiceFactory,
    connectionService,
    settingsService,
    adminService,
    errorHandler
} from '../index.js';

/**
 * API Context for React components
 */
const ApiContext = createContext(null);

/**
 * API Provider component
 * Provides API services to React component tree
 */
export function ApiProvider({ children }) {
    const [isInitialized, setIsInitialized] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        // Initialize API services
        try {
            apiServiceFactory.init();
            setIsInitialized(true);
        } catch (err) {
            setError(err);
        }

        // Cleanup on unmount
        return () => {
            cleanupServices();
        };
    }, []);

    const contextValue = {
        isInitialized,
        error,
        services: {
            connection: connectionService,
            settings: settingsService,
            admin: adminService
        },
        errorHandler
    };

    return (
        <ApiContext.Provider value={contextValue}>
            {children}
        </ApiContext.Provider>
    );
}

/**
 * Hook to use API context
 */
export function useApi() {
    const context = useContext(ApiContext);
    if (!context) {
        throw new Error('useApi must be used within an ApiProvider');
    }
    return context;
}

/**
 * Higher-order component to inject API services into existing components
 */
export function withApiServices(WrappedComponent) {
    return function WithApiServicesComponent(props) {
        const api = useApi();
        return <WrappedComponent {...props} api={api} />;
    };
}

/**
 * Global API object for window access
 */
export function attachToWindow() {
    if (typeof window !== 'undefined') {
        window.SureFeedbackApi = {
            // API Services
            services: {
                connection: connectionService,
                settings: settingsService,
                admin: adminService
            },
            
            // Utilities
            errorHandler,
            factory: apiServiceFactory,
            
            // Initialize function
            init: () => apiServiceFactory.init(),
            
            // Cleanup function
            cleanup: cleanupServices
        };
    }
}

/**
 * Integration utilities for modern components
 */
export const integrationUtils = {
    /**
     * Wrap component methods with API calls
     * @param {Object} component - React component instance
     * @param {Object} mappings - Method to API call mappings
     */
    wrapComponentMethods(component, mappings) {
        Object.entries(mappings).forEach(([methodName, apiCall]) => {
            const originalMethod = component[methodName];
            
            component[methodName] = async function(...args) {
                try {
                    // Call original method if it exists
                    if (originalMethod) {
                        await originalMethod.apply(this, args);
                    }
                    
                    // Call API
                    const result = await apiCall(...args);
                    
                    // Update component state if setState exists
                    if (this.setState) {
                        this.setState({ lastApiResult: result });
                    }
                    
                    return result;
                } catch (error) {
                    if (this.setState) {
                        this.setState({ lastApiError: error });
                    }
                    throw error;
                }
            };
        });
    },

    /**
     * Create API call wrapper for class components
     * @param {string} serviceName - Service name
     * @param {string} methodName - Method name
     * @returns {Function} Wrapped method
     */
    createApiWrapper(serviceName, methodName) {
        return async function(...args) {
            await apiServiceFactory.init();
            const service = apiServiceFactory.getService(serviceName);
            
            if (!service || !service[methodName]) {
                throw new Error(`API method ${serviceName}.${methodName} not found`);
            }
            
            return service[methodName](...args);
        };
    }
};

// Auto-attach to window in browser environment
if (typeof window !== 'undefined') {
    attachToWindow();
}

export default {
    ApiProvider,
    useApi,
    withApiServices,
    integrationUtils,
    attachToWindow
};