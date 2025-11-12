<?php

namespace SureFeedback\Repositories;

/**
 * Rate Limit Repository - Handles rate limiting data operations
 *
 * @package SureFeedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rate Limit Repository Class
 *
 * Manages rate limiting data using WordPress options API
 * without direct database queries.
 *
 * @package SureFeedback\App\Repositories
 * @author Anurag Singh <anurags@bsf.io>
 */
class RateLimitRepository extends BaseRepository {

	/**
	 * Get all rate limit option keys
	 *
	 * Uses wp_load_alloptions() to safely retrieve all options
	 * and filter by rate limit pattern.
	 *
	 * @return array Array of rate limit option names
	 */
	public function getRateLimitKeys(): array {
		$options = $this->getOptionsWithPattern( 'rate_limit_%' );

		// Return the full option names including prefix
		$keys = array();
		foreach ( array_keys( $options ) as $key ) {
			$keys[] = $this->prefix . $key;
		}

		return $keys;
	}

	/**
	 * Get rate limit data for a specific key
	 *
	 * @param string $key
	 * @return array
	 */
	public function getRateLimit( string $key ): array {
		return $this->getOption( $key, array() );
	}

	/**
	 * Set rate limit data
	 *
	 * @param string $key
	 * @param array  $data
	 * @return bool
	 */
	public function setRateLimit( string $key, array $data ): bool {
		return $this->setOption( $key, $data );
	}

	/**
	 * Delete rate limit data
	 *
	 * @param string $key
	 * @return bool
	 */
	public function deleteRateLimit( string $key ): bool {
		return $this->deleteOption( $key );
	}

	/**
	 * Clean up expired rate limits
	 *
	 * @param int $cutoff_time
	 * @return int Number of entries cleaned
	 */
	public function cleanupExpiredRateLimits( int $cutoff_time ): int {
		$rate_limit_keys = $this->getRateLimitKeys();
		$cleaned_count   = 0;

		foreach ( $rate_limit_keys as $option_name ) {
			// Extract the key part without prefix
			$key = str_replace( $this->prefix, '', $option_name );

			$requests = $this->getRateLimit( $key );

			if ( is_array( $requests ) && ! empty( $requests ) ) {
				// Filter out expired requests
				$filtered_requests = array_filter(
					$requests,
					function ( $timestamp ) use ( $cutoff_time ) {
						return $timestamp > $cutoff_time;
					}
				);

				if ( empty( $filtered_requests ) ) {
					// Delete the option if no valid requests remain
					if ( $this->deleteRateLimit( $key ) ) {
						++$cleaned_count;
					}
				} elseif ( count( $filtered_requests ) !== count( $requests ) ) {
					// Update with filtered requests
					$this->setRateLimit( $key, $filtered_requests );
				}
			}
		}

		return $cleaned_count;
	}

	/**
	 * Get rate limit entries count
	 *
	 * @return int
	 */
	public function getRateLimitEntriesCount(): int {
		return count( $this->getRateLimitKeys() );
	}

	/**
	 * Enforce global cap on rate limit entries
	 *
	 * @param int $max_entries
	 * @return int Number of entries removed
	 */
	public function enforceGlobalCap( int $max_entries ): int {
		$rate_limit_keys = $this->getRateLimitKeys();

		if ( count( $rate_limit_keys ) <= $max_entries ) {
			return 0;
		}

		// Determine last activity for each option to pick oldest
		$entries = array();
		foreach ( $rate_limit_keys as $option_name ) {
			$key      = str_replace( $this->prefix, '', $option_name );
			$requests = $this->getRateLimit( $key );

			if ( is_array( $requests ) && ! empty( $requests ) ) {
				$last_activity = max( $requests );
			} else {
				$last_activity = 0;
			}

			$entries[ $key ] = $last_activity;
		}

		// Sort by last activity ascending (oldest first)
		asort( $entries );

		// Remove oldest entries beyond the cap
		$to_remove     = array_slice( array_keys( $entries ), 0, count( $entries ) - $max_entries );
		$removed_count = 0;

		foreach ( $to_remove as $key ) {
			if ( $this->deleteRateLimit( $key ) ) {
				++$removed_count;
			}
		}

		return $removed_count;
	}
}
