<?php

namespace SureFeedback\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Repository
 *
 * Handles all settings-related data operations.
 *
 * @package SureFeedback\App\Repositories
 * @author Anurag Singh <anurags@bsf.io>
 */
class SettingsRepository extends BaseRepository {

	/**
	 * Default settings structure
	 *
	 * @var array
	 */
	protected $defaultSettings = array(
		'roles' => array(), // Empty array means all roles enabled by default
	);

	/**
	 * Get all general settings
	 *
	 * @return array
	 */
	public function getGeneralSettings(): array {
		$settings = array();

		// Get each setting with surefeedback_ prefix
		foreach ( $this->defaultSettings as $key => $defaultValue ) {
			$settings[ $key ] = $this->getOption( $key, $defaultValue );
		}

		// If roles setting is empty or not set, return all available roles as default
		if ( empty( $settings['roles'] ) || ! is_array( $settings['roles'] ) ) {
			$settings['roles'] = $this->getAllAvailableRoleNames();
		}

		return $settings;
	}

	/**
	 * Get all available WordPress role names
	 *
	 * @return array
	 */
	private function getAllAvailableRoleNames(): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		return array_keys( $wp_roles->roles );
	}

	/**
	 * Get specific setting value
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function getSetting( string $key, $default = null ) {
		return $this->getOption( $key, $default );
	}

	/**
	 * Update general settings
	 *
	 * @param array $settings
	 * @return array Updated settings
	 */
	public function updateGeneralSettings( array $settings ): array {
		$updatedSettings = array();

		foreach ( $settings as $key => $value ) {
			// Sanitize and save each setting with surefeedback_ prefix
			$sanitizedValue = $this->sanitizeSettingValue( $key, $value );

			if ( $this->setOption( $key, $sanitizedValue ) ) {
				$updatedSettings[ $key ] = $sanitizedValue;
			}
		}

		return $updatedSettings;
	}

	/**
	 * Sanitize setting value based on type
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return mixed
	 */
	private function sanitizeSettingValue( string $key, $value ) {
		switch ( $key ) {
			case 'roles':
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();

			default:
				return $this->sanitizeData( $value );
		}
	}

	/**
	 * Update specific setting
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return bool
	 */
	public function updateSetting( string $key, $value ): bool {
		$sanitizedValue = $this->sanitizeSettingValue( $key, $value );
		return $this->setOption( $key, $sanitizedValue );
	}

	/**
	 * Get plugin roles
	 *
	 * @return array
	 */
	public function getPluginRoles(): array {
		return (array) $this->getSetting( 'roles', $this->defaultSettings['roles'] );
	}

	/**
	 * Set plugin roles
	 *
	 * @param array $roles
	 * @return bool
	 */
	public function setPluginRoles( array $roles ): bool {
		$sanitizedRoles = array_map( 'sanitize_text_field', $roles );
		return $this->setOption( 'roles', $sanitizedRoles );
	}

	/**
	 * Reset settings to default
	 *
	 * @return bool
	 */
	public function resetSettings(): bool {
		return $this->setOption( 'settings', $this->defaultSettings );
	}

	/**
	 * Export settings
	 *
	 * @return array
	 */
	public function exportSettings(): array {
		return array(
			'timestamp' => current_time( 'mysql' ),
			'version'   => SUREFEEDBACK_VERSION ?? '1.0.0',
			'general'   => $this->getGeneralSettings(),
		);
	}

	/**
	 * Import settings
	 *
	 * @param array $data
	 * @param bool  $merge Whether to merge with existing settings or replace
	 * @return bool
	 */
	public function importSettings( array $data, bool $merge = true ): bool {
		if ( isset( $data['general'] ) ) {
			if ( $merge ) {
				return $this->updateGeneralSettings( $data['general'] );
			} else {
				return $this->setOption( 'settings', $this->sanitizeData( $data['general'] ) );
			}
		}

		return false;
	}

	/**
	 * Get all settings for backup
	 *
	 * @return array
	 */
	public function getAllSettings(): array {
		return array(
			'general' => $this->getGeneralSettings(),
		);
	}

	/**
	 * Backup current settings
	 *
	 * @return bool
	 */
	public function backupSettings(): bool {
		$backup = array(
			'timestamp' => current_time( 'mysql' ),
			'settings'  => $this->getAllSettings(),
		);

		return $this->setOption( 'settings_backup', $backup );
	}

	/**
	 * Restore settings from backup
	 *
	 * @return bool
	 */
	public function restoreFromBackup(): bool {
		$backup = $this->getOption( 'settings_backup' );

		if ( ! $backup || ! isset( $backup['settings'] ) ) {
			return false;
		}

		return $this->importSettings( $backup['settings'], false );
	}

	/**
	 * Validate settings data structure
	 *
	 * @param array $settings
	 * @return bool
	 */
	public function validateSettings( array $settings ): bool {
		// Check if all provided keys exist in defaults (no unknown keys)
		foreach ( $settings as $key => $value ) {
			if ( ! array_key_exists( $key, $this->defaultSettings ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get all settings (alias for getAllSettings)
	 *
	 * @return array
	 */
	public function getSettings(): array {
		return $this->getAllSettings();
	}
}
