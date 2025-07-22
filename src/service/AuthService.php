<?php

namespace App\Service;

use App\Repository\User\UserRepository;
use Firebase\JWT\JWT;
use App\Exception\AuthenticationException; // Custom exception for auth failures

class AuthService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function registerUser(array $data): ?string
    {
        $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);
        if ($hashedPassword === false) {
            throw new \RuntimeException("Failed to hash password.");
        }

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $hashedPassword,
            'role' => $data['role'] ?? 'user',
        ];

        return $this->userRepository->create($userData);
    }


    public function loginUser(array $data): array
    {
        $user = $this->userRepository->findByEmail($data['email']);

        if ($user && !$user['is_active']) {
            throw new AuthenticationException("Your account has been disabled.");
        }

        if (!$user || !password_verify($data['password'], $user['password'])) {
            throw new AuthenticationException("Invalid email or password.");
        }

        // User authenticated, generate JWT
        return $user;
    }

    /**
     * Generates a JWT for a user.
     * @param string $userId
     * @param string $email
     * @param string $role
     * @return string JWT
     */
    public function generateToken(string $userId, string $email, string $role): string
    {
        $jwtSecretKey = $_ENV['JWT_SECRET_KEY'] ?? 'your-default-fallback-secret-key-if-not-in-env'; // Ensure this is loaded from .env
        $jwtExpiration = $_ENV['JWT_EXPIRATION_TIME_SECONDS'] ?? 3600; // e.g., 1 hour

        if (empty($jwtSecretKey)) {
            throw new \RuntimeException("JWT_SECRET_KEY is not configured properly.");
        }

        $issuedAt = time();
        $expire = $issuedAt + (int)$jwtExpiration;

        $payload = [
            'iss' => $_SERVER['HTTP_HOST'] ?? 'localhost', // Issuer
            'aud' => $_SERVER['HTTP_HOST'] ?? 'localhost', // Audience
            'iat' => $issuedAt, // Issued at
            'nbf' => $issuedAt, // Not before
            'exp' => $expire,   // Expiration time
            'data' => [         // Custom claims
                'id' => $userId,
                'email' => $email,
                'role' => $role
            ]
        ];

        return JWT::encode($payload, $jwtSecretKey, 'HS256');
    }

    public function updateProfile(string $userId, array $data): bool
    {
        // If a new password is part of the update, hash it before saving.
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }

        return $this->userRepository->update($userId, $data);
    }
}

// Define a custom AuthenticationException if you don't have one
namespace App\Exception;

class AuthenticationException extends \RuntimeException {}
