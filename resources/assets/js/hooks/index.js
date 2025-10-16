/**
 * React Hooks for SureFeedback API
 * 
 * Custom React hooks that provide easy integration with
 * SureFeedback API services and state management.
 * 
 * @package SureFeedback
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { 
    connectionService, 
    settingsService, 
    dashboardService, 
    adminService,
    errorHandler 
} from '../index.js';

/**
 * Hook for connection state management
 * @param {Object} options - Hook options
 * @returns {Object} Connection state and methods
 */
export function useConnection(options = {}) {
    const { autoRefresh = true, refreshInterval = 30000 } = options;
    
    const [state, setState] = useState({
        isConnected: false,
        connectionData: null,
        isLoading: true,
        error: null,
        lastUpdated: null
    });

    const updateState = useCallback((updates) => {
        setState(prevState => ({
            ...prevState,
            ...updates,
            lastUpdated: new Date().toISOString()
        }));
    }, []);

    const getStatus = useCallback(async (forceRefresh = false) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const data = await connectionService.getStatus(forceRefresh);
            updateState({
                isConnected: data.connected,
                connectionData: data,
                isLoading: false
            });
            return data;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const connect = useCallback(async (connectionData) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await connectionService.connect(connectionData);
            updateState({
                isConnected: true,
                connectionData: result,
                isLoading: false
            });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const disconnect = useCallback(async () => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await connectionService.disconnect();
            updateState({
                isConnected: false,
                connectionData: null,
                isLoading: false
            });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const verify = useCallback(async (parentUrl, accessToken) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await connectionService.verify(parentUrl, accessToken);
            updateState({ isLoading: false });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    // Setup connection listener
    useEffect(() => {
        const listener = (event, data) => {
            switch (event) {
                case 'status_updated':
                    updateState({
                        isConnected: data.connected,
                        connectionData: data
                    });
                    break;
                case 'connection_established':
                    updateState({
                        isConnected: true,
                        connectionData: data
                    });
                    break;
                case 'connection_disconnected':
                    updateState({
                        isConnected: false,
                        connectionData: null
                    });
                    break;
            }
        };

        connectionService.addListener(listener);
        
        // Initial load
        getStatus();

        return () => connectionService.removeListener(listener);
    }, [getStatus, updateState]);

    // Auto refresh
    useEffect(() => {
        if (!autoRefresh || !state.isConnected) {
            return;
        }

        const interval = setInterval(() => {
            getStatus(true).catch(() => {});
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, state.isConnected, getStatus]);

    return {
        ...state,
        getStatus,
        connect,
        disconnect,
        verify,
        refresh: () => getStatus(true)
    };
}

/**
 * Hook for settings management
 * @param {string} section - Settings section ('general', 'white_label', or 'all')
 * @returns {Object} Settings state and methods
 */
export function useSettings(section = 'all') {
    const [state, setState] = useState({
        settings: null,
        isLoading: true,
        error: null,
        lastUpdated: null
    });

    const updateState = useCallback((updates) => {
        setState(prevState => ({
            ...prevState,
            ...updates,
            lastUpdated: new Date().toISOString()
        }));
    }, []);

    const getSettings = useCallback(async (forceRefresh = false) => {
        updateState({ isLoading: true, error: null });
        
        try {
            let data;
            
            switch (section) {
                case 'general':
                    data = await settingsService.getGeneralSettings(forceRefresh);
                    break;
                case 'white_label':
                    data = await settingsService.getWhiteLabelSettings(forceRefresh);
                    break;
                default:
                    data = await settingsService.getSettings(forceRefresh);
            }
            
            updateState({
                settings: data,
                isLoading: false
            });
            return data;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [section, updateState]);

    const updateSettings = useCallback(async (settingsData) => {
        updateState({ isLoading: true, error: null });
        
        try {
            let result;
            
            switch (section) {
                case 'general':
                    result = await settingsService.updateGeneralSettings(settingsData);
                    break;
                case 'white_label':
                    result = await settingsService.updateWhiteLabelSettings(settingsData);
                    break;
                default:
                    result = await settingsService.updateSettings(settingsData);
            }
            
            updateState({
                settings: result,
                isLoading: false
            });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [section, updateState]);

    const resetSettings = useCallback(async () => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await settingsService.resetSettings(section);
            updateState({
                settings: result,
                isLoading: false
            });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [section, updateState]);

    // Setup settings listener
    useEffect(() => {
        const listener = (event, data) => {
            switch (event) {
                case 'settings_updated':
                case 'general_settings_updated':
                case 'white_label_settings_updated':
                    if (section === 'all' || event.includes(section)) {
                        updateState({ settings: data });
                    }
                    break;
            }
        };

        settingsService.addListener(listener);
        
        // Initial load
        getSettings();

        return () => settingsService.removeListener(listener);
    }, [getSettings, section, updateState]);

    return {
        ...state,
        getSettings,
        updateSettings,
        resetSettings,
        refresh: () => getSettings(true)
    };
}

/**
 * Hook for dashboard data management
 * @param {Object} options - Hook options
 * @returns {Object} Dashboard state and methods
 */
export function useDashboard(options = {}) {
    const { autoRefresh = true, refreshInterval = 60000 } = options;
    
    const [state, setState] = useState({
        stats: null,
        quickAccess: null,
        recentActivity: null,
        isLoading: true,
        error: null,
        lastUpdated: null
    });

    const updateState = useCallback((updates) => {
        setState(prevState => ({
            ...prevState,
            ...updates,
            lastUpdated: new Date().toISOString()
        }));
    }, []);

    const refreshAll = useCallback(async () => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await dashboardService.refreshAll();
            updateState({
                stats: result.stats,
                quickAccess: result.quickAccess,
                recentActivity: result.recentActivity,
                isLoading: false,
                error: result.errors.length > 0 ? result.errors[0].error.message : null
            });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const getStats = useCallback(async (forceRefresh = false) => {
        try {
            const data = await dashboardService.getStats(forceRefresh);
            updateState({ stats: data });
            return data;
        } catch (error) {
            updateState({ error: error.message });
            throw error;
        }
    }, [updateState]);

    const getQuickAccess = useCallback(async (forceRefresh = false) => {
        try {
            const data = await dashboardService.getQuickAccess(forceRefresh);
            updateState({ quickAccess: data });
            return data;
        } catch (error) {
            updateState({ error: error.message });
            throw error;
        }
    }, [updateState]);

    const getRecentActivity = useCallback(async (options = {}) => {
        try {
            const data = await dashboardService.getRecentActivity(options);
            updateState({ recentActivity: data });
            return data;
        } catch (error) {
            updateState({ error: error.message });
            throw error;
        }
    }, [updateState]);

    // Setup dashboard listener
    useEffect(() => {
        const listener = (event, data) => {
            switch (event) {
                case 'stats_updated':
                    updateState({ stats: data });
                    break;
                case 'quick_access_updated':
                    updateState({ quickAccess: data });
                    break;
                case 'recent_activity_updated':
                    updateState({ recentActivity: data });
                    break;
                case 'refresh_completed':
                    updateState({
                        stats: data.stats,
                        quickAccess: data.quickAccess,
                        recentActivity: data.recentActivity,
                        error: data.errors.length > 0 ? data.errors[0].error.message : null
                    });
                    break;
            }
        };

        dashboardService.addListener(listener);
        
        // Initial load
        refreshAll();

        return () => dashboardService.removeListener(listener);
    }, [refreshAll, updateState]);

    // Auto refresh
    useEffect(() => {
        if (!autoRefresh) {
            return;
        }

        const interval = setInterval(() => {
            refreshAll().catch(() => {});
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, refreshAll]);

    return {
        ...state,
        refreshAll,
        getStats,
        getQuickAccess,
        getRecentActivity,
        refresh: refreshAll
    };
}

/**
 * Hook for admin operations
 * @returns {Object} Admin state and methods
 */
export function useAdmin() {
    const [state, setState] = useState({
        settings: null,
        systemInfo: null,
        isLoading: true,
        error: null,
        lastUpdated: null
    });

    const updateState = useCallback((updates) => {
        setState(prevState => ({
            ...prevState,
            ...updates,
            lastUpdated: new Date().toISOString()
        }));
    }, []);

    const getSettings = useCallback(async (forceRefresh = false) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const data = await adminService.getSettings(forceRefresh);
            updateState({
                settings: data,
                isLoading: false
            });
            return data;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const saveGeneralSettings = useCallback(async (data) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await adminService.saveGeneralSettings(data);
            updateState({ isLoading: false });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const saveWhiteLabelSettings = useCallback(async (data) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await adminService.saveWhiteLabelSettings(data);
            updateState({ isLoading: false });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const verifyIntegration = useCallback(async (data = {}) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await adminService.verifyIntegration(data);
            updateState({ isLoading: false });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const testParentSite = useCallback(async (url) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await adminService.testParentSite(url);
            updateState({ isLoading: false });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    const disconnectSite = useCallback(async (options = {}) => {
        updateState({ isLoading: true, error: null });
        
        try {
            const result = await adminService.disconnectSite(options);
            updateState({ isLoading: false });
            return result;
        } catch (error) {
            updateState({ 
                error: error.message, 
                isLoading: false 
            });
            throw error;
        }
    }, [updateState]);

    // Setup admin listener
    useEffect(() => {
        const listener = (event, data) => {
            switch (event) {
                case 'admin_settings_updated':
                    updateState({ settings: data });
                    break;
                case 'system_info_updated':
                    updateState({ systemInfo: data });
                    break;
            }
        };

        adminService.addListener(listener);
        
        // Initial load
        getSettings();

        return () => adminService.removeListener(listener);
    }, [getSettings, updateState]);

    return {
        ...state,
        getSettings,
        saveGeneralSettings,
        saveWhiteLabelSettings,
        verifyIntegration,
        testParentSite,
        disconnectSite,
        refresh: () => getSettings(true)
    };
}

/**
 * Hook for error handling
 * @returns {Object} Error state and methods
 */
export function useErrorHandler() {
    const [errors, setErrors] = useState([]);

    const addError = useCallback((error) => {
        const errorObj = {
            id: Date.now() + Math.random(),
            error,
            timestamp: new Date().toISOString()
        };
        
        setErrors(prev => [...prev, errorObj]);
    }, []);

    const removeError = useCallback((id) => {
        setErrors(prev => prev.filter(err => err.id !== id));
    }, []);

    const clearErrors = useCallback(() => {
        setErrors([]);
    }, []);

    // Setup global error listener
    useEffect(() => {
        const listener = (error, context) => {
            if (errorHandler.shouldReportToUser(error)) {
                addError(error);
            }
        };

        errorHandler.addListener(listener);
        
        return () => errorHandler.removeListener(listener);
    }, [addError]);

    return {
        errors,
        addError,
        removeError,
        clearErrors,
        hasErrors: errors.length > 0,
        latestError: errors[errors.length - 1] || null
    };
}

/**
 * Hook for API loading states
 * @returns {Object} Loading state utilities
 */
export function useApiLoading() {
    const [loadingStates, setLoadingStates] = useState({});

    const setLoading = useCallback((key, isLoading) => {
        setLoadingStates(prev => ({
            ...prev,
            [key]: isLoading
        }));
    }, []);

    const isLoading = useCallback((key) => {
        return Boolean(loadingStates[key]);
    }, [loadingStates]);

    const isAnyLoading = useMemo(() => {
        return Object.values(loadingStates).some(Boolean);
    }, [loadingStates]);

    return {
        loadingStates,
        setLoading,
        isLoading,
        isAnyLoading
    };
}