<?php
/**
 * This file returns an array of package configurations.
 *
 * @package SureFeedback
 */

return array(
	'packages' => array(
		'wordpress' => array(
			'source' => 'https://github.com/WordPress/WordPress.git',
			'tags'   => array( 'v6.6.2' ),
			'output' => __DIR__ . '/tests/php/stubs/wordpress',
		),
	),
);
