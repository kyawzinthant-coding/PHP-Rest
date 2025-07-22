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
        $validator = new AuthValidation();
        try {
            $validatedData = $validator->validateLogin();
            $user = $this->authService->loginUser($validatedData);

            // Generate the token
            $jwt = $this->authService->generateToken($user['id'], $user['email'], $user['role']);

            // Set the customer-specific cookie
            setcookie('accessToken', $jwt, [
                'expires' => time() + (int)($_ENV['JWT_EXPIRATION_TIME_SECONDS']),
                'path' => '/',
                'domain' => $_ENV['APP_DOMAIN'] ?? '',
                'secure' => ($_ENV['APP_ENV'] ?? 'development') === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Login successful.']);
        } catch (AuthenticationException $e) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
        }
    }


    public function adminLogin(Request $request): void
    {
        $validator = new AuthValidation();
        try {
            $validatedData = $validator->validateLogin();
            $user = $this->authService->loginUser($validatedData);


            if ($user['role'] !== 'admin') {
                throw new AuthenticationException("Forbidden: You do not have permission to log in here.");
            }

            $jwt = $this->authService->generateToken($user['id'], $user['email'], $user['role']);

            // Set the admin-specific cookie
            setcookie('adminAccessToken', $jwt, [
                'expires' => time() + (int)($_ENV['JWT_EXPIRATION_TIME_SECONDS']),
                'path' => '/',
                'domain' => $_ENV['APP_DOMAIN'] ?? '',
                'secure' => ($_ENV['APP_ENV'] ?? 'development') === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Admin login successful.']);
        } catch (AuthenticationException $e) {
            http_response_code(401); // Can also be 403 for the forbidden error
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
        }
    }
    public function logout(): void
    {

        $body = json_decode(file_get_contents('php://input'), true);
        $scope = $body['scope'] ?? 'customer';

        $cookieName = ($scope === 'admin') ? 'adminAccessToken' : 'accessToken';

        $path = '/';
        $domain = $_ENV['APP_DOMAIN'] ?? '';
        // To delete a cookie, set its expiration date to the past
        setcookie($cookieName, '', [
            'expires' => time() - 3600, // In the past
            'path' => '/',
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

        error_log(($fullUserDetails['role'] ?? 'user') . " user with ID " . $fullUserDetails['id'] . " is requesting their profile.");



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

    public function getCurrentAdmin(Request $request): void
    {

        $authenticatedUserData = $request->getAttribute('user');

        error_log("Admin user with ID " . ($authenticatedUserData->id ?? 'unknown') . " is requesting their profile.");

        if (!$authenticatedUserData || !isset($authenticatedUserData->id)) {
            // This case should ideally not happen if AuthMiddleware is effective
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized or user data not found on request.']);
            return;
        }

        $fullUserDetails = $this->userRepository->findById($authenticatedUserData->id);
        if (!$fullUserDetails) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'User not found in database.']);
            return;
        }

        if ($fullUserDetails['role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
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
