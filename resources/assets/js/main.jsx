import React from 'react';
import { createRoot } from 'react-dom/client'
import Dashboard from './views/Dashboard'
import ErrorBoundary from './components/ErrorBoundary.jsx'
import './assets/tailwind.css'
import './assets/dashboard.css'
import './styles.css'

// Import new API services
import { ApiProvider } from './utils/integration.jsx'
import { initializeServices } from './index.js'

// Initialize API services
try {
  initializeServices()
} catch (error) {
  // Error handled silently
}

function App({ containerType }) {
  return (
    <ErrorBoundary>
      <div className="surefeedback-app-wrapper">
        <div className="surefeedback-styles font-figtree">
          <ApiProvider>
            <Dashboard containerType={containerType} />
          </ApiProvider>
        </div>
      </div>
    </ErrorBoundary>
  )
}

// Wait for DOM to be ready
function initApp() {
  try {
    // Try to find any of the possible container IDs
    const containers = [
      { id: 'surefeedback-dashboard-app', type: 'dashboard' },
      { id: 'surefeedback-admin-dashboard', type: 'dashboard' },
      { id: 'surefeedback-admin-settings', type: 'settings' },
      { id: 'surefeedback-admin-connection', type: 'connection' },
      { id: 'surefeedback-admin-widget-control', type: 'widget-control' },
      { id: 'surefeedback-admin-tools', type: 'tools' }
    ]
    
    let appInitialized = false;
    
    for (const container of containers) {
      const element = document.getElementById(container.id)
      if (element) {
        try {
          const root = createRoot(element)
          root.render(<App containerType={container.type} />)
          appInitialized = true;
          break
        } catch (error) {
          // Continue to try other containers
        }
      }
    }
    
    if (!appInitialized) {
      // No valid container element found
    }

  } catch (error) {
    // Critical initialization error
    
    // Try to show a basic error message if possible
    const errorContainer = document.querySelector('[id*="surefeedback"]')
    if (errorContainer) {
      errorContainer.innerHTML = `
        <div style="padding: 20px; background: #fee; border: 1px solid #fcc; border-radius: 4px; color: #c33;">
          <h3>SureFeedback Error</h3>
          <p>Failed to load the SureFeedback interface. Please refresh the page.</p>
          <button onclick="location.reload()" style="margin-top: 10px; padding: 8px 16px; background: #c33; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Refresh Page
          </button>
        </div>
      `;
    }
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp)
} else {
  initApp()
}