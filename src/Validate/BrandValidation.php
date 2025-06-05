<?php

namespace App\Validate;

use App\Core\Request; // Make sure this use statement is present
use App\Exception\ValidationException;

class BrandValidation extends BaseRequest // Ensure BaseRequest provides error handling & validateImageFile
{
    protected Request $request;
    protected array $dataToValidate;
    protected ?array $imageFileToValidate; // For the uploaded brand image

    public function __construct(Request $request)
    {
        parent::__construct(); // Initialize $this->errors
        $this->request = $request;
        $this->dataToValidate = $this->request->post;
        // Specific file input name for brand images from the form
        $this->imageFileToValidate = $this->request->files['brand_image'] ?? null;
    }

    public function validate(bool $isUpdate = false): array
    {
        if ($isUpdate) {
            return $this->validateCreateBrand();
        } else {
            return $this->validateUpdateBrand();
        }
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

    public function validateCreateBrand(): array
    {
        $validatedData = [];

        // Name (Required)
        $name = $this->getInputValue('name');
        if (empty($name)) {
            $this->addError('name', 'Brand name is required.');
        } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->addError('name', 'Brand name must be between 3 and 255 characters.');
        }
        $validatedData['name'] = $name;

        // Brand Image (Required for create as per your original logic)
        if (empty($this->imageFileToValidate) || $this->imageFileToValidate['error'] === UPLOAD_ERR_NO_FILE) {
            $this->addError('brand_image', 'Brand image is required.');
            $validatedData['image_file'] = null;
        } elseif (!isset($this->imageFileToValidate['tmp_name']) || !is_uploaded_file($this->imageFileToValidate['tmp_name'])) {
            $this->addError('brand_image', 'Invalid brand image file upload. Error code: ' . ($this->imageFileToValidate['error'] ?? 'unknown'));
            $validatedData['image_file'] = null;
        } else {
            // Perform validation using the method from BaseRequest
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            $this->validateImageFile($this->imageFileToValidate, 'brand_image', $allowedTypes, $maxSize);

            // Only include the file if it's OK and no validation errors specific to it were added
            if ($this->imageFileToValidate['error'] === UPLOAD_ERR_OK && !isset($this->errors['brand_image'])) {
                $validatedData['image_file'] = $this->imageFileToValidate;
            } else {
                $validatedData['image_file'] = null; // Don't pass if there was an error
            }
        }

        $this->throwValidationExceptionIfNeeded(); // From BaseRequest

        return $validatedData; // Returns 'name' and 'image_file' (file array or null)
    }


    public function validateUpdateBrand(): array
    {
        $validatedData = [];

        // Name (Optional for update, but if provided, validate it)
        if (array_key_exists('name', $this->dataToValidate)) {
            $name = $this->getInputValue('name');
            if (empty($name)) { // Cannot be updated to empty
                $this->addError('name', 'Brand name cannot be empty if provided for update.');
            } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
                $this->addError('name', 'Brand name must be between 3 and 255 characters.');
            }
            $validatedData['name'] = $name;
        }

        // Brand Image (Optional for update)
        $validatedData['image_file'] = null; // Info about a new uploaded file
        $validatedData['remove_brand_image'] = false; // Flag to indicate image removal

        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            // A new image is being uploaded
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            $this->validateImageFile($this->imageFileToValidate, 'brand_image', $allowedTypes, $maxSize);
            if ($this->imageFileToValidate['error'] === UPLOAD_ERR_OK && !isset($this->errors['brand_image'])) {
                $validatedData['image_file'] = $this->imageFileToValidate;
            }
        } elseif (filter_var($this->getInputValue('remove_brand_image'), FILTER_VALIDATE_BOOLEAN)) {
            // Client explicitly wants to remove the image
            $validatedData['remove_brand_image'] = true;
        }


        if (array_key_exists('brand_cloudinary_public_id', $this->dataToValidate) && $this->dataToValidate['brand_cloudinary_public_id'] === null) {
            $validatedData['brand_cloudinary_public_id'] = null;
        }


        $this->throwValidationExceptionIfNeeded();

        // Return only fields that were meant to be updated
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
