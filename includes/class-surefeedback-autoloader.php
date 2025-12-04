<?php
/**
 * Autoloader class
 *
 * @package SureFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class
 */
class SureFeedback_Autoloader {

	/**
	 * Namespace prefix
	 *
	 * @var string
	 */
	private $namespace_prefix = 'SureFeedback\\';

	/**
	 * Base directory for the namespace prefix
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->base_dir = SUREFEEDBACK_PLUGIN_DIR . 'includes/';
		$this->register();
	}

	/**
	 * Register the autoloader
	 */
	public function register() {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload callback
	 *
	 * @param string $class The fully-qualified class name.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
	 */
	public function autoload( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		$len = strlen( $this->namespace_prefix );
		if ( strncmp( $this->namespace_prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative_class ) ) {
			return;
		}

		$file = str_replace( '\\', '/', $relative_class );

		$file = str_replace( '_', '-', $file );

		$file = strtolower( $file );

		if ( strpos( $file, '..' ) !== false || strpos( $file, './' ) !== false ) {
			return;
		}

		$file_parts = explode( '/', $file );
		$class_name = array_pop( $file_parts );

		foreach ( $file_parts as $part ) {
			if ( empty( $part ) || ! preg_match( '/^[a-z0-9\-]+$/', $part ) ) {
				return;
			}
		}

		if ( empty( $class_name ) || ! preg_match( '/^[a-z0-9\-]+$/', $class_name ) ) {
			return;
		}

		if ( strpos( $class_name, 'interface' ) === 0 || strpos( $relative_class, 'Interface' ) !== false ) {
			$file_parts[] = $class_name;
		} else {
			$file_parts[] = 'class-' . $class_name;
		}

		$file = implode( '/', $file_parts );

		$full_path = $this->base_dir . $file . '.php';

		$real_base = realpath( $this->base_dir );
		$real_file = realpath( dirname( $full_path ) );

		if (
			$real_base &&
			$real_file &&
			strpos( $real_file, $real_base ) === 0 &&
			file_exists( $full_path ) &&
			is_readable( $full_path )
		) {
			require_once $full_path;
		}
	}
}

new SureFeedback_Autoloader();
