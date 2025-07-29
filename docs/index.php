<?php

/**
 * FlexCMS - Modular Content Management System
 * Entry Point
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Load Composer autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv->load();
}

// Start the application
try {
    $app = new FlexCMS\Core\Application();
    $app->run();
} catch (Exception $e) {
    if (env('APP_DEBUG', false)) {
        echo '<h1>Application Error</h1>';
        echo '<p>' . $e->getMessage() . '</p>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    } else {
        echo '<h1>Something went wrong</h1>';
        echo '<p>Please try again later.</p>';
    }
}