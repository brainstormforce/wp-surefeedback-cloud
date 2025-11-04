<?php
/**
 * Uninstall SureFeedback Child Plugin
 *
 * Deletes all the plugin data
 *
 * @package     SureFeedback Child
 * @subpackage  Uninstall
 * @copyright   Copyright (c) 2016, Andre Gagnon
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
// Using direct delete_option calls for reliability during uninstall.
$options_to_delete = array(
	// Connection data.
	'surefeedback_site_id',
	'surefeedback_access_token',
	'surefeedback_project_id',
	'surefeedback_api_key',
	'surefeedback_parent_url',
	'surefeedback_signature',
	'surefeedback_connection_status',
	'surefeedback_site_name',
	'surefeedback_domain',
	'surefeedback_organization_id',
	'surefeedback_is_active',
	'surefeedback_created_at',
	'surefeedback_site_connected',
	'surefeedback_last_verification',
	'surefeedback_is_fully_verified',
	'surefeedback_user_token',
	// Settings data.
	'surefeedback_installed',
	'surefeedback_admin_enabled',
	'surefeedback_allow_guests',
	'surefeedback_commenters',
	'surefeedback_manual_connection',
	'surefeedback_jwt_secret',
	'surefeedback_roles',
	'surefeedback_page_widget_settings',
	'surefeedback_settings_backup',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Clear transients.
delete_transient( 'surefeedback_connection_check' );
wp_cache_delete( 'surefeedback_settings' );
