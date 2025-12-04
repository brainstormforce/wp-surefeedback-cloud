<?php
/**
 * WordPress Mocks for Basic Testing
 *
 * @package SureFeedback
 */

// Global storage for mocked options and hooks
global $_wp_options, $_wp_hooks, $_wp_scheduled_hooks;
$_wp_options         = array();
$_wp_hooks           = array();
$_wp_scheduled_hooks = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_wp_options;
		return isset( $_wp_options[ $option ] ) ? $_wp_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $_wp_options;
		$_wp_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $_wp_options;
		unset( $_wp_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
		global $_wp_options;
		if ( ! isset( $_wp_options[ $option ] ) ) {
			$_wp_options[ $option ] = $value;
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		switch ( $type ) {
			case 'mysql':
				return date( 'Y-m-d H:i:s' );
			case 'timestamp':
				return time();
			default:
				return date( $type );
		}
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		global $_wp_scheduled_hooks;
		unset( $_wp_scheduled_hooks[ $hook ] );
		return true;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		global $_wp_scheduled_hooks;
		$_wp_scheduled_hooks[ $hook ] = array(
			'timestamp'  => $timestamp,
			'recurrence' => $recurrence,
			'args'       => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		global $_wp_scheduled_hooks;
		return isset( $_wp_scheduled_hooks[ $hook ] ) ? $_wp_scheduled_hooks[ $hook ]['timestamp'] : false;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		global $_wp_hooks;
		if ( isset( $_wp_hooks[ $hook_name ] ) ) {
			foreach ( $_wp_hooks[ $hook_name ] as $callback ) {
				call_user_func_array( $callback['function'], $args );
			}
		}
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		global $_wp_hooks;
		$_wp_hooks[ $hook_name ][] = array(
			'function'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		add_action( 'activate_' . plugin_basename( $file ), $callback );
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		add_action( 'deactivate_' . plugin_basename( $file ), $callback );
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'http://example.org/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		global $_wp_hooks;
		if ( isset( $_wp_hooks[ $hook_name ] ) ) {
			foreach ( $_wp_hooks[ $hook_name ] as $callback ) {
				$value = call_user_func_array( $callback['function'], array_merge( array( $value ), $args ) );
			}
		}
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'wp_redirect' ) ) {
	function wp_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
		return true;
	}
}

// Base test case class for environments without WordPress test suite
if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
		public function setUp(): void {
			parent::setUp();
		}

		public function tearDown(): void {
			parent::tearDown();
		}
	}
}
