/**
 * SureFeedback Troubleshooting Component
 *
 * Displays debugging information and troubleshooting steps
 * when the API is not working properly.
 *
 * @package SureFeedback
 */

import React, { useState, useEffect } from 'react';
import { Wrench } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../components/ui/card';

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
        <Card className="shadow-sm">
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Wrench className="h-5 w-5 text-muted-foreground" />
                    <CardTitle className="text-lg font-semibold text-foreground">
                        SureFeedback Troubleshooting
                    </CardTitle>
                </div>
                <CardDescription className="text-sm text-muted-foreground">
                    The SureFeedback interface encountered an issue. Here's some diagnostic information:
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {error && (
                    <Card className="bg-red-50 border-red-200">
                        <CardContent className="p-4 space-y-2">
                            <h4 className="text-sm font-medium text-red-800">Error Details:</h4>
                            <div className="text-sm text-red-700 space-y-1">
                                <p><strong>Status:</strong> {error.status || 'Unknown'}</p>
                                <p><strong>Message:</strong> {error.message || 'No message available'}</p>
                                {error.context && <p><strong>Context:</strong> {error.context}</p>}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div>
                    <h4 className="text-sm font-medium text-foreground mb-3">System Information:</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">WordPress Nonce:</span>
                            <span className={systemInfo.wpNonce === 'Present' ? 'text-green-600' : 'text-red-600'}>
                                {systemInfo.wpNonce}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Admin Nonce:</span>
                            <span className={systemInfo.adminNonce === 'Present' ? 'text-green-600' : 'text-red-600'}>
                                {systemInfo.adminNonce}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">API Status:</span>
                            <span className={getStatusColor()}>
                                {apiStatus}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Logged In:</span>
                            <span className={systemInfo.isLoggedIn === 'Yes' ? 'text-green-600' : 'text-yellow-600'}>
                                {systemInfo.isLoggedIn}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 className="text-sm font-medium text-foreground mb-3">Troubleshooting Steps:</h4>
                    <ol className="list-decimal list-inside text-sm text-muted-foreground space-y-1">
                        <li>Refresh the page to reload authentication tokens</li>
                        <li>Clear your browser cache and cookies</li>
                        <li>Check if you have administrator permissions</li>
                        <li>Ensure WordPress REST API is enabled</li>
                        <li>Check for plugin conflicts by deactivating other plugins</li>
                    </ol>
                </div>

                <div className="flex gap-3 flex-wrap">
                    <Button
                        onClick={() => window.location.reload()}
                        size="default"
                    >
                        Refresh Page
                    </Button>
                    {onRetry && (
                        <Button
                            onClick={onRetry}
                            variant="outline"
                            size="default"
                        >
                            Retry Connection
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
};

export default TroubleshootingPanel;