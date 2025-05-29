<?php

namespace App\Validate;

use App\Core\Request; // If you refactored BaseRequest to use it

class AuthValidation extends BaseRequest
{


    public function validate(bool $isUpdate = false): array
    {
        if ($isUpdate) {
            return $this->validateRegistration();
        } else {
            return $this->validateLogin();
        }
    }

    public function validateRegistration(): array
    {

        $email = filter_var(trim($this->input('email')), FILTER_SANITIZE_EMAIL);
        $name = trim($this->input('name'));
        $password = $this->input('password');
        $role = $this->input('role', 'user'); // Default to 'user' if not provided

        if (empty($name)) {
            $this->addError('name', 'Name is required.');
        } elseif (strlen($name) < 3 || strlen($name) > 255) {
            $this->addError('name', 'Name must be between 3 and 255 characters.');
        }

        if (empty($email)) {
            $this->addError('email', 'Email is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Invalid email format.');
        }

        if (empty($password)) {
            $this->addError('password', 'Password is required.');
        } elseif (strlen($password) < 8) {
            $this->addError('password', 'Password must be at least 8 characters long.');
        }



        $this->throwValidationException(); // Throws exception if errors exist

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ];
    }

    public function validateLogin(): array
    {
        $email = filter_var(trim($this->input('email')), FILTER_SANITIZE_EMAIL);
        $password = $this->input('password');

        if (empty($email)) {
            $this->addError('email', 'Email is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Invalid email format.');
        }

        if (empty($password)) {
            $this->addError('password', 'Password is required.');
        }

        $this->throwValidationException(); // Throws exception if errors exist

        return [
            'email' => $email,
            'password' => $password,
        ];
    }
}
