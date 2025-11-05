<?php

namespace SureFeedback\Repositories;

/**
 * Base Repository - Provides base functionality for data access operations
 *
 * @package SureFeedback
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base Repository Class
 *
 * Provides base functionality for data access operations
 * in WordPress environment using WordPress options API.
 *
 * @package SureFeedback\App\Repositories
 * @author Anurag Singh <anurags@bsf.io>
 */
abstract class BaseRepository {

	/**
	 * The option prefix for this repository
	 *
	 * @var string
	 */
	protected $prefix = 'surefeedback_';

	/**
	 * Cache for loaded options
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Get option with caching
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @param bool   $useCache
	 * @return mixed
	 */
	protected function getOption( string $key, $default = null, bool $useCache = true ) {
		$fullKey = $this->prefix . $key;

		if ( $useCache && isset( $this->cache[ $fullKey ] ) ) {
			return $this->cache[ $fullKey ];
		}

		$value = get_option( $fullKey, $default );

		if ( $useCache ) {
			$this->cache[ $fullKey ] = $value;
		}

		return $value;
	}

	/**
	 * Set option with cache update
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return bool
	 */
	protected function setOption( string $key, $value ): bool {
		$fullKey = $this->prefix . $key;

		$result = update_option( $fullKey, $value );

		if ( $result ) {
			$this->cache[ $fullKey ] = $value;
		}

		return $result;
	}

	/**
	 * Delete option with cache removal
	 *
	 * @param string $key
	 * @return bool
	 */
	protected function deleteOption( string $key ): bool {
		$fullKey = $this->prefix . $key;

		$result = delete_option( $fullKey );

		if ( $result ) {
			unset( $this->cache[ $fullKey ] );
		}

		return $result;
	}

	/**
	 * Get multiple options with a pattern
	 *
	 * This method uses wp_load_alloptions() to retrieve all options,
	 * then filters them by the pattern. This is safe and WordPress-approved.
	 *
	 * @param string $pattern Pattern to match (e.g., 'prefix_%' will match all keys starting with 'prefix_')
	 * @return array Associative array of matched options
	 */
	protected function getOptionsWithPattern( string $pattern ): array {
		$fullPattern = $this->prefix . $pattern;
		$cache_key   = 'surefeedback_options_pattern_' . md5( $fullPattern );

		// Try to get from cache first
		$cached = wp_cache_get( $cache_key, 'surefeedback_options' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Load all options using WordPress core function
		$all_options = wp_load_alloptions();

		// Convert SQL LIKE pattern to regex pattern
		// % becomes .* and _ becomes .
		$regex_pattern = str_replace( array( '%', '_' ), array( '.*', '.' ), preg_quote( $fullPattern, '/' ) );

		// Filter options by pattern
		$options = array();
		foreach ( $all_options as $option_name => $option_value ) {
			if ( preg_match( '/^' . $regex_pattern . '$/', $option_name ) ) {
				$key             = str_replace( $this->prefix, '', $option_name );
				$options[ $key ] = maybe_unserialize( $option_value );
			}
		}

		// Cache the results for 1 hour
		wp_cache_set( $cache_key, $options, 'surefeedback_options', HOUR_IN_SECONDS );

		return $options;
	}

	/**
	 * Set multiple options at once
	 *
	 * @param array $options
	 * @return bool
	 */
	protected function setMultipleOptions( array $options ): bool {
		$success = true;

		foreach ( $options as $key => $value ) {
			if ( ! $this->setOption( $key, $value ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Clear cache for this repository
	 *
	 * @return void
	 */
	public function clearCache(): void {
		$this->cache = array();
	}

	/**
	 * Get transient with caching
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	protected function getTransient( string $key, $default = null ) {
		$fullKey = $this->prefix . $key;

		$value = get_transient( $fullKey );

		return $value !== false ? $value : $default;
	}

	/**
	 * Set transient
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param int    $expiration
	 * @return bool
	 */
	protected function setTransient( string $key, $value, int $expiration = 3600 ): bool {
		$fullKey = $this->prefix . $key;

		return set_transient( $fullKey, $value, $expiration );
	}

	/**
	 * Delete transient
	 *
	 * @param string $key
	 * @return bool
	 */
	protected function deleteTransient( string $key ): bool {
		$fullKey = $this->prefix . $key;

		return delete_transient( $fullKey );
	}

	/**
	 * Sanitize data before storage
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	protected function sanitizeData( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'sanitizeData' ), $data );
		}

		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		return $data;
	}

	/**
	 * Validate data structure
	 *
	 * @param array $data
	 * @param array $required
	 * @return bool
	 */
	protected function validateDataStructure( array $data, array $required ): bool {
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}
}
