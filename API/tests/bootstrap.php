<?php

/**
 * Test Bootstrap File
 * 
 * Sets up the test environment and initializes required dependencies
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env.testing if it exists
$envTestingPath = __DIR__ . '/../.env.testing';
if (file_exists($envTestingPath)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.testing');
    $dotenv->load();
}

// Set up error handling for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Define test constants
define('TEST_MODE', true);
define('TEST_DATABASE', $_ENV['DB_NAME'] ?? 'ordinatrack_test');

// Suppress output buffering for tests
if (ob_get_level() > 0) {
    ob_end_clean();
}
