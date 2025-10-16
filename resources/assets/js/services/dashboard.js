/**
 * Dashboard Service
 * 
 * Handles all dashboard-related API calls and data management.
 * Provides methods for getting statistics, quick access data, and recent activity.
 * 
 * @package SureFeedback
 */

import { apiGateway } from '../api/gateway.js';
import { API_ENDPOINTS, CACHE_CONFIG, UI_CONFIG } from '../constants/api.js';
import { withErrorHandling } from '../utils/errors.js';
import { cacheManager } from '../utils/cache.js';

/**
 * Dashboard Service class
 */
class DashboardService {
    constructor() {
        this.stats = null;
        this.quickAccess = null;
        this.recentActivity = null;
        this.listeners = [];
        this.refreshTimer = null;
        this.isRefreshing = false;
    }

    /**
     * Initialize dashboard service
     */
    init() {
        this.loadCachedData();
        this.startAutoRefresh();
    }

    /**
     * Load cached dashboard data
     */
    loadCachedData() {
        const cachedStats = cacheManager.get(CACHE_CONFIG.KEYS.DASHBOARD_STATS);
        if (cachedStats) {
            this.updateStatsState(cachedStats);
        }
    }

    /**
     * Get dashboard statistics
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getStats(forceRefresh = false) {
        const cacheKey = CACHE_CONFIG.KEYS.DASHBOARD_STATS;
        
        if (!forceRefresh) {
            const cached = cacheManager.get(cacheKey);
            if (cached) {
                return cached;
            }
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.DASHBOARD.STATS);
            
            // Update internal state
            this.updateStatsState(response);
            
            // Cache the response
            cacheManager.set(cacheKey, response, CACHE_CONFIG.DURATIONS.MEDIUM);
            
            return response;
        } catch (error) {
            this.handleDashboardError(error, 'Failed to get dashboard statistics');
            throw error;
        }
    }

    /**
     * Get quick access data
     * @param {boolean} forceRefresh - Force refresh from server
     * @returns {Promise<Object>}
     */
    async getQuickAccess(forceRefresh = false) {
        if (!forceRefresh && this.quickAccess) {
            return this.quickAccess;
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.DASHBOARD.QUICK_ACCESS);
            
            this.quickAccess = response;
            this.notifyListeners('quick_access_updated', response);
            
            return response;
        } catch (error) {
            this.handleDashboardError(error, 'Failed to get quick access data');
            throw error;
        }
    }

    /**
     * Get recent activity
     * @param {Object} options - Query options
     * @returns {Promise<Object>}
     */
    async getRecentActivity(options = {}) {
        const { limit = 10, offset = 0, forceRefresh = false } = options;

        if (!forceRefresh && this.recentActivity && limit === 10 && offset === 0) {
            return this.recentActivity;
        }

        try {
            const response = await apiGateway.get(API_ENDPOINTS.DASHBOARD.RECENT_ACTIVITY, {
                params: { limit, offset }
            });
            
            if (offset === 0) {
                this.recentActivity = response;
                this.notifyListeners('recent_activity_updated', response);
            }
            
            return response;
        } catch (error) {
            this.handleDashboardError(error, 'Failed to get recent activity');
            throw error;
        }
    }

    /**
     * Refresh all dashboard data
     * @returns {Promise<Object>}
     */
    async refreshAll() {
        if (this.isRefreshing) {
            return;
        }

        this.isRefreshing = true;
        this.notifyListeners('refresh_started');

        try {
            const [stats, quickAccess, recentActivity] = await Promise.allSettled([
                this.getStats(true),
                this.getQuickAccess(true),
                this.getRecentActivity({ forceRefresh: true })
            ]);

            const result = {
                stats: stats.status === 'fulfilled' ? stats.value : null,
                quickAccess: quickAccess.status === 'fulfilled' ? quickAccess.value : null,
                recentActivity: recentActivity.status === 'fulfilled' ? recentActivity.value : null,
                errors: []
            };

            // Collect any errors
            if (stats.status === 'rejected') result.errors.push({ type: 'stats', error: stats.reason });
            if (quickAccess.status === 'rejected') result.errors.push({ type: 'quickAccess', error: quickAccess.reason });
            if (recentActivity.status === 'rejected') result.errors.push({ type: 'recentActivity', error: recentActivity.reason });

            this.notifyListeners('refresh_completed', result);
            return result;

        } catch (error) {
            this.handleDashboardError(error, 'Failed to refresh dashboard');
            throw error;
        } finally {
            this.isRefreshing = false;
        }
    }

    /**
     * Get dashboard overview
     * @returns {Promise<Object>}
     */
    async getOverview() {
        try {
            const [stats, quickAccess] = await Promise.all([
                this.getStats(),
                this.getQuickAccess()
            ]);

            const overview = {
                ...stats,
                quick_access: quickAccess,
                last_updated: new Date().toISOString()
            };

            this.notifyListeners('overview_updated', overview);
            return overview;

        } catch (error) {
            this.handleDashboardError(error, 'Failed to get dashboard overview');
            throw error;
        }
    }

    /**
     * Get widget data by type
     * @param {string} widgetType - Type of widget
     * @param {Object} options - Widget options
     * @returns {Promise<Object>}
     */
    async getWidgetData(widgetType, options = {}) {
        try {
            let endpoint;
            let params = options;

            switch (widgetType) {
                case 'stats':
                case 'statistics':
                    return await this.getStats();
                    
                case 'quick_access':
                case 'quickAccess':
                    return await this.getQuickAccess();
                    
                case 'recent_activity':
                case 'recentActivity':
                    return await this.getRecentActivity(options);
                    
                case 'connection_status':
                    // This would call connection service
                    endpoint = API_ENDPOINTS.CONNECTION.STATUS;
                    break;
                    
                default:
                    throw new Error(`Unknown widget type: ${widgetType}`);
            }

            if (endpoint) {
                const response = await apiGateway.get(endpoint, { params });
                this.notifyListeners('widget_data_updated', { type: widgetType, data: response });
                return response;
            }

        } catch (error) {
            this.handleDashboardError(error, `Failed to get widget data: ${widgetType}`);
            throw error;
        }
    }

    /**
     * Start automatic dashboard refresh
     */
    startAutoRefresh() {
        // Refresh dashboard data every minute
        this.refreshTimer = setInterval(() => {
            if (!this.isRefreshing) {
                this.refreshAll().catch(error => {
                    // Error handled silently
                });
            }
        }, UI_CONFIG.POLLING.DASHBOARD_REFRESH);
    }

    /**
     * Stop automatic refresh
     */
    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    /**
     * Update stats state
     * @param {Object} data 
     */
    updateStatsState(data) {
        this.stats = data;
        this.notifyListeners('stats_updated', data);
    }

    /**
     * Handle dashboard errors
     * @param {Error} error 
     * @param {string} context 
     */
    handleDashboardError(error, context) {
        this.notifyListeners('dashboard_error', { error, context });
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
     * Get dashboard summary
     * @returns {Object}
     */
    getDashboardSummary() {
        return {
            hasStats: Boolean(this.stats),
            hasQuickAccess: Boolean(this.quickAccess),
            hasRecentActivity: Boolean(this.recentActivity),
            isRefreshing: this.isRefreshing,
            lastUpdate: this.stats?.last_updated || null,
            autoRefreshEnabled: Boolean(this.refreshTimer)
        };
    }

    /**
     * Get key metrics from stats
     * @returns {Object}
     */
    getKeyMetrics() {
        if (!this.stats) {
            return {};
        }

        return {
            totalComments: this.stats.total_comments || 0,
            activeProjects: this.stats.active_projects || 0,
            pendingReviews: this.stats.pending_reviews || 0,
            completedTasks: this.stats.completed_tasks || 0,
            connectionHealth: this.stats.connection_health || 0
        };
    }

    /**
     * Check if dashboard data is stale
     * @param {number} thresholdMinutes - Threshold in minutes
     * @returns {boolean}
     */
    isDataStale(thresholdMinutes = 5) {
        if (!this.stats?.last_updated) {
            return true;
        }

        const lastUpdate = new Date(this.stats.last_updated);
        const threshold = new Date(Date.now() - (thresholdMinutes * 60 * 1000));
        
        return lastUpdate < threshold;
    }

    /**
     * Force refresh if data is stale
     * @param {number} thresholdMinutes - Threshold in minutes
     * @returns {Promise<Object|null>}
     */
    async refreshIfStale(thresholdMinutes = 5) {
        if (this.isDataStale(thresholdMinutes)) {
            return await this.refreshAll();
        }
        return null;
    }

    /**
     * Cleanup service
     */
    destroy() {
        this.stopAutoRefresh();
        this.listeners = [];
        this.stats = null;
        this.quickAccess = null;
        this.recentActivity = null;
        this.isRefreshing = false;
    }
}

// Create wrapped methods with error handling
const dashboardService = new DashboardService();

// Export wrapped methods
export const {
    getStats: getDashboardStats,
    getQuickAccess: getDashboardQuickAccess,
    getRecentActivity: getDashboardRecentActivity,
    refreshAll: refreshDashboard,
    getOverview: getDashboardOverview,
    getWidgetData: getDashboardWidgetData
} = Object.fromEntries(
    ['getStats', 'getQuickAccess', 'getRecentActivity', 'refreshAll', 'getOverview', 'getWidgetData']
        .map(method => [
            method, 
            withErrorHandling(
                dashboardService[method].bind(dashboardService),
                { service: 'dashboard', method }
            )
        ])
);

// Export service instance
export { dashboardService };
export default dashboardService;