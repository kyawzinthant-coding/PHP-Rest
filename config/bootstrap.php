<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(dirname(__DIR__));

// Basic error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$dotenv->load();

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
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

define('CLOUDINARY_CLOUD_NAME', $_ENV['CLOUDINARY_CLOUD_NAME']);
define('CLOUDINARY_API_KEY', $_ENV['CLOUDINARY_API_KEY']);
define('CLOUDINARY_API_SECRET', $_ENV['CLOUDINARY_API_SECRET']);

define('CLOUDINARY_URL', 'cloudinary://245584398211485:9HJk9CnXB68ld-T8Tsp-z38Dbqk@df3jn4uqd');

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
