import React from 'react'
import { createRoot } from 'react-dom/client'
import Dashboard from './views/Dashboard'
import './assets/tailwind.css'
import './assets/dashboard.css'
import './assets/dashboard-styles.css'

// Import new API services
import { ApiProvider, initializeServices } from './utils/integration.js'

// Initialize API services
initializeServices()

// Enhanced App component with API provider
function App() {
  return (
    <ApiProvider>
      <Dashboard />
    </ApiProvider>
  )
}

// Wait for DOM to be ready
function initApp() {
  const container = document.getElementById('surefeedback-dashboard-app')
  if (!container) return
  try {
    const root = createRoot(container)
    root.render(<App />)
  } catch (error) {
    console.error('SureFeedback: Failed to initialize app', error)
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp)
} else {
  initApp()
}