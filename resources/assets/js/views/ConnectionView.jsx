import React from 'react';
import { __ } from '@wordpress/i18n';
import ConnectionCard from '../components/ConnectionCard';
import Connected from '../components/Connected';
import NotConnected from '../components/NotConnected';
import ConnectionFailed from '../components/ConnectionFailed';
import UnverifiedState from '../components/UnverifiedState';

const ConnectionView = () => {
    
    const connectionData = window.sureFeedbackAdmin?.connection;
    const isConnected = connectionData?.site_data?.site_url;
    const isVerified = connectionData?.verified;
    
    const getConnectionStatus = () => {
        if (!connectionData) return 'not-connected';
        if (connectionData.error) return 'failed';
        if (!isConnected) return 'not-connected';
        if (!isVerified) return 'unverified';
        return 'connected';
    };
    
    const connectionStatus = getConnectionStatus();
    
    const renderConnectionStatus = () => {
        switch (connectionStatus) {
            case 'connected':
                return <Connected connectionData={connectionData} />;
            case 'unverified':
                return <UnverifiedState connectionData={connectionData} />;
            case 'failed':
                return <ConnectionFailed error={connectionData.error} />;
            case 'not-connected':
            default:
                return <NotConnected />;
        }
    };
    
    return (
        <div className="surefeedback-connection-view">
            {/* Centered Connection Content */}
            <div className="flex justify-center items-start">
                <div className="w-full max-w-2xl">
                    <div className="space-y-6">
                        {renderConnectionStatus()}
                        
                        {isConnected && (
                            <div className="mt-8">
                                <ConnectionCard data={connectionData} />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ConnectionView;