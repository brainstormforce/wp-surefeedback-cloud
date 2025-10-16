<?php

/**
 * Bootstrap the application
 *
 * @package SureFeedback
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use SureFeedback\Application;

// Create application instance
$app = new Application(dirname(__DIR__));

return $app;