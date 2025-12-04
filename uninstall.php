<?php
/**
 * Uninstall SureFeedback Plugin
 *
 * Fired when the plugin is uninstalled. This file removes all plugin data
 * from the WordPress database including options, transients, and scheduled events.
 *
 * @package SureFeedback
 * @author Brainstorm Force
 * @since 0.0.1
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all SureFeedback plugin data from the database
 *
 * This function is called when the plugin is uninstalled (not just deactivated).
 * It removes all options, transients, scheduled events, and cached data.
 */
function surefeedback_uninstall_cleanup() {
	// Security: Only run if called by WordPress uninstall process
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// Clear all scheduled events
	wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
	wp_clear_scheduled_hook( 'surefeedback_hourly_verify' );
	wp_clear_scheduled_hook( 'surefeedback_cleanup_rate_limits' );

	// Define all plugin options to remove
	$options_to_remove = array(
		// Connection data
		'surefeedback_access_token',
		'surefeedback_parent_url',
		'surefeedback_site_id',
		'surefeedback_connection_status',
		'surefeedback_project_id',
		'surefeedback_api_key',
		'surefeedback_signature',
		'surefeedback_manual_connection',

		// Site data
		'surefeedback_last_verification',
		'surefeedback_site_name',
		'surefeedback_domain',
		'surefeedback_organization_id',
		'surefeedback_is_active',
		'surefeedback_created_at',
		'surefeedback_site_connected',
		'surefeedback_is_fully_verified',

		// Authentication & Security
		'surefeedback_user_token',
		'surefeedback_jwt_secret',
		'surefeedback_webhook_secret',
		'surefeedback_webhook_signing_secret',
		'surefeedback_disconnect_webhook_secret',
		'surefeedback_connection_signature_secret',
		'surefeedback_webhook_state',

		// Settings
		'surefeedback_roles',
		'surefeedback_allow_guests',
	);

	// Remove all defined options
	foreach ( $options_to_remove as $option ) {
		delete_option( $option );
	}

	// Remove all rate limit options (pattern: surefeedback_rate_limit_*)
	surefeedback_delete_options_by_pattern( 'surefeedback_rate_limit_' );

	// Remove all page settings options (pattern: surefeedback_page_settings_*)
	surefeedback_delete_options_by_pattern( 'surefeedback_page_settings_' );

	// Remove all plugin transients
	$transients_to_remove = array(
		'surefeedback_connection_check',
		'surefeedback_security_events',
		'surefeedback_activation_redirect',
	);

	foreach ( $transients_to_remove as $transient ) {
		delete_transient( $transient );
	}

	// Clear WordPress object cache for plugin data
	wp_cache_delete( 'surefeedback_settings' );
	wp_cache_flush_group( 'surefeedback_options' );

	// Remove any site transients (multisite)
	if ( is_multisite() ) {
		foreach ( $transients_to_remove as $transient ) {
			delete_site_transient( $transient );
		}
	}
}

/**
 * Delete all options matching a specific pattern
 *
 * @param string $pattern The prefix pattern to match (e.g., 'surefeedback_rate_limit_')
 * @return int Number of options deleted
 */
function surefeedback_delete_options_by_pattern( $pattern ) {
	global $wpdb;

	$deleted_count = 0;

	// Security: Use WordPress database methods with proper escaping
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $pattern ) . '%'
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	if ( ! empty( $options ) && is_array( $options ) ) {
		foreach ( $options as $option ) {
			if ( delete_option( $option ) ) {
				++$deleted_count;
			}
		}
	}

	return $deleted_count;
}

// Execute cleanup
surefeedback_uninstall_cleanup();
