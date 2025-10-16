/**
 * SureFeedback Troubleshooting Component
 * 
 * Displays debugging information and troubleshooting steps
 * when the API is not working properly.
 * 
 * @package SureFeedback
 */

import React, { useState, useEffect } from 'react';

const TroubleshootingPanel = ({ error, onRetry }) => {
    const [systemInfo, setSystemInfo] = useState({});
    const [apiStatus, setApiStatus] = useState('unknown');

    useEffect(() => {
        checkSystemInfo();
        testApiConnection();
    }, []);

    const checkSystemInfo = () => {
        const info = {
            userAgent: navigator.userAgent,
            url: window.location.href,
            wpNonce: window.wpApiSettings?.nonce ? 'Present' : 'Missing',
            adminNonce: window.sureFeedbackAdmin?.nonce ? 'Present' : 'Missing',
            apiBase: window.wpApiSettings?.root || 'Not available',
            isLoggedIn: window.sureFeedbackAdmin?.currentUser?.ID ? 'Yes' : 'Unknown'
        };
        setSystemInfo(info);
    };

    const testApiConnection = async () => {
        try {
            const testUrl = `${window.wpApiSettings?.root || '/wp-json/'}wp/v2/users/me`;
            const response = await fetch(testUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': window.wpApiSettings?.nonce || ''
                }
            });
            
            setApiStatus(response.ok ? 'connected' : `error-${response.status}`);
        } catch (err) {
            setApiStatus('failed');
        }
    };

    const getStatusColor = () => {
        switch (apiStatus) {
            case 'connected': return 'text-green-600';
            case 'unknown': return 'text-gray-600';
            default: return 'text-red-600';
        }
    };

    return (
        <div className="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div className="mb-4">
                <h3 className="text-lg font-semibold text-gray-900 mb-2">
                    🔧 SureFeedback Troubleshooting
                </h3>
                <p className="text-sm text-gray-600">
                    The SureFeedback interface encountered an issue. Here's some diagnostic information:
                </p>
            </div>

            {error && (
                <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <h4 className="text-sm font-medium text-red-800 mb-2">Error Details:</h4>
                    <div className="text-sm text-red-700">
                        <p><strong>Status:</strong> {error.status || 'Unknown'}</p>
                        <p><strong>Message:</strong> {error.message || 'No message available'}</p>
                        {error.context && <p><strong>Context:</strong> {error.context}</p>}
                    </div>
                </div>
            )}

            <div className="mb-4">
                <h4 className="text-sm font-medium text-gray-900 mb-2">System Information:</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                    <div className="flex justify-between">
                        <span>WordPress Nonce:</span>
                        <span className={systemInfo.wpNonce === 'Present' ? 'text-green-600' : 'text-red-600'}>
                            {systemInfo.wpNonce}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span>Admin Nonce:</span>
                        <span className={systemInfo.adminNonce === 'Present' ? 'text-green-600' : 'text-red-600'}>
                            {systemInfo.adminNonce}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span>API Status:</span>
                        <span className={getStatusColor()}>
                            {apiStatus}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span>Logged In:</span>
                        <span className={systemInfo.isLoggedIn === 'Yes' ? 'text-green-600' : 'text-yellow-600'}>
                            {systemInfo.isLoggedIn}
                        </span>
                    </div>
                </div>
            </div>

            <div className="mb-4">
                <h4 className="text-sm font-medium text-gray-900 mb-2">Troubleshooting Steps:</h4>
                <ol className="list-decimal list-inside text-sm text-gray-700 space-y-1">
                    <li>Refresh the page to reload authentication tokens</li>
                    <li>Clear your browser cache and cookies</li>
                    <li>Check if you have administrator permissions</li>
                    <li>Ensure WordPress REST API is enabled</li>
                    <li>Check for plugin conflicts by deactivating other plugins</li>
                </ol>
            </div>

            <div className="flex space-x-3">
                <button
                    onClick={() => window.location.reload()}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md"
                >
                    Refresh Page
                </button>
                {onRetry && (
                    <button
                        onClick={onRetry}
                        className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md"
                    >
                        Retry Connection
                    </button>
                )}
                <button
                    onClick={() => console.log('System Info:', systemInfo, 'Error:', error)}
                    className="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-md"
                >
                    Log Debug Info
                </button>
            </div>
        </div>
    );
};

export default TroubleshootingPanel;