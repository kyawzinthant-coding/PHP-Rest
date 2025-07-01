<?php

namespace App\Middleware;

use App\Core\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OptionalAuthMiddleware
{
    public function handle(Request $request, callable $next)
    {
        // Try to read the token from the cookie
        $token = $_COOKIE['accessToken'] ?? null;
        $jwtSecretKey = $_ENV['JWT_SECRET_KEY'];

        if ($token && $jwtSecretKey) {
            try {
                // If a token exists, try to decode it
                $decoded = JWT::decode($token, new Key($jwtSecretKey, 'HS256'));
                // If successful, set the user attribute on the request
                $request->setAttribute('user', $decoded->data);
            } catch (\Exception $e) {
                // If decoding fails (e.g., expired token), do nothing.
                // The user will be treated as a guest.
            }
        }

        // IMPORTANT: Always proceed to the next step, whether a user was found or not.
        return $next($request);
    }
}
