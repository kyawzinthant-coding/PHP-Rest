<?php

// Basic error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set default timezone (important for date/time functions)
date_default_timezone_set('UTC');

// Define constants for paths (useful later)
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Load environment variables (optional, but good practice for sensitive data)
// For pure PHP, we can either hardcode them here (bad practice for production)
// or load them from a .env file. Let's create a simple .env loader.

// Database Configuration (for now, directly here. We'll improve this)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Your MySQL password

// Helper function for dumping variables (like console.log)
if (!function_exists('dd')) {
    function dd(...$vars)
    {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        exit;
    }
}
