<?php

namespace App\Validate;

use App\Core\Request; // Make sure this use statement is present
use App\Exception\ValidationException;

class CategoryValidate extends BaseRequest // Ensure BaseRequest provides error handling & validateImageFile
{
    protected Request $request;
    protected array $dataToValidate;
    protected ?array $imageFileToValidate; // For the uploaded category image


    public function validate(bool $isUpdate = false): array
    {
        if ($isUpdate) {
            return $this->validateCreateCategory();
        } else {
            return $this->validateUpdateCategory();
        }
    }
    public function __construct(Request $request)
    {
        parent::__construct(); // Initialize $this->errors
        $this->request = $request;
        $this->dataToValidate = $this->request->post;
        // Specific file input name for category images from the form
        $this->imageFileToValidate = $this->request->files['category_image'] ?? null;
    }

    // Helper to get input value, trims strings
    private function getInputValue(string $key, $default = null)
    {
        if (!array_key_exists($key, $this->dataToValidate)) {
            return $default;
        }
        $value = $this->dataToValidate[$key];
        return is_string($value) ? trim($value) : $value;
    }

    public function validateCreateCategory(): array
    {
        $validatedData = [];

        // Name (Required)
        $name = $this->getInputValue('name');
        if (empty($name)) {
            $this->addError('name', 'Category name is required.');
        } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->addError('name', 'Category name must be between 3 and 255 characters.');
        }
        $validatedData['name'] = $name;

        // Category Image (Optional for create, but if provided, validate it)
        $validatedData['image_file'] = null;
        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            if (!isset($this->imageFileToValidate['tmp_name']) || !is_uploaded_file($this->imageFileToValidate['tmp_name'])) {
                $this->addError('category_image', 'Invalid category image file upload. Error code: ' . ($this->imageFileToValidate['error'] ?? 'unknown'));
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5 MB
                $this->validateImageFile($this->imageFileToValidate, 'category_image', $allowedTypes, $maxSize);

                if ($this->imageFileToValidate['error'] === UPLOAD_ERR_OK && !isset($this->errors['category_image'])) {
                    $validatedData['image_file'] = $this->imageFileToValidate;
                }
            }
        }

        $this->throwValidationExceptionIfNeeded(); // From BaseRequest

        return $validatedData; // Returns 'name' and 'image_file' (file array or null)
    }

    public function validateUpdateCategory(): array
    {
        $validatedData = [];

        // Name (Optional for update, but if provided, validate it)
        if (array_key_exists('name', $this->dataToValidate)) {
            $name = $this->getInputValue('name');
            if (empty($name)) { // Cannot be updated to empty
                $this->addError('name', 'Category name cannot be empty if provided for update.');
            } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
                $this->addError('name', 'Category name must be between 3 and 255 characters.');
            }
            $validatedData['name'] = $name;
        }

        // Category Image (Optional for update)
        $validatedData['image_file'] = null;
        $validatedData['remove_category_image'] = false;

        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            // A new image is being uploaded
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            $this->validateImageFile($this->imageFileToValidate, 'category_image', $allowedTypes, $maxSize);
            if ($this->imageFileToValidate['error'] === UPLOAD_ERR_OK && !isset($this->errors['category_image'])) {
                $validatedData['image_file'] = $this->imageFileToValidate;
            }
        } elseif (filter_var($this->getInputValue('remove_category_image'), FILTER_VALIDATE_BOOLEAN)) {
            $validatedData['remove_category_image'] = true;
        }

        // If client sends category_cloudinary_public_id as null
        if (array_key_exists('category_cloudinary_public_id', $this->dataToValidate) && $this->dataToValidate['category_cloudinary_public_id'] === null) {
            $validatedData['category_cloudinary_public_id'] = null;
        }

        $this->throwValidationExceptionIfNeeded();
        return $validatedData;
    }

    // Ensure throwValidationExceptionIfNeeded() and other helpers are in BaseRequest
    protected function throwValidationExceptionIfNeeded(): void
    {
        if ($this->hasErrors()) {
            throw new ValidationException('Validation failed.', 400, $this->getErrors());
        }
    }
}
