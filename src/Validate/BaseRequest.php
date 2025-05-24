<?php

namespace App\Validate;

use App\Exception\ValidationException;

abstract class BaseRequest
{
    protected array $data;
    protected ?array $imageFile; // To handle file uploads generally
    protected array $errors = [];

    public function __construct()
    {
        // For POST/PUT/PATCH requests, check both $_POST and raw JSON input
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            $json_data = json_decode($input, true);
            $this->data = array_merge($_POST, $json_data ?: []);
        } else {
            $this->data = $_GET; // For GET requests (e.g., query parameters)
        }

        $this->imageFile = $_FILES['image'] ?? $_FILES['file'] ?? null; // Adjust based on your file input name
    }

    /**
     * Get a specific piece of request data.
     */
    protected function input(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get the uploaded file.
     */


    /**
     * Abstract method to be implemented by child classes for specific validation rules.
     * @param bool $isUpdate Indicates if the validation is for an update operation.
     * @return array Validated data.
     * @throws ValidationException If validation fails.
     */
    abstract public function validate(bool $isUpdate = false): array;

    /**
     * Add an error message.
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    /**
     * Check if there are any validation errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Throw a ValidationException if errors exist.
     */
    protected function throwValidationException(): void
    {
        if ($this->hasErrors()) {
            throw new ValidationException('Validation failed.', 400, $this->errors);
        }
    }

    /**
     * Validate a common image file upload.
     * @param array|null $file The $_FILES array entry for the image.
     * @param string $fieldName The name of the field in the request.
     * @param array $allowedTypes Allowed MIME types.
     * @param int $maxSize Max allowed file size in bytes.
     * @return bool True if valid or no file, false if errors are added.
     */
    protected function validateImageFile(?array $file, string $fieldName, array $allowedTypes, int $maxSize): bool
    {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return true; // No file uploaded, or optional
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'Unknown upload error.';
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'File exceeds configured size limits.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = 'File was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage = 'Missing a temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage = 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage = 'A PHP extension stopped the upload.';
                    break;
            }
            $this->addError($fieldName, 'Image upload error: ' . $errorMessage . ' (Code: ' . $file['error'] . ').');
            return false;
        }

        if (!in_array($file['type'], $allowedTypes)) {
            $this->addError($fieldName, 'Invalid image file type. Only ' . implode(', ', $allowedTypes) . ' are allowed.');
            return false;
        }

        if ($file['size'] > $maxSize) {
            $this->addError($fieldName, 'Image file size exceeds ' . ($maxSize / (1024 * 1024)) . 'MB limit.');
            return false;
        }

        return true;
    }
}
