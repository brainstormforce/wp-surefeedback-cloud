/**
 * SureFeedback Pre-init Script
 * 
 * This script runs before the main React application to ensure
 * WordPress API settings are properly initialized.
 * 
 * @package SureFeedback
 */

(function() {
    'use strict';
    
    // Ensure wpApiSettings exists
    if (!window.wpApiSettings && window.sureFeedbackAdmin) {
        console.log('SureFeedback: Initializing wpApiSettings from sureFeedbackAdmin');
        
        window.wpApiSettings = {
            root: window.sureFeedbackAdmin.rest_url || (window.location.origin + '/wp-json/'),
            nonce: window.sureFeedbackAdmin.rest_nonce || window.sureFeedbackAdmin.nonce || ''
        };
    }
    
    // Debug logging
    console.log('SureFeedback: API Settings initialized', {
        wpApiSettings: window.wpApiSettings,
        sureFeedbackAdmin: window.sureFeedbackAdmin ? 'Available' : 'Missing'
    });
    
})();