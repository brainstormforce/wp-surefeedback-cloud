#!/usr/bin/env php
<?php
/**
 * Simple Test Runner for SureFeedback Plugin
 * This allows running tests without the full WordPress test environment
 *
 * @package SureFeedback
 */

// Set up constants
define( 'SUREFEEDBACK_TESTS', true );
define( 'ABSPATH', dirname( __DIR__, 1 ) . '/' );
define( 'SUREFEEDBACK_PLUGIN_DIR', dirname( __DIR__, 1 ) . '/' );
define( 'SUREFEEDBACK_PLUGIN_URL', 'http://example.org/wp-content/plugins/surefeedback/' );
define( 'SUREFEEDBACK_PLUGIN_FILE', dirname( __DIR__, 1 ) . '/surefeedback-cloud.php' );
define( 'SUREFEEDBACK_VERSION', '1.0.0' );
define( 'SUREFEEDBACK_PLUGIN_BASENAME', 'surefeedback/surefeedback-cloud.php' );

// Load WordPress mocks
require_once __DIR__ . '/mocks/wordpress-mocks.php';

// Load test case base class
require_once __DIR__ . '/includes/class-surefeedback-test-case.php';

// Simple test runner
class Simple_Test_Runner {
	private $tests_passed = 0;
	private $tests_failed = 0;
	private $failures     = array();

	public function run_tests( $directory ) {
		echo "SureFeedback Plugin Test Runner\n";
		echo "===============================\n\n";

		$test_files = glob( $directory . '/*.php' );

		foreach ( $test_files as $test_file ) {
			echo 'Running tests from: ' . basename( $test_file ) . "\n";
			require_once $test_file;
		}

		// Get all test classes
		$classes      = get_declared_classes();
		$test_classes = array_filter(
			$classes,
			function ( $class ) {
				return strpos( $class, 'Test_' ) === 0;
			}
		);

		foreach ( $test_classes as $test_class ) {
			$this->run_test_class( $test_class );
		}

		$this->print_summary();
	}

	private function run_test_class( $class_name ) {
		echo "\n--- Testing: $class_name ---\n";

		$reflection = new ReflectionClass( $class_name );
		$methods    = $reflection->getMethods( ReflectionMethod::IS_PUBLIC );

		foreach ( $methods as $method ) {
			if ( strpos( $method->getName(), 'test_' ) === 0 ) {
				$this->run_test_method( $class_name, $method->getName() );
			}
		}
	}

	private function run_test_method( $class_name, $method_name ) {
		try {
			$instance = new $class_name();

			// Run setUp if it exists
			if ( method_exists( $instance, 'setUp' ) ) {
				$instance->setUp();
			}

			// Run the test
			$instance->$method_name();

			// Run tearDown if it exists
			if ( method_exists( $instance, 'tearDown' ) ) {
				$instance->tearDown();
			}

			echo "  ✓ $method_name\n";
			++$this->tests_passed;

		} catch ( Exception $e ) {
			echo "  ✗ $method_name - " . $e->getMessage() . "\n";
			++$this->tests_failed;
			$this->failures[] = "$class_name::$method_name - " . $e->getMessage();
		}
	}

	private function print_summary() {
		echo "\n===============================\n";
		echo "Test Summary:\n";
		echo "Passed: {$this->tests_passed}\n";
		echo "Failed: {$this->tests_failed}\n";

		if ( ! empty( $this->failures ) ) {
			echo "\nFailures:\n";
			foreach ( $this->failures as $failure ) {
				echo "- $failure\n";
			}
		}

		echo "\n";
		exit( $this->tests_failed > 0 ? 1 : 0 );
	}
}

// Run tests
$runner   = new Simple_Test_Runner();
$test_dir = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/unit';
$runner->run_tests( $test_dir );
