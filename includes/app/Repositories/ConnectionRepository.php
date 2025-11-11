<?php

namespace SureFeedback\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Connection Repository Class
 *
 * Manages all connection-related data including access tokens,
 * site IDs, project IDs, and connection status.
 */
class ConnectionRepository extends BaseRepository {

	/**
	 * Get site ID
	 *
	 * @return string|null
	 */
	public function getSiteId(): ?string {
		$site_id = $this->getOption( 'site_id' );
		return ! empty( $site_id ) ? $site_id : null;
	}

	/**
	 * Set site ID
	 *
	 * @param string $site_id Site ID.
	 * @return bool
	 */
	public function setSiteId( string $site_id ): bool {
		return $this->setOption( 'site_id', sanitize_text_field( $site_id ) );
	}

	/**
	 * Get access token
	 *
	 * @return string|null
	 */
	public function getAccessToken(): ?string {
		$token = $this->getOption( 'access_token' );
		return ! empty( $token ) ? $token : null;
	}

	/**
	 * Set access token
	 *
	 * @param string $token Access token.
	 * @return bool
	 */
	public function setAccessToken( string $token ): bool {
		return $this->setOption( 'access_token', sanitize_text_field( $token ) );
	}

	/**
	 * Get project ID
	 *
	 * @return string|null
	 */
	public function getProjectId(): ?string {
		$project_id = $this->getOption( 'project_id' );
		return ! empty( $project_id ) ? $project_id : null;
	}

	/**
	 * Set project ID
	 *
	 * @param string $project_id Project ID.
	 * @return bool
	 */
	public function setProjectId( string $project_id ): bool {
		return $this->setOption( 'project_id', sanitize_text_field( $project_id ) );
	}

	/**
	 * Get API key
	 *
	 * @return string|null
	 */
	public function getApiKey(): ?string {
		$api_key = $this->getOption( 'api_key' );
		return ! empty( $api_key ) ? $api_key : null;
	}

	/**
	 * Set API key
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	public function setApiKey( string $api_key ): bool {
		return $this->setOption( 'api_key', sanitize_text_field( $api_key ) );
	}

	/**
	 * Get connection status
	 *
	 * @return string
	 */
	public function getConnectionStatus(): string {
		return $this->getOption( 'connection_status', 'disconnected' );
	}

	/**
	 * Set connection status
	 *
	 * @param string $status Connection status.
	 * @return bool
	 */
	public function setConnectionStatus( string $status ): bool {
		$allowed_statuses = array( 'connected', 'disconnected', 'pending', 'error' );
		$status           = sanitize_text_field( $status );

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		return $this->setOption( 'connection_status', $status );
	}

	/**
	 * Check if site is connected
	 *
	 * @return bool
	 */
	public function isConnected(): bool {
		$access_token = $this->getAccessToken();
		$status       = $this->getConnectionStatus();

		return ! empty( $access_token ) && 'connected' === $status;
	}

	/**
	 * Get all connection data
	 *
	 * @return array
	 */
	public function getConnectionData(): array {
		return array(
			'site_id'           => $this->getSiteId(),
			'access_token'      => $this->getAccessToken(),
			'project_id'        => $this->getProjectId(),
			'api_key'           => $this->getApiKey(),
			'connection_status' => $this->getConnectionStatus(),
			'is_connected'      => $this->isConnected(),
		);
	}

	/**
	 * Save connection data
	 *
	 * @param array $data Connection data.
	 * @return bool
	 */
	public function saveConnectionData( array $data ): bool {
		$success = true;

		if ( isset( $data['site_id'] ) ) {
			$success = $this->setSiteId( $data['site_id'] ) && $success;
		}

		if ( isset( $data['access_token'] ) ) {
			$success = $this->setAccessToken( $data['access_token'] ) && $success;
		}

		if ( isset( $data['project_id'] ) ) {
			$success = $this->setProjectId( $data['project_id'] ) && $success;
		}

		if ( isset( $data['api_key'] ) ) {
			$success = $this->setApiKey( $data['api_key'] ) && $success;
		}

		if ( isset( $data['connection_status'] ) ) {
			$success = $this->setConnectionStatus( $data['connection_status'] ) && $success;
		}

		return $success;
	}

	/**
	 * Clear all connection data
	 *
	 * @return bool
	 */
	public function clearConnection(): bool {
		$success = true;

		$success = $this->deleteOption( 'site_id' ) && $success;
		$success = $this->deleteOption( 'access_token' ) && $success;
		$success = $this->deleteOption( 'project_id' ) && $success;
		$success = $this->deleteOption( 'api_key' ) && $success;
		$success = $this->setConnectionStatus( 'disconnected' ) && $success;

		return $success;
	}

	/**
	 * Get JWT secret
	 *
	 * @return string
	 */
	public function getJwtSecret(): string {
		// Check for stored JWT secret first
		$stored_secret = get_option( 'surefeedback_jwt_secret' );
		if ( $stored_secret ) {
			return $stored_secret;
		}

		// Generate new random JWT secret
		$secret = $this->generateSecureJwtSecret();
		update_option( 'surefeedback_jwt_secret', $secret );
		
		return $secret;
	}

	/**
	 * Generate a cryptographically secure JWT secret
	 *
	 * @return string
	 */
	private function generateSecureJwtSecret(): string {
		// Ensure WordPress auth constants are defined
		if ( ! defined( 'SECURE_AUTH_KEY' ) || empty( SECURE_AUTH_KEY ) ) {
			wp_die( 'SECURE_AUTH_KEY is not defined in wp-config.php. Please add WordPress authentication keys.' );
		}

		// Combine multiple entropy sources for better security
		$entropy_sources = array(
			wp_generate_password( 64, true, true ), // High entropy random string
			SECURE_AUTH_KEY,
			defined( 'AUTH_KEY' ) ? AUTH_KEY : '',
			defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '',
			defined( 'NONCE_KEY' ) ? NONCE_KEY : '',
			get_site_url(),
			time(),
			wp_rand()
		);

		// Create secure hash from all entropy sources
		$combined_entropy = implode( '|', $entropy_sources );
		return hash( 'sha256', $combined_entropy );
	}

	/**
	 * Rotate JWT secret (for security maintenance)
	 *
	 * @return string New JWT secret
	 */
	public function rotateJwtSecret(): string {
		$new_secret = $this->generateSecureJwtSecret();
		update_option( 'surefeedback_jwt_secret', $new_secret );
		
		// Log secret rotation for audit trail
		error_log( 'SureFeedback: JWT secret rotated at ' . current_time( 'mysql' ) );
		
		return $new_secret;
	}

	/**
	 * Set JWT secret (not needed - secrets are derived from WordPress keys)
	 *
	 * @param string $secret JWT secret.
	 * @return bool
	 * @deprecated Secrets are now derived from WordPress authentication keys
	 */
	public function setJwtSecret( string $secret ): bool {
		// Secrets are now derived from WordPress keys, no storage needed
		return true;
	}

	/**
	 * Get allow guests setting
	 *
	 * @return bool
	 */
	public function getAllowGuests(): bool {
		return (bool) $this->getOption( 'allow_guests', true );
	}

	/**
	 * Set allow guests setting
	 *
	 * @param bool $allow Allow guests.
	 * @return bool
	 */
	public function setAllowGuests( bool $allow ): bool {
		return $this->setOption( 'allow_guests', $allow );
	}

	/**
	 * Get parent URL
	 *
	 * @return string|null
	 */
	public function getParentUrl(): ?string {
		$url = $this->getOption( 'parent_url' );
		return ! empty( $url ) ? $url : null;
	}

	/**
	 * Set parent URL
	 *
	 * @param string $url Parent URL.
	 * @return bool
	 */
	public function setParentUrl( string $url ): bool {
		return $this->setOption( 'parent_url', esc_url_raw( $url ) );
	}

	/**
	 * Get signature
	 *
	 * @return string|null
	 */
	public function getSignature(): ?string {
		$signature = $this->getOption( 'signature' );
		return ! empty( $signature ) ? $signature : null;
	}

	/**
	 * Set signature
	 *
	 * @param string $signature Signature.
	 * @return bool
	 */
	public function setSignature( string $signature ): bool {
		return $this->setOption( 'signature', sanitize_text_field( $signature ) );
	}

	/**
	 * Get manual connection flag
	 *
	 * @return bool
	 */
	public function getManualConnection(): bool {
		return (bool) $this->getOption( 'manual_connection', false );
	}

	/**
	 * Set manual connection flag
	 *
	 * @param bool $manual Manual connection.
	 * @return bool
	 */
	public function setManualConnection( bool $manual ): bool {
		return $this->setOption( 'manual_connection', $manual );
	}

	/**
	 * Get user token (for API authentication)
	 *
	 * @return string|null
	 */
	public function getUserToken(): ?string {
		$token = $this->getOption( 'user_token' );
		return ! empty( $token ) ? $token : null;
	}

	/**
	 * Set user token
	 *
	 * @param string $token User token.
	 * @return bool
	 */
	public function setUserToken( string $token ): bool {
		return $this->setOption( 'user_token', sanitize_text_field( $token ) );
	}

	/**
	 * Get domain
	 *
	 * @return string|null
	 */
	public function getDomain(): ?string {
		$domain = $this->getOption( 'domain' );
		return ! empty( $domain ) ? $domain : null;
	}

	/**
	 * Set domain
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	public function setDomain( string $domain ): bool {
		return $this->setOption( 'domain', sanitize_text_field( $domain ) );
	}

	/**
	 * Get site name
	 *
	 * @return string|null
	 */
	public function getSiteName(): ?string {
		$name = $this->getOption( 'site_name' );
		return ! empty( $name ) ? $name : null;
	}

	/**
	 * Set site name
	 *
	 * @param string $name Site name.
	 * @return bool
	 */
	public function setSiteName( string $name ): bool {
		return $this->setOption( 'site_name', sanitize_text_field( $name ) );
	}

	/**
	 * Get organization ID
	 *
	 * @return string|null
	 */
	public function getOrganizationId(): ?string {
		$id = $this->getOption( 'organization_id' );
		return ! empty( $id ) ? $id : null;
	}

	/**
	 * Set organization ID
	 *
	 * @param string $id Organization ID.
	 * @return bool
	 */
	public function setOrganizationId( string $id ): bool {
		return $this->setOption( 'organization_id', sanitize_text_field( $id ) );
	}

	/**
	 * Get is active flag
	 *
	 * @return bool
	 */
	public function getIsActive(): bool {
		return (bool) $this->getOption( 'is_active', true );
	}

	/**
	 * Set is active flag
	 *
	 * @param bool $active Is active.
	 * @return bool
	 */
	public function setIsActive( bool $active ): bool {
		return $this->setOption( 'is_active', $active );
	}

	/**
	 * Get created at timestamp
	 *
	 * @return string|null
	 */
	public function getCreatedAt(): ?string {
		$created = $this->getOption( 'created_at' );
		return ! empty( $created ) ? $created : null;
	}

	/**
	 * Set created at timestamp
	 *
	 * @param string $created_at Created at.
	 * @return bool
	 */
	public function setCreatedAt( string $created_at ): bool {
		return $this->setOption( 'created_at', sanitize_text_field( $created_at ) );
	}

	/**
	 * Get site connected flag
	 *
	 * @return bool
	 */
	public function getSiteConnected(): bool {
		return (bool) $this->getOption( 'site_connected', false );
	}

	/**
	 * Set site connected flag
	 *
	 * @param bool $connected Site connected.
	 * @return bool
	 */
	public function setSiteConnected( bool $connected ): bool {
		return $this->setOption( 'site_connected', $connected );
	}

	/**
	 * Get last verification timestamp
	 *
	 * @return string|null
	 */
	public function getLastVerification(): ?string {
		$last = $this->getOption( 'last_verification' );
		return ! empty( $last ) ? $last : null;
	}

	/**
	 * Set last verification timestamp
	 *
	 * @param string $last_verification Last verification.
	 * @return bool
	 */
	public function setLastVerification( string $last_verification ): bool {
		return $this->setOption( 'last_verification', sanitize_text_field( $last_verification ) );
	}

	/**
	 * Get is fully verified status
	 *
	 * @param int $default Default value.
	 * @return int
	 */
	public function getIsFullyVerified( int $default = 0 ): int {
		return (int) $this->getOption( 'is_fully_verified', $default );
	}

	/**
	 * Set is fully verified status
	 *
	 * @param int $status Verification status.
	 * @return bool
	 */
	public function setIsFullyVerified( int $status ): bool {
		return $this->setOption( 'is_fully_verified', $status );
	}

	/**
	 * Clear all connection data (for reset)
	 *
	 * @return bool
	 */
	public function clearAllConnectionData(): bool {
		$options = array(
			'access_token',
			'parent_url',
			'site_id',
			'last_verification',
			'site_name',
			'domain',
			'organization_id',
			'is_active',
			'created_at',
			'site_connected',
			'is_fully_verified',
			'user_token',
		);

		$success = true;
		foreach ( $options as $option ) {
			$success = $this->deleteOption( $option ) && $success;
		}

		// Clear connection status
		$this->setConnectionStatus( 'disconnected' );

		return $success;
	}
}
