<?php

namespace App\Controller\User;

use App\Core\Request;
use App\Repository\User\UserRepository;

class UserController
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }




    public function index(Request $request): void
    {
        $user = $request->getAttribute('user');
        if (!$user || $user->role !== 'admin') {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: You do not have permission to perform this action.']);
            return;
        }

        $users = $this->userRepository->findAll();

        echo json_encode(['status' => 'success', 'data' => $users]);
    }

    /**
     * ADMIN-ONLY: Updates a user's role.
     */
    public function updateUserRole(Request $request, string $id): void
    {
        $user = $request->getAttribute('user');
        if (!$user || $user->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: You do not have permission to perform this action.']);
            return;
        }

        $data = json_decode($request->body, true);
        $newRole = $data['role'] ?? null;

        // Basic validation
        if (!$newRole || !in_array($newRole, ['customer', 'admin'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid role provided. Must be "customer" or "admin".']);
            return;
        }

        // Prevent an admin from accidentally demoting themselves (a good safety check)
        if ($user->id === $id && $newRole !== 'admin') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'You cannot change your own role.']);
            return;
        }

        $targetUser = $this->userRepository->findById($id);
        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
            return;
        }

        if ($targetUser['email'] === getenv('SUPER_USER_EMAIL')) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'The super user role cannot be changed.']);
            return;
        }

        $success = $this->userRepository->updateRole($id, $newRole);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'User role updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update user role.']);
        }
    }

    public function disableUser(Request $request, string $id): void
    {
        $adminUser = $request->getAttribute('user');
        if (!$adminUser || $adminUser->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: Admins only.']);
            return;
        }

        $targetUser = $this->userRepository->findById($id);
        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
            return;
        }

        if ($targetUser['email'] === getenv('SUPER_USER_EMAIL')) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'The super user cannot be disabled.']);
            return;
        }
        if ($adminUser->id === $id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'You cannot disable your own account.']);
            return;
        }


        $this->userRepository->disable($id);
        echo json_encode(['status' => 'success', 'message' => 'User disabled successfully.']);
    }
}
