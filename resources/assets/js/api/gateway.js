/**
 * API Gateway
 * 
 * Centralized HTTP client for all API communications.
 * Handles authentication, error handling, and request/response transformation.
 * 
 * @package SureFeedback
 */

import { API_CONFIG } from '../constants/api.js';
import { ApiError } from '../utils/errors.js';
import { tokenManager } from '../utils/auth.js';

class ApiGateway {
    constructor() {
        this.baseURL = API_CONFIG.BASE_URL;
        this.timeout = API_CONFIG.TIMEOUT;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };
        
        // Initialize authentication
        this.initAuth();
    }

    /**
     * Set authentication token for requests
     * @param {string} token 
     */
    setAuthToken(token) {
        if (token) {
            // Set the nonce header for WordPress REST API
            this.defaultHeaders['X-WP-Nonce'] = token;
            
            // Also set authorization header for good measure
            this.defaultHeaders['Authorization'] = `Bearer ${token}`;
        } else {
            delete this.defaultHeaders['Authorization'];
            delete this.defaultHeaders['X-WP-Nonce'];
        }
    }

    /**
     * Initialize authentication from WordPress
     */
    initAuth() {
        // Try multiple sources for the nonce
        const nonce = window.wpApiSettings?.nonce ||
                     window.sureFeedbackAdmin?.nonce ||
                     window.sureFeedbackAdmin?.rest_nonce || '';

        console.log('SureFeedback: Initializing auth with nonce:', nonce ? 'Found' : 'Not found');
        console.log('Available nonce sources:', {
            wpApiSettings: window.wpApiSettings?.nonce ? 'Available' : 'Missing',
            adminNonce: window.sureFeedbackAdmin?.nonce ? 'Available' : 'Missing',
            restNonce: window.sureFeedbackAdmin?.rest_nonce ? 'Available' : 'Missing'
        });
        console.log('Dev server mode:', this.isDevServer());

        if (nonce) {
            this.setAuthToken(nonce);
        } else {
            console.warn('SureFeedback: No valid nonce found for API authentication');
        }
    }

    /**
     * Check if running from dev server (different origin)
     * @returns {boolean}
     */
    isDevServer() {
        // Check if current origin is different from API base URL origin
        try {
            const currentOrigin = window.location.origin;
            const apiOrigin = new URL(this.baseURL).origin;
            return currentOrigin !== apiOrigin;
        } catch (e) {
            return false;
        }
    }

    /**
     * Build complete URL
     * @param {string} endpoint 
     * @returns {string}
     */
    buildUrl(endpoint) {
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
        return `${this.baseURL}/${cleanEndpoint}`;
    }

    /**
     * Build request options
     * @param {string} method
     * @param {Object} options
     * @returns {Object}
     */
    buildRequestOptions(method, options = {}) {
        const { headers = {}, body, ...restOptions } = options;

        // Use 'include' for cross-origin requests (dev server), 'same-origin' otherwise
        const credentials = this.isDevServer() ? 'include' : 'same-origin';

        const requestOptions = {
            method: method.toUpperCase(),
            headers: {
                ...this.defaultHeaders,
                ...headers,
            },
            credentials: credentials,
            ...restOptions,
        };

        // Add body for methods that support it
        if (body && ['POST', 'PUT', 'PATCH'].includes(requestOptions.method)) {
            if (body instanceof FormData) {
                // Remove Content-Type header for FormData (browser sets it automatically)
                delete requestOptions.headers['Content-Type'];
                requestOptions.body = body;
            } else {
                requestOptions.body = JSON.stringify(body);
            }
        }

        return requestOptions;
    }

    /**
     * Handle response
     * @param {Response} response 
     * @returns {Promise<any>}
     */
    async handleResponse(response) {
        const contentType = response.headers.get('content-type');
        let data;

        try {
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }
        } catch (error) {
            throw new ApiError('Failed to parse response', response.status, response);
        }

        if (!response.ok) {
            // Handle WordPress REST API errors
            if (data && data.code && data.message) {
                throw new ApiError(data.message, response.status, response, data.code, data.data);
            }
            
            // Handle generic errors
            const message = data.message || data || `HTTP ${response.status}: ${response.statusText}`;
            throw new ApiError(message, response.status, response);
        }

        return data;
    }

    /**
     * Make HTTP request
     * @param {string} method 
     * @param {string} endpoint 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async request(method, endpoint, options = {}) {
        const url = this.buildUrl(endpoint);
        const requestOptions = this.buildRequestOptions(method, options);

        try {
            // Auto-refresh token if needed
            await tokenManager.ensureValidToken();

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);

            const response = await fetch(url, {
                ...requestOptions,
                signal: controller.signal,
            });

            clearTimeout(timeoutId);
            return await this.handleResponse(response);

        } catch (error) {
            if (error.name === 'AbortError') {
                throw new ApiError('Request timeout', 408);
            }

            // Re-throw ApiError instances
            if (error instanceof ApiError) {
                throw error;
            }

            // Handle network errors
            throw new ApiError('Network error: ' + error.message, 0, null, 'network_error');
        }
    }

    // HTTP Method shortcuts

    /**
     * GET request
     * @param {string} endpoint 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async get(endpoint, options = {}) {
        return this.request('GET', endpoint, options);
    }

    /**
     * POST request
     * @param {string} endpoint 
     * @param {any} body 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async post(endpoint, body = null, options = {}) {
        return this.request('POST', endpoint, { ...options, body });
    }

    /**
     * PUT request
     * @param {string} endpoint 
     * @param {any} body 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async put(endpoint, body = null, options = {}) {
        return this.request('PUT', endpoint, { ...options, body });
    }

    /**
     * PATCH request
     * @param {string} endpoint 
     * @param {any} body 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async patch(endpoint, body = null, options = {}) {
        return this.request('PATCH', endpoint, { ...options, body });
    }

    /**
     * DELETE request
     * @param {string} endpoint 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async delete(endpoint, options = {}) {
        return this.request('DELETE', endpoint, options);
    }

    /**
     * Upload file
     * @param {string} endpoint 
     * @param {FormData} formData 
     * @param {Object} options 
     * @returns {Promise<any>}
     */
    async upload(endpoint, formData, options = {}) {
        return this.request('POST', endpoint, { 
            ...options, 
            body: formData 
        });
    }

    /**
     * Batch requests
     * @param {Array} requests 
     * @returns {Promise<Array>}
     */
    async batch(requests) {
        const promises = requests.map(({ method, endpoint, body, options }) => 
            this.request(method, endpoint, { ...options, body })
        );

        return Promise.allSettled(promises);
    }
}

export const apiGateway = new ApiGateway();
export { ApiGateway };