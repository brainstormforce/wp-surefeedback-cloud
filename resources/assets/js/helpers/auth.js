/**
 * SureFeedback Authentication Helpers
 */

import { apiGateway } from '../api/gateway.js';
import { API_ENDPOINTS } from '../constants/api.js';

export const authenticateRedirect = async () => {
  const { sureFeedbackAdmin } = window;
  
  if (!sureFeedbackAdmin || !sureFeedbackAdmin.connection) {
    return;
  }

  const { connection } = sureFeedbackAdmin;

  // Generate a state token for security
  const state = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);

  // Store state in WordPress for webhook verification
  try {
    await apiGateway.post(API_ENDPOINTS.CONNECTION.STORE_STATE, {
      state: state
    });
  } catch (error) {
    console.warn('Error storing state for webhook verification:', error);
  }

  const params = new URLSearchParams({
    source: 'wordpress',
    action: 'connect_site',
    callback_url: connection.callback_url,
    state: state,
    site_data: btoa(JSON.stringify(connection.site_data)), // base64 encode like PHP version
  });

  // Construct the connection URL using localized app URL
  const connectUrl = `${connection.app_url}/connect?${params.toString()}`;
  
  // Store connection intent in sessionStorage for redirect after login
  const connectionIntent = {
    url: connectUrl,
    timestamp: Date.now(),
    source: 'wordpress_plugin',
    state: state
  };
  
  // Store in both sessionStorage and localStorage for reliability
  sessionStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  localStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  
  // Redirect to parent site for authentication
  window.open(connectUrl, '_blank');
};

export const reconnectSite = async () => {
  const { sureFeedbackAdmin } = window;
  
  if (!sureFeedbackAdmin || !sureFeedbackAdmin.connection) {
    return;
  }

  const { connection } = sureFeedbackAdmin;
  
  // Generate a state token for security
  const state = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
  
  // Store state in WordPress for webhook verification
  try {
    await apiGateway.post(API_ENDPOINTS.CONNECTION.STORE_STATE, {
      state: state
    });
  } catch (error) {
    console.warn('Error storing state for webhook verification:', error);
  }
  
  // Build reconnection URL parameters
  const params = new URLSearchParams({
    source: 'wordpress',
    action: 'reconnect_site',
    callback_url: connection.callback_url,
    state: state,
    site_data: btoa(JSON.stringify(connection.site_data)), // base64 encode like PHP version
  });
  const reconnectUrl = `${connection.app_url}/reconnect?${params.toString()}`;

  const connectionIntent = {
    url: reconnectUrl,
    timestamp: Date.now(),
    source: 'wordpress_plugin',
    action: 'reconnect',
    state: state
  };
  sessionStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  localStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  window.open(reconnectUrl, '_blank');
};


export const getUrlParam = (param) => {
  return new URL(window.location.href).searchParams.get(param);
};