import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useVerification } from '../hooks/index.js';
import Connected from '../components/Connected';
import NotConnected from '../components/NotConnected';
import ConnectionFailed from '../components/ConnectionFailed';
import UnverifiedState from '../components/UnverifiedState';

const ConnectionView = () => {
    const [apiVerificationStatus, setApiVerificationStatus] = useState(null);
    const [verificationResult, setVerificationResult] = useState(null);
    const [isCheckingStatus, setIsCheckingStatus] = useState(true);

    // Use the simplified verification hook
    const { verifyConnection, isLoading, error } = useVerification();

    const connectionData = window.sureFeedbackAdmin?.connection;

    // Get connection status from database options
    const siteConnected = connectionData?.site_connected || false;
    const isFullyVerified = connectionData?.is_fully_verified || 0;
    const connectionStatus = connectionData?.connection_status || 'not_connected';

    // Always check API status on component mount
    useEffect(() => {
        checkApiVerificationStatus();
    }, []);

    const checkApiVerificationStatus = async () => {
        // Only check API if we have a connection
        if (connectionStatus !== 'connected' && connectionStatus !== 'not_verified') {
            setIsCheckingStatus(false);
            return;
        }

        try {
            setIsCheckingStatus(true);

            // Use the simplified verification hook that calls apiGateway.post with site_token
            const result = await verifyConnection();

            // Always set the verification result regardless of success/failure
            setVerificationResult(result);
            setApiVerificationStatus(result.status);

        } catch (error) {
            const errorResult = { status: 'failed', message: error.message };
            setApiVerificationStatus('failed');
            setVerificationResult(errorResult);
        } finally {
            setIsCheckingStatus(false);
        }
    };

    const renderConnectionStatus = () => {
        // Show loading state while checking API
        if (isCheckingStatus && (connectionStatus === 'connected' || connectionStatus === 'not_verified')) {
            return <UnverifiedState showLoading={true} onRetryVerification={checkApiVerificationStatus} />;
        }

        // Use database connection status to render appropriate component
        // Connection status mapping:
        // site_connected = 0 → 'not_connected' → NotConnected component
        // site_connected = 1 AND is_fully_verified = 0 → 'not_verified' → UnverifiedState component
        // site_connected = 1 AND is_fully_verified = 1 → 'connected' → Connected component
        // site_connected = 1 AND is_fully_verified = 2 → 'not_verified' → UnverifiedState component

        if (connectionStatus === 'connected') {
            // site_connected = 1 AND is_fully_verified = 1
            return <Connected connectionData={connectionData} verificationResult={verificationResult} />;
        } else if (connectionStatus === 'not_verified') {
            // site_connected = 1 AND is_fully_verified = 0 OR 2
            return <UnverifiedState
                onRetryVerification={checkApiVerificationStatus}
                verificationResult={verificationResult}
            />;
        } else {
            // site_connected = 0 (not_connected)
            return <NotConnected />;
        }
    };
    
    return (
        <div className="surefeedback-connection-view">
            {renderConnectionStatus()}
        </div>
    );
};

export default ConnectionView;