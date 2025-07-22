<?php

namespace App\Middleware;

use App\Core\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AdminAuthMiddleware
{
    public function handle(Request $request, callable $next)
    {
        // This middleware ONLY looks for the admin token.
        $token = $_COOKIE['adminAccessToken'] ?? null;

        if (!$token) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Admin access token not found.']);
            exit;
        }

        $jwtSecretKey = $_ENV['JWT_SECRET_KEY'];

        try {
            $decoded = JWT::decode($token, new Key($jwtSecretKey, 'HS256'));

            // Security check: Ensure the user has the 'admin' role.
            if ($decoded->data->role !== 'admin') {
                http_response_code(403); // Forbidden
                echo json_encode(['status' => 'error', 'message' => 'Forbidden: You do not have permission to perform this action.']);
                exit;
            }

            $request->setAttribute('user', $decoded->data);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: ' . $e->getMessage()]);
            exit;
        }

        return $next($request);
    }
}
