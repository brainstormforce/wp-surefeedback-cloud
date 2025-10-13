<?php

/**
 * Frontend Manager class
 *
 * @package SureFeedback
 */

namespace SureFeedback\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Manager class
 * 
 * Handles frontend script injection and widget loading
 */
class Frontend_Manager {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Inject widget script on frontend for connected sites
		add_action( 'wp_footer', array( $this, 'inject_widget_script' ) );
	}

	/**
	 * Inject SureFeedback widget script on frontend for connected sites
	 *
	 * @return void
	 */
	public function inject_widget_script() {
		// Only inject on frontend, not in admin
		if ( is_admin() ) {
			return;
		}

		// Check if site is connected and has proper tokens
		$site_id = get_option( 'surefeedback_id' );
		$script_token = get_option( 'surefeedback_script_token' );
		$access_token = get_option( 'surefeedback_access_token' );
		$parent_url = get_option( 'surefeedback_parent_url' );

		// Debug: Add console logging to help troubleshoot
		?>

		<?php

		if ( ! $site_id || ! $parent_url ) {
			?>
			<script>
			console.warn('SureFeedback: Widget not loaded - missing connection data');
			</script>
			<?php
			return;
		}

		// Fallback to manual script injection if no integration script
		$token = $script_token ?: $access_token;
		if ( ! $token ) {
			?>
			<script>
			console.warn('SureFeedback: No valid tokens available');
			</script>
			<?php
			return;
		}

		// Inject the SureFeedback widget script - corrected path and configuration
		?>
		<!-- SureFeedback Widget -->
		<script>
		// SureFeedback WordPress Integration Script (Fallback Mode)
		(function (d, t, g, defaultToken, baseUrl, debug, restrictedUrl, requiredToken) {
		'use strict';
		
		var sf = d.createElement(t),
			s = d.getElementsByTagName(t)[0];
		
		sf.type = 'text/javascript';
		sf.async = true;
		sf.defer = true;
		sf.charset = 'UTF-8';
		sf.src = g + '?v=' + (new Date()).getTime();
		sf.setAttribute('data-default-token', defaultToken);
		sf.setAttribute('data-base-url', baseUrl);
		sf.setAttribute('data-debug', debug || 'false');
		sf.setAttribute('data-mode', 'iframe');
		sf.setAttribute('data-platform', 'wordpress');
		sf.setAttribute('data-site-url', window.location.href);
		sf.setAttribute('data-site-origin', window.location.origin);
		sf.setAttribute('data-site-domain', window.location.hostname);
		
		// Optional: Add restricted URL and required token if provided
		if (restrictedUrl) {
			sf.setAttribute('data-restricted-url', restrictedUrl);
		}
		if (requiredToken) {
			sf.setAttribute('data-required-token', requiredToken);
		}
		
		s.parentNode.insertBefore(sf, s);
		// Debug: Check URL parameters that widget-loader.js will look for
		const urlParams = new URLSearchParams(window.location.search);
	
		// Debug: Set up listener for widget ready event
		window.addEventListener('surefeedback:ready', function() {
			console.log('SureFeedback: Widget ready event fired - widget is fully loaded');
		});
		
		})(document, 'script', '<?php echo esc_js( $parent_url ); ?>/dist/js/widget-loader.js', '<?php echo esc_js( $token ); ?>', '<?php echo esc_js( $parent_url ); ?>', 'true', null, null);
		</script>
		<!-- End SureFeedback Widget -->
		<?php
	}

	/**
	 * Trigger script injection immediately by making internal HTTP request
	 * This forces wp_footer to run and inject the script right after webhook completes
	 * 
	 * @return void
	 */
	public function trigger_script_injection_immediately() {
		// Get the site URL to make internal request
		$site_url = home_url();

		// Make blocking HTTP request to ensure script injection completes
		$response = wp_remote_get( $site_url, array(
			'timeout' => 15,
			'blocking' => false, // Don't wait for response, just trigger the request
			'headers' => array(
				'User-Agent' => 'SureFeedback-Auto-Injection/1.0',
				'Cache-Control' => 'no-cache',
				'Pragma' => 'no-cache'
			),
			'sslverify' => false // In case of local SSL issues
		) );
		
		
	}
}