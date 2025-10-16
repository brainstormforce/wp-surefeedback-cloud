<?php
/**
 * PHPUnit Bootstrap File for SureFeedback Plugin Tests
 *
 * @package SureFeedback
 */

// Define test environment constants
define('SUREFEEDBACK_TESTS', true);
define('WP_TESTS_CONFIG_FILE_PATH', dirname(__FILE__) . '/wp-tests-config.php');

// Prevent WordPress from trying to access the database during tests
if (!defined('WP_INSTALLING')) {
    define('WP_INSTALLING', true);
}

// Load WordPress test functions
if (file_exists('/tmp/wordpress-tests-lib/includes/functions.php')) {
    require_once '/tmp/wordpress-tests-lib/includes/functions.php';
} else {
    // Fallback for local development
    echo "WordPress test library not found. Please install it using:\n";
    echo "bash tests/bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
    exit(1);
}

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Define constants that would normally be set by WordPress
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(dirname(dirname(__FILE__))) . '/');
    }
    
    if (!defined('SUREFEEDBACK_PLUGIN_DIR')) {
        define('SUREFEEDBACK_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
    }
    
    if (!defined('SUREFEEDBACK_PLUGIN_URL')) {
        define('SUREFEEDBACK_PLUGIN_URL', 'http://example.org/wp-content/plugins/surefeedback/');
    }
    
    if (!defined('SUREFEEDBACK_PLUGIN_FILE')) {
        define('SUREFEEDBACK_PLUGIN_FILE', dirname(dirname(__FILE__)) . '/surefeedback.php');
    }
    
    if (!defined('SUREFEEDBACK_VERSION')) {
        define('SUREFEEDBACK_VERSION', '1.0.0');
    }
    
    if (!defined('SUREFEEDBACK_PLUGIN_BASENAME')) {
        define('SUREFEEDBACK_PLUGIN_BASENAME', 'surefeedback/surefeedback.php');
    }
    
    // Load the plugin
    require dirname(dirname(__FILE__)) . '/surefeedback.php';
}

// Load the plugin after WordPress is loaded
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
if (file_exists('/tmp/wordpress-tests-lib/includes/bootstrap.php')) {
    require '/tmp/wordpress-tests-lib/includes/bootstrap.php';
} else {
    // Mock WordPress functions for basic testing
    require_once dirname(__FILE__) . '/mocks/wordpress-mocks.php';
}

// Include test utilities
require_once dirname(__FILE__) . '/includes/class-surefeedback-test-case.php';