/**
 * Authentication Utilities
 * 
 * Handles token management, authentication state,
 * and auth-related utilities.
 * 
 * @package SureFeedback
 */

import { CACHE_CONFIG, API_CONFIG } from '../constants/api.js';

/**
 * Token Manager class
 */
class TokenManager {
    constructor() {
        this.token = null;
        this.refreshTimer = null;
        this.listeners = [];
    }

    /**
     * Initialize token manager
     */
    init() {
        this.loadToken();
        this.setupAutoRefresh();
    }

    /**
     * Load token from WordPress API settings
     */
    loadToken() {
        // Get nonce from WordPress - prioritize SureFeedback admin object
        this.token = window.sureFeedbackAdmin?.rest_nonce ||
                    window.sureFeedbackAdmin?.nonce ||
                    window.wpApiSettings?.nonce || '';
        
        if (this.token) {
            this.notifyListeners('token_loaded', this.token);
        }
    }

    /**
     * Get current token
     * @returns {string}
     */
    getToken() {
        return this.token;
    }

    /**
     * Set new token
     * @param {string} token 
     */
    setToken(token) {
        this.token = token;
        this.notifyListeners('token_updated', token);
    }

    /**
     * Clear token
     */
    clearToken() {
        this.token = null;
        this.clearAutoRefresh();
        this.notifyListeners('token_cleared');
    }

    /**
     * Check if token exists
     * @returns {boolean}
     */
    hasToken() {
        return Boolean(this.token);
    }

    /**
     * Ensure valid token exists
     * @returns {Promise<string>}
     */
    async ensureValidToken() {
        if (!this.hasToken()) {
            await this.refreshToken();
        }
        return this.token;
    }

    /**
     * Refresh token
     * @returns {Promise<string>}
     */
    async refreshToken() {
        try {
            // In WordPress, we typically refresh the nonce
            const response = await fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'surefeedback_refresh_nonce',
                    _wpnonce: this.token
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.nonce) {
                    this.setToken(data.data.nonce);
                    return this.token;
                }
            }

            throw new Error('Failed to refresh token');
        } catch (error) {
            this.clearToken();
            throw error;
        }
    }

    /**
     * Setup automatic token refresh
     */
    setupAutoRefresh() {
        // Refresh token every 12 hours (WordPress nonces typically expire in 24 hours)
        const refreshInterval = 12 * 60 * 60 * 1000; // 12 hours
        
        this.refreshTimer = setInterval(() => {
            if (this.hasToken()) {
                this.refreshToken().catch(error => {
                    // Error handled silently
                });
            }
        }, refreshInterval);
    }

    /**
     * Clear automatic refresh
     */
    clearAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    /**
     * Add token event listener
     * @param {Function} callback 
     */
    addListener(callback) {
        this.listeners.push(callback);
    }

    /**
     * Remove token event listener
     * @param {Function} callback 
     */
    removeListener(callback) {
        const index = this.listeners.indexOf(callback);
        if (index > -1) {
            this.listeners.splice(index, 1);
        }
    }

    /**
     * Notify listeners of token events
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
}

/**
 * Authentication state manager
 */
class AuthManager {
    constructor() {
        this.isAuthenticated = false;
        this.user = null;
        this.capabilities = [];
        this.listeners = [];
        this.init();
    }

    /**
     * Initialize auth manager
     */
    init() {
        this.loadAuthState();
    }

    /**
     * Load authentication state
     */
    loadAuthState() {
        // Get current user info from WordPress
        const currentUser = window.wpUserData || window.currentUser;
        
        if (currentUser && currentUser.ID) {
            this.setAuthState(true, currentUser);
        } else {
            this.setAuthState(false, null);
        }
    }

    /**
     * Set authentication state
     * @param {boolean} isAuthenticated 
     * @param {Object} user 
     */
    setAuthState(isAuthenticated, user = null) {
        this.isAuthenticated = isAuthenticated;
        this.user = user;
        
        if (user && user.capabilities) {
            this.capabilities = Object.keys(user.capabilities).filter(cap => user.capabilities[cap]);
        } else {
            this.capabilities = [];
        }

        this.notifyListeners('auth_changed', {
            isAuthenticated: this.isAuthenticated,
            user: this.user,
            capabilities: this.capabilities
        });
    }

    /**
     * Check if user is authenticated
     * @returns {boolean}
     */
    isAuth() {
        return this.isAuthenticated;
    }

    /**
     * Get current user
     * @returns {Object|null}
     */
    getUser() {
        return this.user;
    }

    /**
     * Get user capabilities
     * @returns {Array}
     */
    getCapabilities() {
        return this.capabilities;
    }

    /**
     * Check if user has capability
     * @param {string} capability 
     * @returns {boolean}
     */
    hasCapability(capability) {
        return this.capabilities.includes(capability);
    }

    /**
     * Check if user can manage SureFeedback
     * @returns {boolean}
     */
    canManage() {
        return this.hasCapability('manage_options') || 
               this.hasCapability('surefeedback_manage');
    }

    /**
     * Check if user can configure settings
     * @returns {boolean}
     */
    canConfigureSettings() {
        return this.hasCapability('manage_options') || 
               this.hasCapability('surefeedback_configure');
    }

    /**
     * Check if user can view dashboard
     * @returns {boolean}
     */
    canViewDashboard() {
        return this.hasCapability('read') || 
               this.hasCapability('surefeedback_view');
    }

    /**
     * Add auth event listener
     * @param {Function} callback 
     */
    addListener(callback) {
        this.listeners.push(callback);
    }

    /**
     * Remove auth event listener
     * @param {Function} callback 
     */
    removeListener(callback) {
        const index = this.listeners.indexOf(callback);
        if (index > -1) {
            this.listeners.splice(index, 1);
        }
    }

    /**
     * Notify listeners of auth events
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
     * Logout user
     */
    logout() {
        window.location.href = window.wpLogoutUrl || '/wp-login.php?action=logout';
    }
}

/**
 * Authentication utilities
 */
export const authUtils = {
    /**
     * Check if current page is WordPress admin
     * @returns {boolean}
     */
    isAdminPage() {
        return window.location.pathname.includes('/wp-admin/');
    },

    /**
     * Check if current page is SureFeedback admin page
     * @returns {boolean}
     */
    isSureFeedbackPage() {
        const params = new URLSearchParams(window.location.search);
        return params.get('page')?.startsWith('surefeedback-cloud') || false;
    },

    /**
     * Get admin URL
     * @param {string} page 
     * @param {Object} params 
     * @returns {string}
     */
    getAdminUrl(page = '', params = {}) {
        const adminUrl = window.wpAdminUrl || '/wp-admin/';
        const queryParams = new URLSearchParams({
            page: `surefeedback-${page}`,
            ...params
        });
        
        return `${adminUrl}admin.php?${queryParams.toString()}`;
    },

    /**
     * Get login URL with redirect
     * @param {string} redirectTo 
     * @returns {string}
     */
    getLoginUrl(redirectTo = null) {
        const loginUrl = window.wpLoginUrl || '/wp-login.php';
        const redirect = redirectTo || window.location.href;
        
        return `${loginUrl}?redirect_to=${encodeURIComponent(redirect)}`;
    },

    /**
     * Redirect to login if not authenticated
     */
    requireAuth() {
        if (!authManager.isAuth()) {
            window.location.href = this.getLoginUrl();
        }
    },

    /**
     * Show unauthorized message
     */
    showUnauthorized() {
        // Implementation depends on your notification system
    }
};

// Create singleton instances
export const tokenManager = new TokenManager();
export const authManager = new AuthManager();

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        tokenManager.init();
    });
} else {
    tokenManager.init();
}

// Export for testing
export { TokenManager, AuthManager };