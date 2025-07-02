<?php

namespace App\Validate;

class ProfileValidate extends BaseRequest
{
    public function validate(bool $isUpdate = false): array
    {
        $validatedData = [];

        // Validate name only if it was provided
        if ($this->input('name') !== null) {
            $name = trim($this->input('name'));
            if (empty($name)) {
                $this->addError('name', 'Name cannot be empty.');
            }
            $validatedData['name'] = $name;
        }

        // Validate password only if it was provided
        if ($this->input('password') !== null) {
            $password = $this->input('password');
            if (strlen($password) < 8) {
                $this->addError('password', 'New password must be at least 8 characters long.');
            }
            $validatedData['password'] = $password;
        }

        $this->throwValidationException();

        return $validatedData;
    }
}
