<?php

// 1. Load Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load configuration
require_once __DIR__ . '/../config/bootstrap.php';

use App\Controller\Auth\AuthController;
use App\Controller\Category\CategoryController;
use App\Core\Router;
use App\Controller\Product\ProductController;
use App\Exception\ValidationException;
use App\Middleware\AuthMiddleware;
use App\Controller\Brand\BrandController;
use App\Controller\Discount\DiscountController;
use App\Controller\Order\OrderController;
use App\Controller\Review\ReviewController;
use App\Controller\User\UserController;
use App\Repository\DuplicateEntryException;
use App\Controller\Wishlist\WishlistController;
use App\Middleware\OptionalAuthMiddleware;

$allowed_origins = [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://localhost:5177'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
};

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Add any other headers your frontend might send
header('Access-Control-Allow-Credentials: true'); // If you plan to send cookies/auth headers

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Just send the CORS headers and exit for OPTIONS requests
    http_response_code(204); // No Content
    exit();
}

// Set Content-Type for all other requests that will return JSON
header('Content-Type: application/json');

$router = new Router();

// 3. Define routes
$router->get('/', function () {
    echo json_encode(['message' => 'Welcome to the Pure PHP E-commerce API']);
    exit; // Good practice to exit after a direct response
});

// product controller
$router->get('/api/v1/products',  [ProductController::class, 'index'], [OptionalAuthMiddleware::class]);
$router->post('/api/v1/products', [ProductController::class, 'store']);
$router->get('/api/v1/products/discounted', [ProductController::class, 'getDiscountedProducts'], [OptionalAuthMiddleware::class]);
$router->get('/api/v1/products/{id}', [ProductController::class, 'GetProductById'], [OptionalAuthMiddleware::class]);
$router->post('/api/v1/products/{id}', [ProductController::class, 'update']);
$router->delete('/api/v1/products/{id}', [ProductController::class, 'destroy']);
$router->get('/api/v1/products/categoryId/{id}', [ProductController::class, 'getProductsByCategoryId']);


// Auth routes
$router->post('/api/v1/auth/register', [AuthController::class, 'register']);
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);
$router->post('/api/v1/auth/logout', [AuthController::class, 'logout']); // Add logout route

// Route to get current user info (requires authentication)
$router->get('/api/v1/auth/me', [AuthController::class, 'getCurrentUser'], [AuthMiddleware::class]);
$router->put('/api/v1/auth/me', [AuthController::class, 'updateProfile'], [AuthMiddleware::class]);

// Category routes
$router->get('/api/v1/category', [CategoryController::class, 'index'],);
$router->post('/api/v1/category', [CategoryController::class, 'create']);
$router->delete('/api/v1/category/{id}', [CategoryController::class, 'delete']);
$router->post('/api/v1/category/{id}', [CategoryController::class, 'update']);


// Brand routes
$router->get('/api/v1/brand', [BrandController::class, 'index']);
$router->post('/api/v1/brand', [BrandController::class, 'create']);
$router->post('/api/v1/brand/{id}', [BrandController::class, 'update']);
$router->delete('/api/v1/brand/{id}', [BrandController::class, 'delete']);


$router->get('/api/v1/filter-type', [ProductController::class, 'getCategoryAndBrand']);

// Wishlist routes
$router->get('/api/v1/wishlist', [WishlistController::class, 'getWishlist'], [AuthMiddleware::class]);
$router->post('/api/v1/wishlist/{productId}', [WishlistController::class, 'toggleWishlistItem'], [AuthMiddleware::class]);

// Review routes
$router->get('/api/v1/products/{productId}/reviews', [ReviewController::class, 'getReviewsForProduct']);
$router->post('/api/v1/products/{productId}/reviews', [ReviewController::class, 'create'], [AuthMiddleware::class]);

// Discount 
$router->get('/api/v1/admin/discounts', [DiscountController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/v1/admin/discounts', [DiscountController::class, 'create'], [AuthMiddleware::class]);
$router->get('/api/v1/admin/discounts/{id}', [DiscountController::class, 'getById'], [AuthMiddleware::class]);
$router->put('/api/v1/admin/discounts/{id}', [DiscountController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/v1/admin/discounts/{id}', [DiscountController::class, 'delete'], [AuthMiddleware::class]);

$router->post('/api/v1/discounts/apply', [DiscountController::class, 'apply']);

//order management

$router->post('/api/v1/orders', [OrderController::class, 'create'], [AuthMiddleware::class]);
$router->get('/api/v1/orders', [OrderController::class, 'getOrders'], [AuthMiddleware::class]);
$router->get('/api/v1/orders/{id}', [OrderController::class, 'getOrderById'], [AuthMiddleware::class]);
$router->put('/api/v1/admin/orders/{id}/status', [OrderController::class, 'updateStatus'], [AuthMiddleware::class]); // Admin-specific endpoint


// user management
$router->get('/api/v1/admin/users', [UserController::class, 'index'], [AuthMiddleware::class]);
$router->put('/api/v1/admin/users/{id}/role', [UserController::class, 'updateUserRole'], [AuthMiddleware::class]);
$router->delete('/api/v1/admin/users/{id}', [UserController::class, 'disableUser'], [AuthMiddleware::class]);

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

try {
    $router->resolve($requestUri, $requestMethod);
} catch (ValidationException $e) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'errors' => $e->getErrors()
    ]);
    exit; // Terminate script
} catch (DuplicateEntryException $e) {
    http_response_code(409); // Conflict
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit; // Terminate script
} catch (RuntimeException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'A runtime error occurred: ' . $e->getMessage()
    ]);
    error_log("Runtime Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
    exit; // Terminate script
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
    error_log("Unexpected Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
    exit; // Terminate script
} catch (Throwable $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred: ' . $e->getMessage(),
        'dev_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    error_log("Unexpected Error/Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    exit;
}

// No code should execute here if a route was matched and handled, or an exception was caught.
// If Router::notFound() is called, it will exit.