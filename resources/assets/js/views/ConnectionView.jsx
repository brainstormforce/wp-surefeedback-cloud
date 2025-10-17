import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useVerification } from '../hooks/index.js';
import Connected from '../components/Connected';
import NotConnected from '../components/NotConnected';
import ConnectionFailed from '../components/ConnectionFailed';
import UnverifiedState from '../components/UnverifiedState';
import ConnectedState from '../components/ConnectedState';

const ConnectionView = () => {
    const [apiVerificationStatus, setApiVerificationStatus] = useState(null);
    const [verificationResult, setVerificationResult] = useState(null);
    const [isCheckingStatus, setIsCheckingStatus] = useState(true);
    
    // Use the simplified verification hook
    const { verifyConnection, isLoading, error } = useVerification();
    
    const connectionData = window.sureFeedbackAdmin?.connection;
    const connectionStatus = window.sureFeedbackAdmin?.connection_status || 'not_connected';
    const dbVerificationStatus = window.sureFeedbackAdmin?.verification_status || 'unverified';
    
    // Always check API status on component mount
    useEffect(() => {
        checkApiVerificationStatus();
    }, []);

    const checkApiVerificationStatus = async () => {
        // Only check API if we have a connection
        if (connectionStatus !== 'connected') {
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
        if (isCheckingStatus && connectionStatus === 'connected') {
            return <UnverifiedState showLoading={true} />;
        }

        // Use API verification status if available, otherwise fall back to DB status
        const verificationStatus = apiVerificationStatus || dbVerificationStatus;
        
        // Check verification status first
        if (verificationStatus === 'verified' && connectionStatus === 'connected') {
            // Fully verified and connected - show success state
            return <ConnectedState connectionData={connectionData} verificationResult={verificationResult} />;
        } else if (connectionStatus === 'connected' || verificationStatus === 'pending') {
            // Connected but not fully verified, or verification pending
            return <UnverifiedState 
                onRetryVerification={checkApiVerificationStatus} 
                verificationResult={verificationResult}
            />;
        } else if (connectionStatus === 'failed' || verificationStatus === 'failed') {
            // Connection or verification failed
            return <ConnectionFailed verificationResult={verificationResult} />;
        } else {
            // Not connected at all
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