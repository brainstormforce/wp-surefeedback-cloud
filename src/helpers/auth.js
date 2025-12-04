/**
 * SureFeedback Authentication Helpers (Production Cleaned)
 */

import { apiGateway } from '../api/gateway.js';
import { API_ENDPOINTS } from '../constants/api.js';

/**
 * Generate a cryptographically secure random hex string
 * @param {number} length
 * @returns {string}
 */
const generateSecureRandomHex = (length = 16) => {
  const array = new Uint8Array(length);
  crypto.getRandomValues(array);
  return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
};

/**
 * Generate a secure UUID v4
 * @returns {string}
 */
const generateSecureUUID = () => crypto.randomUUID();


export const authenticateRedirect = async () => {
  const { surefeedbackAdmin } = window;
  
  // Get auth URL from admin data
  const authUrl = surefeedbackAdmin?.authUrl || '#';
  
  if (!authUrl || authUrl === '#') {
    return;
  }

  // Simple redirect to SaaS auth page
  // The OAuth callback will be handled by Auth_Manager in PHP
  window.location.href = authUrl;
};

/**
 * Handle authentication completion
 * OAuth callback is handled by PHP Auth_Manager, so we just reload
 */
const handleAuthCompletion = async () => {
  // OAuth callback is handled server-side by Auth_Manager
  // Just reload the page to show updated connection status
  window.location.reload();
};

/**
 * Reconnect existing site
 */
export const reconnectSite = async () => {
  const { sureFeedbackAdmin } = window;
  if (!sureFeedbackAdmin?.connection) return;

  const { connection } = sureFeedbackAdmin;
  const state = generateSecureUUID();

  try {
    await apiGateway.post(API_ENDPOINTS.CONNECTION.STORE_STATE, { state });
  } catch (_) {}

  const authContext = {
    wp_nonce: sureFeedbackAdmin.rest_nonce || sureFeedbackAdmin.nonce || '',
    admin_url: sureFeedbackAdmin.admin_url || '',
    user_id: sureFeedbackAdmin.current_user?.ID || '',
    timestamp: Date.now(),
  };

  const params = new URLSearchParams({
    source: 'wordpress',
    action: 'reconnect_site',
    callback_url: connection.callback_url,
    state,
    site_data: btoa(JSON.stringify(connection.site_data)),
    auth_context: btoa(JSON.stringify(authContext)),
  });

  const reconnectUrl = `${connection.app_url}/reconnect?${params.toString()}`;
  const connectionIntent = {
    url: reconnectUrl,
    timestamp: Date.now(),
    source: 'wordpress_plugin',
    action: 'reconnect',
    state,
    auth_context: authContext,
  };

  sessionStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  localStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));

  const popup = window.open(
    reconnectUrl,
    'surefeedback_reconnect',
    'width=600,height=700,scrollbars=yes,resizable=yes,centerscreen=yes,modal=yes'
  );

  if (popup) {
    const checkClosed = setInterval(() => {
      if (popup.closed) {
        clearInterval(checkClosed);
        handleAuthCompletion();
      }
    }, 1000);

    const messageHandler = (event) => {
      if (event.origin !== new URL(connection.app_url).origin) return;

      if (event.data?.type === 'surefeedback_auth_complete') {
        clearInterval(checkClosed);
        window.removeEventListener('message', messageHandler);
        if (!popup.closed) popup.close();
        handleAuthCompletion(event.data);
      }
    };

    window.addEventListener('message', messageHandler);

    setTimeout(() => {
      clearInterval(checkClosed);
      window.removeEventListener('message', messageHandler);
      if (!popup.closed) popup.close();
    }, 5 * 60 * 1000);
  } else {
    window.location.href = reconnectUrl;
  }
};

/**
 * Get a URL parameter
 */
export const getUrlParam = (param) =>
  new URL(window.location.href).searchParams.get(param);

/**
 * Initialize authentication handling
 */
export const initializeAuthHandling = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const authSuccess = urlParams.get('auth_success');
  const authError = urlParams.get('auth_error');
  const state = urlParams.get('state');
  const token = urlParams.get('token');

  if (authSuccess === 'true' || authError) {
    handleAuthReturn({ authSuccess, authError, state, token });
  }

  window.addEventListener('storage', handleStorageChange);
};

/**
 * Handle authentication return
 */
const handleAuthReturn = async ({ authSuccess, authError, state, token }) => {
  try {
    const cleanUrl = window.location.pathname +
      window.location.search
        .replace(/[?&](auth_success|auth_error|state|token)=[^&]*/g, '')
        .replace(/^&/, '?')
        .replace(/\?&/, '?')
        .replace(/\?$/, '');

    window.history.replaceState({}, document.title, cleanUrl);

    if (authError && window.SureFeedback?.admin) {
      window.SureFeedback.admin.showError('Authentication failed. Please try again.');
      return;
    }

    if (authSuccess === 'true') {
      const intentData =
        sessionStorage.getItem('surefeedback_connection_intent') ||
        localStorage.getItem('surefeedback_connection_intent');

      if (intentData) {
        const intent = JSON.parse(intentData);
        if (state && state !== intent.state) return;
      }

      if (token) {
        apiGateway.setAuthToken(token);
        if (window.sureFeedbackAdmin) {
          window.sureFeedbackAdmin.rest_nonce = token;
          window.sureFeedbackAdmin.nonce = token;
        }
      }

      await handleAuthCompletion({ token, type: 'surefeedback_auth_complete' });
    }
  } catch (_) {}
};

/**
 * Handle localStorage communication
 */
const handleStorageChange = (event) => {
  if (event.key === 'surefeedback_auth_complete' && event.newValue) {
    try {
      const authData = JSON.parse(event.newValue);
      handleAuthCompletion(authData);
      localStorage.removeItem('surefeedback_auth_complete');
    } catch (_) {}
  }
};

/**
 * Notify parent window about authentication completion
 */
export const signalAuthCompletion = (authData) => {
  if (window.opener) {
    window.opener.postMessage(
      { type: 'surefeedback_auth_complete', ...authData },
      window.location.origin
    );
  }

  localStorage.setItem(
    'surefeedback_auth_complete',
    JSON.stringify({ type: 'surefeedback_auth_complete', timestamp: Date.now(), ...authData })
  );

  if (window.opener) window.close();
};

// Auto-initialize
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAuthHandling);
} else {
  initializeAuthHandling();
}

window.SureFeedbackAuth = {
  authenticateRedirect,
  reconnectSite,
  signalAuthCompletion,
  initializeAuthHandling,
  getUrlParam,
};
