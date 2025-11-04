<?php
/**
 * Stubs Generator Helper Script
 *
 * This script helps generate PHP stubs for SureFeedback plugin classes
 * to help PHPStan understand the codebase structure.
 *
 * The stubs are generated using the php-stubs/generator tool directly
 * on the includes/app directory.
 *
 * Usage:
 *   Composer: composer run gen-stubs
 *   Direct:   vendor/bin/generate-stubs includes/app --out=tests/php/stubs/surefeedback-stubs.php --force
 *
 * @package SureFeedback
 * @since 0.0.1
 */

// This file serves as documentation and configuration reference
// The actual stub generation uses direct directory paths via composer script

// Configuration for stub generation
return array(
	'source_directory' => __DIR__ . '/includes/app',
	'output_file'      => __DIR__ . '/tests/php/stubs/surefeedback-stubs.php',
	'exclude_patterns' => array(
		'*Test.php',
		'*test.php',
		'*Mock.php',
		'*mock.php',
	),
	'description'      => 'SureFeedback Plugin Stubs for PHPStan',
	'version'          => '0.0.1',
);
