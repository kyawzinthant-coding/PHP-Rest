<?php

namespace App\Exception;

use RuntimeException;

/**
 * Custom exception for validation errors.
 */
class ValidationException extends RuntimeException
{
    protected array $errors = [];

    public function __construct(string $message = "", int $code = 0, array $errors = [])
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
