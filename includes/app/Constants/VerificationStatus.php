<?php

namespace SureFeedback\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Verification Status Constants
 *
 * Defines the verification status enum values for the SureFeedback plugin
 *
 * @package SureFeedback\Constants
 * @author Anurag Singh
 */
class VerificationStatus {

	/**
	 * Pending verification - site connected but script not verified
	 * Used when site_connected = 1 and is_fully_verified = 0
	 */
	const PENDING = 0;

	/**
	 * Fully verified and connected
	 * Widget script is loaded and verification is successful
	 * Used when site_connected = 1 and is_fully_verified = 1
	 */
	const CONNECTED = 1;

	/**
	 * Not verified - site connected but script not loaded
	 * Used when site_connected = 1 and is_fully_verified = 2
	 */
	const NOT_VERIFIED = 2;

	/**
	 * Get status label
	 *
	 * @param int $status
	 * @return string
	 */
	public static function getLabel( int $status ): string {
		switch ( $status ) {
			case self::PENDING:
				return 'Pending';
			case self::CONNECTED:
				return 'Connected';
			case self::NOT_VERIFIED:
				return 'Not Verified';
			default:
				return 'Unknown';
		}
	}

	/**
	 * Check if status is valid
	 *
	 * @param int $status
	 * @return bool
	 */
	public static function isValid( int $status ): bool {
		return in_array( $status, array( self::PENDING, self::CONNECTED, self::NOT_VERIFIED ), true );
	}

	/**
	 * Get all statuses
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			self::PENDING      => 'Pending',
			self::CONNECTED    => 'Connected',
			self::NOT_VERIFIED => 'Not Verified',
		);
	}
}
