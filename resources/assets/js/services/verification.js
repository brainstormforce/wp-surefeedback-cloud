/**
 * Verification Service
 * @package SureFeedback
 */

import { apiGateway } from '../api/gateway.js';

// WordPress REST API endpoint constants
const VERIFICATION_ENDPOINT = '/verification/verify';

/**
 * Verification service class
 */
class VerificationService {
    constructor() {
        this.endpoints = {
            verify: VERIFICATION_ENDPOINT
        };
    }

    /**
     * Get site token from WordPress admin data
     * @returns {string} Site token
     */
    getSiteToken() {
        return window.sureFeedbackAdmin?.site_token || 
               window.sureFeedbackAdmin?.connection?.site_token ||
               window.sureFeedbackAdmin?.connection?.access_token;
    }

    /**
     * Get API URL for Laravel SureFeedback instance
     * @returns {string} API URL
     */
    getApiUrl() {
        return window.sureFeedbackAdmin?.connection?.app_url || 
               window.sureFeedbackAdmin?.app_url ||
               'http://localhost:8000';
    }

    /**
     * Verify connection with SureFeedback Laravel API
     * @param {Object} options - Verification options
     * @returns {Promise<Object>} Verification result
     */
    async verifyConnection(options = {}) {
        const siteToken = this.getSiteToken();
        
        if (!siteToken) {
            throw new Error('Site token is required for verification. Please check your connection settings.');
        }

        try {

            // Use API gateway to call WordPress REST API with only site token as parameter
            const data = await apiGateway.post(VERIFICATION_ENDPOINT, {
                site_token: siteToken
            });

            // Check for verification status from WordPress controller response first
            if (data.is_fully_verified === true || data.verification_status === 'verified') {
                return {
                    success: true,
                    status: 'verified',
                    message: data.message || 'Connection verified successfully - script is loaded and working',
                    data: data,
                    is_fully_verified: true,
                    verification_status: 'verified'
                };
            }

            // Handle the Laravel API response format
            // Check if script is integrated first
            if (data.data && data.data.integrated === true) {
                return {
                    success: true,
                    status: 'verified',
                    message: 'Connection verified successfully - script is loaded and working',
                    data: data,
                    is_fully_verified: true,
                    verification_status: 'verified'
                };
            } else if (data.data && data.data.integrated === false) {
                // Script token is valid but script not loaded
                const instructions = data.data.instructions || {};
                const status = data.data.status || 'SCRIPT_NOT_LOADED';
                return {
                    success: true,
                    status: 'pending',
                    message: data.data.error || `Script token valid but ${status.toLowerCase().replace('_', ' ')}`,
                    data: data,
                    instructions: {
                        message: instructions.message || 'To complete integration, add the SureFeedback script to your website',
                        script_url: instructions.script_url,
                        documentation: instructions.documentation
                    }
                };
            } else {
                // Handle cases where success is false or other scenarios
                const isTokenValid = data.data && data.data.site && data.data.site.id;
                
                if (isTokenValid && data.data.status === 'SCRIPT_NOT_LOADED') {
                    // Token valid but script not loaded - treat as pending
                    const instructions = data.data.instructions || {};
                    return {
                        success: true,
                        status: 'pending',
                        message: data.data.error || 'Script token valid but script not loaded',
                        data: data,
                        instructions: {
                            message: instructions.message || 'To complete integration, add the SureFeedback script to your website',
                            script_url: instructions.script_url,
                            documentation: instructions.documentation
                        }
                    };
                } else {
                    // Token invalid or other error
                    return {
                        success: false,
                        status: 'failed',
                        message: data.message || data.data?.error || 'Verification failed',
                        data: data
                    };
                }
            }
        } catch (error) {
            throw new Error(`Verification failed: ${error.message}`);
        }
    }
}

// Create singleton instance
const verificationService = new VerificationService();

// Export individual methods for convenience
export const verifyConnection = (options) => verificationService.verifyConnection(options);

// Export service instance and class
export { verificationService };
export default VerificationService;