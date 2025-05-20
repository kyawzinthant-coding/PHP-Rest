<?php

// 1. Load Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load configuration (we'll create this next)
require_once __DIR__ . '/../config/bootstrap.php';

use App\Core\Router;
use App\Controller\Product\ProductController;

header('Access-Control-Allow-Origin: *'); // Allow all origins for CORS (change this in production)
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Add any other headers your frontend might send
header('Access-Control-Allow-Credentials: true'); // If you plan to send cookies/auth headers
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Just send the CORS headers and exit for OPTIONS requests
    http_response_code(204); // No Content
    exit();
}

$router = new Router();

// 3. Define routes (we'll create a separate file for this later)
$router->get('/', function () {
    echo json_encode(['message' => 'Welcome to the Pure PHP E-commerce API']);
});

$router->get('/api/v1/products', [ProductController::class, 'index']);
$router->post('/api/v1/products', [ProductController::class, 'store']);

$router->get('/api/v1/products/{id}', [ProductController::class, 'show']);
$router->put('/api/v1/products/{id}', [ProductController::class, 'update']);
$router->delete('/api/v1/products/{id}', [ProductController::class, 'destroy']);

// Get the requested URI and method
// $_SERVER['REQUEST_URI']: Contains the full URI that was requested by the browser (e.g., `/api/v1/products?page=1`).
$requestUri = $_SERVER['REQUEST_URI'];
// $_SERVER['REQUEST_METHOD']: Contains the HTTP method (GET, POST, etc.)
$requestMethod = $_SERVER['REQUEST_METHOD'];


$router->resolve($requestUri, $requestMethod);

// This prevents any further output or unexpected errors if not handled by a route
// exit();
