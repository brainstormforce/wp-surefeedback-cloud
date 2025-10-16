import React from 'react';
import { useConnection, useDashboard, useErrorHandler } from '../hooks/index.js';
import QuickAccess from '../components/QuickAccess.jsx';
import ExtendWebsite from './ExtendWebsite.jsx';

/**
 * Main Dashboard Component
 * 
 * Displays connection status and dashboard statistics
 * using the new API services and hooks
 */
const Dashboard = () => {
    const { isConnected, connectionData, isLoading: connectionLoading, error: connectionError, connect, disconnect } = useConnection();
    const { stats, isLoading: statsLoading, refreshAll } = useDashboard();
    const { errors, removeError, hasErrors } = useErrorHandler();
    const [activeTab, setActiveTab] = React.useState('dashboard');

    const handleConnect = async () => {
        try {
            await connect({
                parentUrl: 'https://parent.example.com', // This would come from user input
                accessToken: 'sample-token', // This would come from user input
                signature: 'sample-signature' // This would be generated
            });
        } catch (error) {
            console.error('Connection failed:', error);
        }
    };

    const handleDisconnect = async () => {
        try {
            await disconnect();
        } catch (error) {
            console.error('Disconnect failed:', error);
        }
    };

    if (connectionLoading) {
        return (
            <div className="surefeedback-dashboard">
                <div className="loading">Loading...</div>
            </div>
        );
    }

    return (
        <div className="surefeedback-dashboard">
            {/* Navigation Tabs */}
            <div className="dashboard-tabs">
                <button 
                    className={`tab ${activeTab === 'dashboard' ? 'active' : ''}`}
                    onClick={() => setActiveTab('dashboard')}
                >
                    Dashboard
                </button>
                <button 
                    className={`tab ${activeTab === 'extend' ? 'active' : ''}`}
                    onClick={() => setActiveTab('extend')}
                >
                    Extend Website
                </button>
            </div>

            {/* Error Display */}
            {hasErrors && (
                <div className="error-container">
                    {errors.map(({ id, error }) => (
                        <div key={id} className="error-message">
                            <strong>Error:</strong> {error.message}
                            <button onClick={() => removeError(id)} className="error-close">×</button>
                        </div>
                    ))}
                </div>
            )}

            {/* Tab Content */}
            {activeTab === 'dashboard' && (
                <div className="dashboard-content">
                    <h1>SureFeedback Dashboard</h1>
                    
                    {connectionLoading && (
                        <div className="loading">Loading...</div>
                    )}

                    {/* Connection Status */}
                    <div className="connection-status">
                        <h2>Connection Status</h2>
                        <p>Status: {isConnected ? 'Connected' : 'Disconnected'}</p>
                        
                        {connectionData && (
                            <div className="connection-details">
                                <p><strong>Parent URL:</strong> {connectionData.parent_url}</p>
                                <p><strong>Last Check:</strong> {connectionData.last_check}</p>
                            </div>
                        )}

                        <div className="connection-actions">
                            {isConnected ? (
                                <button onClick={handleDisconnect} className="btn btn-secondary">
                                    Disconnect
                                </button>
                            ) : (
                                <button onClick={handleConnect} className="btn btn-primary">
                                    Connect
                                </button>
                            )}
                            <button onClick={() => window.location.reload()} className="btn btn-tertiary">
                                Refresh
                            </button>
                        </div>
                    </div>

                    {/* Dashboard Stats */}
                    {isConnected && (
                        <div className="dashboard-stats">
                            <h2>Dashboard Statistics</h2>
                            {statsLoading ? (
                                <p>Loading statistics...</p>
                            ) : stats ? (
                                <div className="stats-grid">
                                    <div className="stat-item">
                                        <h3>Total Comments</h3>
                                        <p>{stats.total_comments || 0}</p>
                                    </div>
                                    <div className="stat-item">
                                        <h3>Active Projects</h3>
                                        <p>{stats.active_projects || 0}</p>
                                    </div>
                                    <div className="stat-item">
                                        <h3>Pending Reviews</h3>
                                        <p>{stats.pending_reviews || 0}</p>
                                    </div>
                                </div>
                            ) : (
                                <p>No statistics available</p>
                            )}
                            
                            <button onClick={refreshAll} className="btn btn-secondary">
                                Refresh Stats
                            </button>
                        </div>
                    )}

                    {/* Quick Access Component */}
                    <div className="quick-access-section">
                        <QuickAccess />
                    </div>
                </div>
            )}

            {/* Extend Website Tab */}
            {activeTab === 'extend' && (
                <div className="extend-content">
                    <ExtendWebsite />
                </div>
            )}
        </div>
    );
};

export default Dashboard;