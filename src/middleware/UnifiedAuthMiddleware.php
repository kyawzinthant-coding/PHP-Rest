<?php

namespace App\Middleware;

use App\Core\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UnifiedAuthMiddleware
{
    public function handle(Request $request, callable $next)
    {
        // Check for the admin token first, then fall back to the customer token.
        $token = $_COOKIE['adminAccessToken'] ?? $_COOKIE['accessToken'] ?? null;

        $jwtSecretKey = $_ENV['JWT_SECRET_KEY'];

        if (!$token) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Access token not found.']);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($jwtSecretKey, 'HS256'));
            $request->setAttribute('user', $decoded->data);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid token.']);
            exit;
        }

        return $next($request);
    }
}
