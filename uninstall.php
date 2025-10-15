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

// delete our options.
delete_option( 'surefeedback_api_key' );
delete_option( 'surefeedback_access_token' );
delete_option( 'surefeedback_project_id' );
delete_option( 'surefeedback_parent_url' );
delete_option( 'surefeedback_signature' );
delete_option( 'surefeedback_installed' );
delete_option( 'surefeedback_admin_enabled' );
delete_option( 'surefeedback_allow_guests' );
delete_option( 'surefeedback_connection_status' );
delete_option( 'surefeedback_commenters' );
delete_option( 'surefeedback_manual_connection' );
