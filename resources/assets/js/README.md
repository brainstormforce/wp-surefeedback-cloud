# SureFeedback Frontend API Documentation

## Overview

This document provides comprehensive documentation for the SureFeedback frontend API architecture. The system features a centralized API gateway, service layer, and React hooks for seamless integration.

## Architecture

### Directory Structure

```
resources/assets/js/
├── api/
│   └── gateway.js              # Centralized HTTP client
├── services/
│   ├── connection.js           # Connection management
│   ├── settings.js            # Settings operations
│   ├── dashboard.js           # Dashboard data
│   └── admin.js               # Admin functionality
├── utils/
│   ├── errors.js              # Error handling
│   ├── auth.js                # Authentication
│   ├── cache.js               # Caching system
│   └── integration.js         # Component integration
├── constants/
│   └── api.js                 # API configuration
├── hooks/
│   └── index.js               # React hooks
└── index.js                   # Main exports
```

## API Gateway

### Basic Usage

```javascript
import { apiGateway } from './api/gateway.js';

// GET request
const data = await apiGateway.get('/connection/status');

// POST request
const result = await apiGateway.post('/settings', { 
    plugin_name: 'SureFeedback',
    parent_url: 'https://parent.com'
});

// PUT request
const updated = await apiGateway.put('/settings/general', settingsData);

// DELETE request
await apiGateway.delete('/connection');

// File upload
const formData = new FormData();
formData.append('file', file);
await apiGateway.upload('/import', formData);
```

### Advanced Features

```javascript
// Batch requests
const results = await apiGateway.batch([
    { method: 'GET', endpoint: '/connection/status' },
    { method: 'GET', endpoint: '/settings' },
    { method: 'GET', endpoint: '/dashboard/stats' }
]);

// Custom headers
await apiGateway.get('/endpoint', {
    headers: { 'Custom-Header': 'value' }
});

// Timeout override
await apiGateway.get('/endpoint', {
    timeout: 10000 // 10 seconds
});
```

## Services

### Connection Service

```javascript
import { connectionService } from './services/connection.js';

// Get connection status
const status = await connectionService.getStatus();

// Connect to parent site
const result = await connectionService.connect({
    parentUrl: 'https://parent.com',
    accessToken: 'token123',
    signature: 'signature456'
});

// Verify connection
const verification = await connectionService.verify(
    'https://parent.com', 
    'token123'
);

// Disconnect
await connectionService.disconnect();

// Health check
const health = await connectionService.getHealth();

// Test connection
const test = await connectionService.testConnection('https://parent.com');
```

#### Connection Events

```javascript
connectionService.addListener((event, data) => {
    switch (event) {
        case 'status_updated':
            console.log('Connection status:', data);
            break;
        case 'connection_established':
            console.log('Connected successfully');
            break;
        case 'connection_disconnected':
            console.log('Disconnected');
            break;
        case 'connection_error':
            console.error('Connection error:', data);
            break;
    }
});
```

### Settings Service

```javascript
import { settingsService } from './services/settings.js';

// Get all settings
const settings = await settingsService.getSettings();

// Get specific sections
const general = await settingsService.getGeneralSettings();
const whiteLabel = await settingsService.getWhiteLabelSettings();

// Update settings
const updated = await settingsService.updateSettings({
    plugin_name: 'Custom Name',
    parent_url: 'https://new-parent.com'
});

// Update specific sections
await settingsService.updateGeneralSettings(generalData);
await settingsService.updateWhiteLabelSettings(whiteLabelData);

// Reset settings
await settingsService.resetSettings('all'); // or 'general', 'white_label'

// Import/Export
const exported = await settingsService.exportSettings('json', ['general']);
await settingsService.importSettings({
    source: 'data',
    data: importData
});
```

### Dashboard Service

```javascript
import { dashboardService } from './services/dashboard.js';

// Get dashboard statistics
const stats = await dashboardService.getStats();

// Get quick access data
const quickAccess = await dashboardService.getQuickAccess();

// Get recent activity
const activity = await dashboardService.getRecentActivity({
    limit: 10,
    offset: 0
});

// Refresh all dashboard data
const overview = await dashboardService.refreshAll();

// Get specific widget data
const widgetData = await dashboardService.getWidgetData('stats');
```

### Admin Service

```javascript
import { adminService } from './services/admin.js';

// Get admin settings
const settings = await adminService.getSettings();

// Save settings
await adminService.saveGeneralSettings(generalData);
await adminService.saveWhiteLabelSettings(whiteLabelData);

// Verify integration
const verification = await adminService.verifyIntegration();

// Test parent site
const test = await adminService.testParentSite('https://parent.com');

// Disconnect site
await adminService.disconnectSite({ force: true });

// Get system info
const sysInfo = await adminService.getSystemInfo();

// Generate access token
const token = await adminService.generateAccessToken();
```

## React Hooks

### Connection Hook

```javascript
import { useConnection } from './hooks/index.js';

function ConnectionComponent() {
    const {
        isConnected,
        connectionData,
        isLoading,
        error,
        connect,
        disconnect,
        verify,
        refresh
    } = useConnection({
        autoRefresh: true,
        refreshInterval: 30000
    });

    if (isLoading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;

    return (
        <div>
            <p>Status: {isConnected ? 'Connected' : 'Disconnected'}</p>
            {!isConnected && (
                <button onClick={() => connect({ parentUrl, accessToken })}>
                    Connect
                </button>
            )}
            {isConnected && (
                <button onClick={disconnect}>Disconnect</button>
            )}
        </div>
    );
}
```

### Settings Hook

```javascript
import { useSettings } from './hooks/index.js';

function SettingsComponent() {
    const {
        settings,
        isLoading,
        error,
        updateSettings,
        resetSettings,
        refresh
    } = useSettings('general'); // or 'white_label', 'all'

    const handleSave = async (data) => {
        try {
            await updateSettings(data);
            alert('Settings saved successfully');
        } catch (error) {
            alert('Error saving settings: ' + error.message);
        }
    };

    return (
        <div>
            {/* Settings form */}
        </div>
    );
}
```

### Dashboard Hook

```javascript
import { useDashboard } from './hooks/index.js';

function DashboardComponent() {
    const {
        stats,
        quickAccess,
        recentActivity,
        isLoading,
        refreshAll,
        getStats
    } = useDashboard({
        autoRefresh: true,
        refreshInterval: 60000
    });

    return (
        <div>
            <button onClick={refreshAll}>Refresh Dashboard</button>
            {stats && (
                <div>
                    <h3>Statistics</h3>
                    <p>Total Comments: {stats.total_comments}</p>
                    <p>Active Projects: {stats.active_projects}</p>
                </div>
            )}
        </div>
    );
}
```

### Admin Hook

```javascript
import { useAdmin } from './hooks/index.js';

function AdminComponent() {
    const {
        settings,
        isLoading,
        error,
        saveGeneralSettings,
        verifyIntegration,
        testParentSite
    } = useAdmin();

    return (
        <div>
            {/* Admin interface */}
        </div>
    );
}
```

### Error Handling Hook

```javascript
import { useErrorHandler } from './hooks/index.js';

function AppComponent() {
    const {
        errors,
        removeError,
        clearErrors,
        hasErrors
    } = useErrorHandler();

    return (
        <div>
            {hasErrors && (
                <div className="error-container">
                    {errors.map(({ id, error }) => (
                        <div key={id} className="error-message">
                            {error.message}
                            <button onClick={() => removeError(id)}>×</button>
                        </div>
                    ))}
                    <button onClick={clearErrors}>Clear All</button>
                </div>
            )}
            {/* App content */}
        </div>
    );
}
```

## Integration with React

### Provider Setup

```javascript
import { ApiProvider } from './utils/integration.js';

function App() {
    return (
        <ApiProvider>
            <YourComponents />
        </ApiProvider>
    );
}
```

### Higher-Order Component

```javascript
import { withApiServices } from './utils/integration.js';

class MyComponent extends React.Component {
    async componentDidMount() {
        // Access API services via props
        const { api } = this.props;
        const status = await api.services.connection.getStatus();
        this.setState({ connectionStatus: status });
    }
}

export default withApiServices(MyComponent);
```

### Global Window Access

```javascript
// Access API globally (after initialization)
const status = await window.SureFeedbackApi.services.connection.getStatus();

// Initialize services
window.SureFeedbackApi.init();
```

## Error Handling

### Error Types

```javascript
import { ApiError, ValidationError, ConnectionError } from './utils/errors.js';

try {
    await apiCall();
} catch (error) {
    if (error instanceof ApiError) {
        console.log('API Error:', error.status, error.message);
        console.log('Is retryable:', error.isRetryable());
        console.log('User message:', error.getUserMessage());
    } else if (error instanceof ValidationError) {
        console.log('Validation Error:', error.field, error.message);
    } else if (error instanceof ConnectionError) {
        console.log('Connection Error:', error.parentUrl, error.message);
    }
}
```

### Error Handler

```javascript
import { errorHandler } from './utils/errors.js';

// Add custom error listener
errorHandler.addListener((error, context) => {
    console.log('Global error:', error, context);
    // Send to logging service, show notification, etc.
});

// Wrap functions with error handling
import { withErrorHandling } from './utils/errors.js';

const safeFunction = withErrorHandling(async () => {
    // Your async function
}, { context: 'MyComponent' });
```

## Caching

### Cache Manager

```javascript
import { cacheManager } from './utils/cache.js';

// Set cache item
cacheManager.set('key', data, 60000); // 1 minute

// Get cache item
const cached = cacheManager.get('key');

// Check if exists
if (cacheManager.has('key')) {
    // Use cached data
}

// Delete item
cacheManager.delete('key');

// Clear all
cacheManager.clear();

// Get cache stats
const stats = cacheManager.getStats();
```

### Cache with Callback

```javascript
// Get or set with callback
const data = await cacheManager.getOrSet('expensive-operation', async () => {
    return await expensiveApiCall();
}, 300000); // 5 minutes
```

## Authentication

### Token Manager

```javascript
import { tokenManager } from './utils/auth.js';

// Get current token
const token = tokenManager.getToken();

// Set new token
tokenManager.setToken('new-token');

// Refresh token
await tokenManager.refreshToken();

// Check if has token
if (tokenManager.hasToken()) {
    // Make authenticated request
}
```

### Auth Manager

```javascript
import { authManager } from './utils/auth.js';

// Check authentication
if (authManager.isAuth()) {
    console.log('User is authenticated');
    console.log('User:', authManager.getUser());
    console.log('Capabilities:', authManager.getCapabilities());
}

// Check permissions
if (authManager.canManage()) {
    // Show admin interface
}

if (authManager.canConfigureSettings()) {
    // Show settings
}
```

## Configuration

### API Configuration

```javascript
import { API_CONFIG, API_ENDPOINTS } from './constants/api.js';

// Access configuration
console.log('Base URL:', API_CONFIG.BASE_URL);
console.log('Timeout:', API_CONFIG.TIMEOUT);

// Access endpoints
console.log('Connection status:', API_ENDPOINTS.CONNECTION.STATUS);
console.log('Settings:', API_ENDPOINTS.SETTINGS.INDEX);
```

### Environment Detection

```javascript
import { ENVIRONMENT } from './constants/api.js';

if (ENVIRONMENT.IS_DEVELOPMENT) {
    console.log('Development mode');
}

if (ENVIRONMENT.IS_WORDPRESS_ADMIN) {
    console.log('WordPress admin area');
}
```

## Best Practices

### 1. Always Handle Errors

```javascript
try {
    const result = await apiCall();
    // Handle success
} catch (error) {
    // Handle error appropriately
    console.error('API call failed:', error);
    // Show user-friendly message
    showNotification(error.getUserMessage());
}
```

### 2. Use Hooks in React Components

```javascript
// Preferred approach
function MyComponent() {
    const { data, loading, error } = useConnection();
    // Component logic
}
```

### 3. Leverage Caching

```javascript
// Good: Uses cache when appropriate
const data = await service.getData(); // Automatically cached

// Better: Force refresh when needed
const data = await service.getData(true); // Force refresh
```

### 4. Clean Up Listeners

```javascript
useEffect(() => {
    const listener = (event, data) => {
        // Handle event
    };
    
    service.addListener(listener);
    
    // Always clean up
    return () => service.removeListener(listener);
}, []);
```

### 5. Use Type Checking

```javascript
// Add type checking in your components
const { connectionData } = useConnection();

if (connectionData && typeof connectionData === 'object') {
    // Safe to use connectionData
}
```

This documentation covers the modern SureFeedback frontend API architecture. The system provides a clean, maintainable, and scalable foundation for frontend development.