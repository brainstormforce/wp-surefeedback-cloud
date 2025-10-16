import { createRoot } from 'react-dom/client'
import Dashboard from './views/Dashboard'
import './assets/tailwind.css'
import './assets/dashboard.css'
import './assets/dashboard-styles.css'

// Import new API services
import { ApiProvider } from './utils/integration.jsx'
import { initializeServices } from './index.js'

// Initialize API services
initializeServices()

// Enhanced App component with API provider
function App({ containerType }) {
  return (
    <ApiProvider>
      <Dashboard containerType={containerType} />
    </ApiProvider>
  )
}

// Wait for DOM to be ready
function initApp() {
  // Try to find any of the possible container IDs
  const containers = [
    { id: 'surefeedback-dashboard-app', type: 'dashboard' },
    { id: 'surefeedback-admin-dashboard', type: 'dashboard' },
    { id: 'surefeedback-admin-settings', type: 'settings' },
    { id: 'surefeedback-admin-connection', type: 'connection' },
    { id: 'surefeedback-admin-tools', type: 'tools' }
  ]
  
  for (const container of containers) {
    const element = document.getElementById(container.id)
    if (element) {
      try {
        const root = createRoot(element)
        root.render(<App containerType={container.type} />)
        console.log(`SureFeedback: Mounted to ${container.id}`)
        break
      } catch (error) {
        console.error(`SureFeedback: Failed to mount to ${container.id}`, error)
      }
    }
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp)
} else {
  initApp()
}