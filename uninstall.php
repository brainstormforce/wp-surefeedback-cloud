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
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	wp_clear_scheduled_hook( 'surefeedback_auto_verify' );
	wp_clear_scheduled_hook( 'surefeedback_hourly_verify' );
	wp_clear_scheduled_hook( 'surefeedback_cleanup_rate_limits' );

	$options_to_remove = array(
		'surefeedback_access_token',
		'surefeedback_parent_url',
		'surefeedback_site_id',
		'surefeedback_connection_status',
		'surefeedback_project_id',
		'surefeedback_api_key',
		'surefeedback_signature',
		'surefeedback_manual_connection',
		'surefeedback_last_verification',
		'surefeedback_site_name',
		'surefeedback_domain',
		'surefeedback_organization_id',
		'surefeedback_is_active',
		'surefeedback_created_at',
		'surefeedback_site_connected',
		'surefeedback_is_fully_verified',
		'surefeedback_user_token',
		'surefeedback_jwt_secret',
		'surefeedback_webhook_secret',
		'surefeedback_webhook_signing_secret',
		'surefeedback_disconnect_webhook_secret',
		'surefeedback_connection_signature_secret',
		'surefeedback_webhook_state',
		'surefeedback_roles',
		'surefeedback_allow_guests',
	);
	foreach ( $options_to_remove as $option ) {
		delete_option( $option );
	}
	surefeedback_delete_options_by_pattern( 'surefeedback_rate_limit_' );
	surefeedback_delete_options_by_pattern( 'surefeedback_page_settings_' );
	$transients_to_remove = array(
		'surefeedback_connection_check',
		'surefeedback_security_events',
		'surefeedback_activation_redirect',
	);

	foreach ( $transients_to_remove as $transient ) {
		delete_transient( $transient );
	}
	wp_cache_delete( 'surefeedback_settings' );
	wp_cache_flush_group( 'surefeedback_options' );
	if ( is_multisite() ) {
		foreach ( $transients_to_remove as $transient ) {
			delete_site_transient( $transient );
		}
	}
}

/**
 * Delete all options matching a specific pattern
 *
 * @param string $pattern The prefix pattern to match (e.g., 'surefeedback_rate_limit_').
 * @return int Number of options deleted.
 */
function surefeedback_delete_options_by_pattern( $pattern ) {
	global $wpdb;

	$deleted_count = 0;

	// Cache key for this pattern search.
	$cache_key = 'surefeedback_options_' . md5( $pattern );
	$options   = wp_cache_get( $cache_key, 'surefeedback-cloud' );

	if ( false === $options ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for plugin uninstall cleanup
		$options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $pattern ) . '%'
			)
		);
		wp_cache_set( $cache_key, $options, 'surefeedback-cloud', 300 );
	}

	if ( ! empty( $options ) && is_array( $options ) ) {
		foreach ( $options as $option ) {
			if ( delete_option( $option ) ) {
				++$deleted_count;
			}
		}
		// Clear cache after deletion.
		wp_cache_delete( $cache_key, 'surefeedback-cloud' );
	}

	return $deleted_count;
}
surefeedback_uninstall_cleanup();
