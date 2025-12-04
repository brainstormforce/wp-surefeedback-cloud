<?php
/**
 * Secure Cookie Manager
 *
 * @package SureFeedback
 */

namespace SureFeedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secure Cookie Manager class
 *
 * Provides secure cookie storage with encryption, HMAC authentication,
 * and proper key management for sensitive data like auth tokens.
 */
class Secure_Cookie_Manager {

	/**
	 * Encryption method
	 */
	private const CIPHER_METHOD = 'AES-256-GCM';

	/**
	 * Cookie prefix for all secure cookies
	 */
	private const COOKIE_PREFIX = 'surefeedback_secure_';

	/**
	 * Default cookie expiration in seconds (30 days)
	 */
	private const DEFAULT_EXPIRY = 2592000;

	/**
	 * Option name for storing encrypted keys
	 */
	private const KEY_OPTION = 'surefeedback_encryption_keys';

	/**
	 * Cache group for keys
	 */
	private const CACHE_GROUP = 'surefeedback_secure_cookies';

	/**
	 * Instance of the class
	 *
	 * @var Secure_Cookie_Manager|null
	 */
	private static $instance = null;

	/**
	 * Encryption keys cache
	 *
	 * @var array|null
	 */
	private $keys_cache = null;

	/**
	 * Get singleton instance
	 *
	 * @return Secure_Cookie_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get cookie domain for subdomain support
	 *
	 * @return string Cookie domain (empty string for current domain, or .domain.com for subdomains).
	 */
	public function get_cookie_domain() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		if ( empty( $host ) ) {
			return '';
		}

		$host = explode( ':', $host )[0];

		if ( 'localhost' === $host || filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		$parts = explode( '.', $host );

		if ( count( $parts ) < 2 ) {
			return '';
		}

		if ( count( $parts ) >= 2 ) {
			$main_domain = $parts[ count( $parts ) - 2 ] . '.' . $parts[ count( $parts ) - 1 ];
			return '.' . $main_domain;
		}

		return '';
	}

	/**
	 * Set an encrypted cookie
	 *
	 * @param string $name    Cookie name (will be prefixed).
	 * @param mixed  $value   Value to encrypt (will be JSON encoded if not string).
	 * @param int    $expiry  Expiration time in seconds from now.
	 * @param array  $options Additional cookie options.
	 * @return bool True on success, false on failure.
	 */
	public function set_secure_cookie( string $name, $value, int $expiry = self::DEFAULT_EXPIRY, array $options = array() ) {
		try {
			if ( ! is_string( $value ) ) {
				$value = wp_json_encode( $value );
				if ( false === $value ) {
					return false;
				}
			}

			$keys = $this->get_encryption_keys();
			if ( ! $keys ) {
				return false;
			}

			$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
			$iv        = openssl_random_pseudo_bytes( $iv_length );
			if ( false === $iv ) {
				return false;
			}

			$tag       = '';
			$encrypted = openssl_encrypt(
				$value,
				self::CIPHER_METHOD,
				$keys['encryption_key'],
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false === $encrypted ) {
				return false;
			}

			$payload = array(
				'v' => 1, // Version for future compatibility.
				'i' => base64_encode( $iv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'd' => base64_encode( $encrypted ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				't' => base64_encode( $tag ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'e' => time() + $expiry, // Expiration timestamp.
			);

			$payload_string = wp_json_encode( $payload );
			$signature      = hash_hmac( 'sha256', $payload_string, $keys['signing_key'] );

			$cookie_value = base64_encode( $payload_string . '.' . $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			$cookie_options = wp_parse_args(
				$options,
				array(
					'expires'  => time() + $expiry,
					'path'     => '/',
					'domain'   => $this->get_cookie_domain(),
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);

			return setcookie(
				self::COOKIE_PREFIX . $name,
				$cookie_value,
				$cookie_options
			);
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get and decrypt a secure cookie
	 *
	 * @param string $name Cookie name (without prefix).
	 * @param bool   $json_decode Whether to JSON decode the result.
	 * @return mixed Decrypted value or null on failure.
	 */
	public function get_secure_cookie( string $name, bool $json_decode = true ) {
		$cookie_name = self::COOKIE_PREFIX . $name;

		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return null;
		}

		try {
			$raw_cookie = wp_unslash( $_COOKIE[ $cookie_name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$sanitized_cookie = preg_replace( '/[^a-zA-Z0-9\/+=\r\n]/', '', $raw_cookie );

			if ( empty( $sanitized_cookie ) ) {
				return null;
			}

			$cookie_value = base64_decode( $sanitized_cookie, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $cookie_value ) {
				return null;
			}

			$parts = explode( '.', $cookie_value, 2 );
			if ( 2 !== count( $parts ) ) {
				return null;
			}

			list($payload_string, $signature) = $parts;

			$keys = $this->get_encryption_keys();
			if ( ! $keys ) {
				return null;
			}

			$expected_signature = hash_hmac( 'sha256', $payload_string, $keys['signing_key'] );
			if ( ! hash_equals( $expected_signature, $signature ) ) {
				return null;
			}

			$payload = json_decode( $payload_string, true );
			if ( ! is_array( $payload ) || ! isset( $payload['v'], $payload['i'], $payload['d'], $payload['t'], $payload['e'] ) ) {
				return null;
			}

			if ( $payload['e'] < time() ) {
				return null;
			}

			$iv        = base64_decode( $payload['i'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$encrypted = base64_decode( $payload['d'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$tag       = base64_decode( $payload['t'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $iv || false === $encrypted || false === $tag ) {
				return null;
			}

			$decrypted = openssl_decrypt(
				$encrypted,
				self::CIPHER_METHOD,
				$keys['encryption_key'],
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false === $decrypted ) {
				return null;
			}

			if ( $json_decode ) {
				$decoded = json_decode( $decrypted, true );
				return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $decrypted;
			}

			return $decrypted;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Delete a secure cookie
	 *
	 * @param string $name Cookie name (without prefix).
	 * @return bool
	 */
	public function delete_secure_cookie( string $name ) {
		$cookie_name = self::COOKIE_PREFIX . $name;

		$result = setcookie(
			$cookie_name,
			'',
			time() - 3600,
			'/',
			$this->get_cookie_domain(),
			is_ssl(),
			true
		);

		unset( $_COOKIE[ $cookie_name ] );

		return $result;
	}

	/**
	 * Store auth token securely
	 *
	 * @param string $token Auth token to store.
	 * @param int    $expiry Expiration in seconds.
	 * @return bool
	 */
	public function store_auth_token( string $token, int $expiry = 2592000 ) {
		$token_data = array(
			'token'      => $token,
			'created_at' => time(),
			'expires_at' => time() + $expiry,
		);

		return $this->set_secure_cookie( 'auth_token', $token_data, $expiry );
	}

	/**
	 * Get stored auth token
	 *
	 * @return string|null Auth token or null if not found/expired.
	 */
	public function get_auth_token() {
		$token_data = $this->get_secure_cookie( 'auth_token' );

		if ( ! is_array( $token_data ) || ! isset( $token_data['token'] ) ) {
			return null;
		}

		if ( isset( $token_data['expires_at'] ) && $token_data['expires_at'] < time() ) {
			$this->delete_secure_cookie( 'auth_token' );
			return null;
		}

		return $token_data['token'];
	}

	/**
	 * Get encryption keys
	 *
	 * @return array|false Array with 'encryption_key' and 'signing_key' or false on failure.
	 */
	private function get_encryption_keys() {
		if ( null !== $this->keys_cache ) {
			return $this->keys_cache;
		}

		$keys = wp_cache_get( 'encryption_keys', self::CACHE_GROUP );
		if ( false !== $keys && is_array( $keys ) ) {
			$this->keys_cache = $keys;
			return $keys;
		}

		$stored_keys = get_option( self::KEY_OPTION );

		if ( false === $stored_keys || ! is_array( $stored_keys ) ) {
			$keys = $this->generate_keys();
			if ( false === $keys ) {
				return false;
			}

			if ( ! $this->store_keys( $keys ) ) {
				return false;
			}
		} else {
			$keys = $this->decrypt_stored_keys( $stored_keys );
			if ( false === $keys ) {
				return false;
			}
		}

		wp_cache_set( 'encryption_keys', $keys, self::CACHE_GROUP, 3600 );
		$this->keys_cache = $keys;

		return $keys;
	}

	/**
	 * Generate new encryption keys
	 *
	 * @return array|false
	 */
	private function generate_keys() {
		try {
			$encryption_key = openssl_random_pseudo_bytes( 32 );
			$signing_key    = openssl_random_pseudo_bytes( 32 );

			if ( false === $encryption_key || false === $signing_key ) {
				return false;
			}

			return array(
				'encryption_key' => $encryption_key,
				'signing_key'    => $signing_key,
			);
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Store keys securely in database
	 *
	 * @param array $keys Keys to store.
	 * @return bool
	 */
	private function store_keys( array $keys ) {
		try {
			$master_key = $this->derive_master_key();

			$iv  = openssl_random_pseudo_bytes( 16 );
			$tag = '';

			$encrypted_keys = openssl_encrypt(
				serialize( $keys ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				self::CIPHER_METHOD,
				$master_key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false === $encrypted_keys ) {
				return false;
			}

			$stored_data = array(
				'iv'      => base64_encode( $iv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'data'    => base64_encode( $encrypted_keys ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'tag'     => base64_encode( $tag ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'created' => time(),
			);

			return update_option( self::KEY_OPTION, $stored_data, false );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Decrypt stored keys
	 *
	 * @param array $stored_data Stored encrypted data.
	 * @return array|false
	 */
	private function decrypt_stored_keys( array $stored_data ) {
		try {
			if ( ! isset( $stored_data['iv'], $stored_data['data'], $stored_data['tag'] ) ) {
				return false;
			}

			$master_key = $this->derive_master_key();

			$iv        = base64_decode( $stored_data['iv'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$encrypted = base64_decode( $stored_data['data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$tag       = base64_decode( $stored_data['tag'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $iv || false === $encrypted || false === $tag ) {
				return false;
			}

			$decrypted = openssl_decrypt(
				$encrypted,
				self::CIPHER_METHOD,
				$master_key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false === $decrypted ) {
				return false;
			}

			$keys = unserialize( $decrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			if ( ! is_array( $keys ) || ! isset( $keys['encryption_key'], $keys['signing_key'] ) ) {
				return false;
			}

			return $keys;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Derive master key from WordPress salts
	 *
	 * @return string
	 */
	private function derive_master_key() {
		$salt_data = '';
		$salts     = array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' );

		foreach ( $salts as $salt ) {
			if ( defined( $salt ) ) {
				$salt_data .= constant( $salt );
			}
		}

		if ( empty( $salt_data ) ) {
			$salt_data = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		}

		return hash_pbkdf2( 'sha256', $salt_data, 'surefeedback_cookie_encryption', 10000, 32, true );
	}

	/**
	 * Rotate encryption keys
	 *
	 * @return bool
	 */
	public function rotate_keys() {
		wp_cache_delete( 'encryption_keys', self::CACHE_GROUP );
		$this->keys_cache = null;

		$new_keys = $this->generate_keys();
		if ( false === $new_keys ) {
			return false;
		}

		return $this->store_keys( $new_keys );
	}
}
