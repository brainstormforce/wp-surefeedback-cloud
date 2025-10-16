/**
 * SureFeedback Authentication Helpers
 */

export const authenticateRedirect = () => {
  const { sureFeedbackAdmin } = window;
  
  if (!sureFeedbackAdmin || !sureFeedbackAdmin.connection) {
    console.error('SureFeedback admin data or connection info not available');
    return;
  }

  const { connection } = sureFeedbackAdmin;
  
  // Generate a state token for security
  const state = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
  
  // Build connection URL parameters following the PHP pattern
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
    source: 'wordpress_plugin'
  };
  
  // Store in both sessionStorage and localStorage for reliability
  sessionStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  localStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  
  // Redirect to parent site for authentication
  window.open(connectUrl, '_blank');
};

export const reconnectSite = () => {
  const { sureFeedbackAdmin } = window;
  
  if (!sureFeedbackAdmin || !sureFeedbackAdmin.connection) {
    console.error('SureFeedback admin data or connection info not available');
    return;
  }

  const { connection } = sureFeedbackAdmin;
  
  // Generate a state token for security
  const state = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
  
  // Build reconnection URL parameters
  const params = new URLSearchParams({
    source: 'wordpress',
    action: 'reconnect_site',
    callback_url: connection.callback_url,
    state: state,
    site_data: btoa(JSON.stringify(connection.site_data)), // base64 encode like PHP version
  });

  // Construct the reconnection URL - use /reconnect instead of /connect
  const reconnectUrl = `${connection.app_url}/reconnect?${params.toString()}`;
  
  // Store connection intent for redirect after login
  const connectionIntent = {
    url: reconnectUrl,
    timestamp: Date.now(),
    source: 'wordpress_plugin',
    action: 'reconnect'
  };
  
  // Store in both sessionStorage and localStorage for reliability
  sessionStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  localStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
  
  
  // Redirect to SaaS platform for reconnection
  window.open(reconnectUrl, '_blank');
};

export const disconnectSite = async () => {
  const { sureFeedbackAdmin } = window;
  
  if (!sureFeedbackAdmin) {
    console.error('SureFeedback admin data not available');
    return { success: false, error: 'Admin data not available' };
  }

  // Get site data from WordPress options
  const siteToken = sureFeedbackAdmin.site_token || sureFeedbackAdmin.api_token;
  const domain = sureFeedbackAdmin.site_domain || window.location.hostname;
  const apiUrl = sureFeedbackAdmin.api_url || 'https://app.surefeedback.com/api/v1';

  if (!siteToken) {
    console.error('No site token found for disconnection');
    return { success: false, error: 'No site token found' };
  }

  try {
    // Make API call to SaaS platform to disconnect the site
    const response = await fetch(`${apiUrl}/sites/wordpress/disconnect`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${sureFeedbackAdmin.user_token || ''}`, // JWT token if available
      },
      body: JSON.stringify({
        site_token: siteToken,
        domain: domain,
      }),
    });

    const result = await response.json();

    if (result.success) {
      // Clear local storage and session storage
      localStorage.removeItem('surefeedback_connection_intent');
      sessionStorage.removeItem('surefeedback_connection_intent');
      
      // Clear any other SureFeedback related storage
      Object.keys(localStorage).forEach(key => {
        if (key.startsWith('surefeedback_')) {
          localStorage.removeItem(key);
        }
      });

      // Now call WordPress plugin endpoint to clear local WordPress data
      try {
        const wpDisconnectResponse = await fetch(sureFeedbackAdmin.rest_url + 'surefeedback/v1/disconnect', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': sureFeedbackAdmin.rest_nonce,
          },
        });

        const wpResult = await wpDisconnectResponse.json();
        
        if (!wpResult.success) {
          console.warn('WordPress local data clearing failed:', wpResult.message);
        }
      } catch (error) {
        console.warn('Failed to clear WordPress local data:', error);
      }

      return { success: true, data: result.data };
    } else {
      console.error('Failed to disconnect site:', result.message);
      return { success: false, error: result.message };
    }
  } catch (error) {
    console.error('Disconnect request failed:', error);
    return { success: false, error: 'Network error occurred' };
  }
};

export const getUrlParam = (param) => {
  return new URL(window.location.href).searchParams.get(param);
};