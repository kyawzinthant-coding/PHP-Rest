<?php

namespace App\Middleware;

use App\Core\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthMiddleware
{
    public function handle(Request $request, callable $next)
    {

        $token = $_COOKIE['accessToken'] ?? null;


        if (!$token) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Access token not found in cookie.']);
            exit;
        }

        $jwtSecretKey = $_ENV['JWT_SECRET_KEY'];

        try {
            $decoded = JWT::decode($token, new Key($jwtSecretKey, 'HS256'));
            $request->setAttribute('user', $decoded->data); // Store decoded user data
        } catch (Exception $e) {
            http_response_code(401);
            setcookie('accessToken', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => $_ENV['APP_DOMAIN'] ?? '',
                'secure' => isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: ' . $e->getMessage()]);
            exit;
        }

        return $next($request);
    }
}
