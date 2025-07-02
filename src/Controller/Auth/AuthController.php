<?php

namespace App\Controller\Auth;

use App\Core\Request;
use App\Service\AuthService;
use App\Validate\AuthValidation;
use App\Exception\ValidationException;
use App\Exception\AuthenticationException;
use App\Repository\DuplicateEntryException;
use App\Repository\User\UserRepository;
use App\Validate\ProfileValidate;

class AuthController
{
    private AuthService $authService;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userRepository = new UserRepository();
    }

    public function register(Request $request): void
    {
        // If your BaseRequest was refactored to take a Request object:
        // $validator = new AuthValidation($request);
        // Otherwise, it will use superglobals:
        $validator = new AuthValidation();

        try {
            $validatedData = $validator->validateRegistration();

            $userId = $this->authService->registerUser($validatedData);


            if ($userId) {
                http_response_code(201); // Created
                echo json_encode([
                    'status' => 'success',
                    'message' => 'User registered successfully.',
                    'data' => ['id' => $userId, 'email' => $validatedData['email'], 'name' => $validatedData['name'], 'role' => $validatedData['role'] ?? 'user']
                ]);
            } else {
                // This case should ideally be caught by more specific exceptions from the service
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'User registration failed.']);
            }
        } catch (ValidationException $e) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->getErrors()]);
        } catch (DuplicateEntryException $e) {
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log("AuthController::register Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred during registration.']);
        }
    }

    public function login(Request $request): void
    {
        $validator = new AuthValidation(); // Or new AuthValidation($request) if refactored

        try {
            $validatedData = $validator->validateLogin();
            $jwt = $this->authService->loginUser($validatedData); // This still generates the token string

            // Set the JWT as an HTTP-Only cookie
            $cookieName = 'accessToken';
            $cookieValue = $jwt;
            $expiry = time() + (int)($_ENV['JWT_EXPIRATION_TIME_SECONDS']);
            $path = '/'; // Available on the entire domain
            $domain = $_ENV['APP_DOMAIN'] ?? ''; // Set to your domain in .env for production, empty for localhost
            $secure = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production'; // True if HTTPS in production
            $httpOnly = true; // Crucial: JavaScript cannot access it
            $sameSite = 'Lax'; // Or 'Strict' or 'None' (if 'None', $secure must be true)

            setcookie($cookieName, $cookieValue, [
                'expires' => $expiry,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ]);

            // You can also send the token in the response body
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful. Access token set in cookie.',
            ]);
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->getErrors()]);
        } catch (AuthenticationException $e) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log("AuthController::login Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred during login.']);
        }
    }

    public function logout(): void
    {
        $cookieName = 'accessToken';
        $path = '/';
        $domain = $_ENV['APP_DOMAIN'] ?? '';
        // To delete a cookie, set its expiration date to the past
        setcookie($cookieName, '', [
            'expires' => time() - 3600, // In the past
            'path' => $path,
            'domain' => $domain,
            'secure' => isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    public function getCurrentUser(Request $request): void
    {
        $authenticatedUserData = $request->getAttribute('user'); // Get from AuthMiddleware

        if (!$authenticatedUserData || !isset($authenticatedUserData->id)) {
            // This case should ideally not happen if AuthMiddleware is effective
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized or user data not found on request.']);
            return;
        }

        // The $authenticatedUserData from JWT payload might be enough.
        // If you need more details from the DB (that are not in the JWT):
        $fullUserDetails = $this->userRepository->findById($authenticatedUserData->id);
        if (!$fullUserDetails) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'User not found in database.']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => [
                'id' => $fullUserDetails['id'],
                'email' => $fullUserDetails['email'],
                'name' => $fullUserDetails['name'] ?? 'Unknown',
                'role' => $fullUserDetails['role'] ?? 'user',
            ]
        ]);
    }

    public function updateProfile(Request $request): void
    {
        $user = $request->getAttribute('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
            return;
        }

        try {
            $validator = new ProfileValidate();
            $validatedData = $validator->validate();

            if (empty($validatedData)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No fields provided for update.']);
                return;
            }

            $success = $this->authService->updateProfile($user->id, $validatedData);

            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully.']);
            } else {
                throw new \RuntimeException('Failed to update profile.');
            }
        } catch (\App\Exception\ValidationException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $e->getErrors()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
