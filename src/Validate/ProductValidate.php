<?php

namespace App\Validate;

use App\Core\Request;
use App\Exception\ValidationException; // Ensure this exception class exists

class ProductValidate extends BaseRequest // Make sure BaseRequest provides $this->errors, addError(), hasErrors(), getErrors()
{
    protected Request $request;
    protected array $dataToValidate; // Data from request body (POST/JSON)
    protected ?array $imageFileToValidate; // File from request

    // Implements the required validate() method
    public function validate(bool $isUpdate = false): array
    {
        // By default, call validateCreate (or change to validateUpdate as needed)
        // You may want to determine which validation to run based on request method or context
        return $this->validateCreate();
    }

    public function __construct(Request $request)
    {
        parent::__construct(); // Initialize $this->errors = [] from BaseRequest
        $this->request = $request;
        $this->dataToValidate = $this->request->post; // Assumes POST/JSON body from Request object
        $this->imageFileToValidate = $this->request->files['product_image'] ?? null;
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

    public function validateCreate(): array
    {
        $validatedData = [];

        // Name (Required)
        $name = $this->getInputValue('name');
        if (empty($name)) {
            $this->addError('name', 'Product name is required.');
        } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->addError('name', 'Product name must be between 3 and 255 characters.');
        }
        $validatedData['name'] = $name;

        // Description (Required)
        $description = $this->getInputValue('description');
        if (empty($description)) {
            $this->addError('description', 'Description is required.');
        } elseif (mb_strlen($description) < 10) {
            $this->addError('description', 'Description must be at least 10 characters.');
        }
        $validatedData['description'] = $description;

        // Brand ID (Required, UUID format)
        $brand_id = $this->getInputValue('brand_id');
        if (empty($brand_id)) {
            $this->addError('brand_id', 'Brand ID is required.');
        } elseif (!$this->isValidUuid($brand_id)) {
            $this->addError('brand_id', 'Brand ID must be a valid UUID.');
        }
        $validatedData['brand_id'] = $brand_id;

        // Category ID (Required, UUID format)
        $category_id = $this->getInputValue('category_id');
        if (empty($category_id)) {
            $this->addError('category_id', 'Category ID is required.');
        } elseif (!$this->isValidUuid($category_id)) {
            $this->addError('category_id', 'Category ID must be a valid UUID.');
        }
        $validatedData['category_id'] = $category_id;

        // Size ML (Required, Integer, >= 0)
        $size_ml = $this->getInputValue('size_ml');
        if ($size_ml === null || $size_ml === '') { // Check for explicitly empty or null
            $this->addError('size_ml', 'Size (ML) is required.');
        } elseif (!is_numeric($size_ml) || (int)$size_ml < 0 || filter_var($size_ml, FILTER_VALIDATE_INT) === false) {
            $this->addError('size_ml', 'Size (ML) must be a non-negative integer.');
        }
        $validatedData['size_ml'] = isset($this->errors['size_ml']) ? $size_ml : (int)$size_ml;


        // Price (Required, Numeric, >= 0)
        $price = $this->getInputValue('price');
        if ($price === null || $price === '') {
            $this->addError('price', 'Price is required.');
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $this->addError('price', 'Price must be a non-negative number.');
        }
        $validatedData['price'] = isset($this->errors['price']) ? $price : (float)$price;

        // Stock Quantity (Optional, Integer, >= 0, defaults in DB or here)
        $stock_quantity = $this->getInputValue('stock_quantity', 0); // Default if not provided
        if ($stock_quantity !== null && (!is_numeric($stock_quantity) || (int)$stock_quantity < 0 || filter_var($stock_quantity, FILTER_VALIDATE_INT) === false)) {
            $this->addError('stock_quantity', 'Stock quantity must be a non-negative integer.');
        }
        $validatedData['stock_quantity'] = isset($this->errors['stock_quantity']) ? $stock_quantity : (int)$stock_quantity;

        // Notes (Optional, Text) - Max length can be handled by DB or a general rule if needed
        $validatedData['top_notes'] = $this->getInputValue('top_notes', null);
        $validatedData['middle_notes'] = $this->getInputValue('middle_notes', null);
        $validatedData['base_notes'] = $this->getInputValue('base_notes', null);

        // Gender Affinity (Optional, String, default 'Unisex' in DB)
        $gender_affinity = $this->getInputValue('gender_affinity', 'Unisex');
        if (mb_strlen($gender_affinity) > 50) {
            $this->addError('gender_affinity', 'Gender affinity must not exceed 50 characters.');
        }
        $validatedData['gender_affinity'] = $gender_affinity;

        // Is Active (Optional, Boolean, defaults to true in DB)
        $is_active_input = $this->getInputValue('is_active');
        if ($is_active_input !== null) {
            $validatedData['is_active'] = filter_var($is_active_input, FILTER_VALIDATE_BOOLEAN);
        } else {
            $validatedData['is_active'] = true; // Default for create if not sent
        }

        // Image validation (optional for create)
        $validatedData['image_file'] = null;
        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->validateProductImageFile();
            if (!$this->hasErrors() && $this->imageFileToValidate['error'] === UPLOAD_ERR_OK) {
                $validatedData['image_file'] = $this->imageFileToValidate;
            }
        }

        $this->throwValidationExceptionIfNeeded();
        return $validatedData;
    }

    public function validateUpdate(): array
    {
        $validatedData = []; // Only include fields that are present and validated

        // Name (Optional)
        if (array_key_exists('name', $this->dataToValidate)) {
            $name = $this->getInputValue('name');
            if (empty($name)) { // If sent as empty, it's an error for name
                $this->addError('name', 'Product name cannot be empty if provided for update.');
            } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
                $this->addError('name', 'Product name must be between 3 and 255 characters.');
            }
            $validatedData['name'] = $name;
        }

        // Description (Optional)
        if (array_key_exists('description', $this->dataToValidate)) {
            $description = $this->getInputValue('description');
            if (empty($description)) { // If sent as empty, it's an error for description
                $this->addError('description', 'Description cannot be empty if provided for update.');
            } elseif (mb_strlen($description) < 10) {
                $this->addError('description', 'Description must be at least 10 characters if provided.');
            }
            $validatedData['description'] = $description;
        }

        // Brand ID (Optional, UUID format)
        if (array_key_exists('brand_id', $this->dataToValidate)) {
            $brand_id = $this->getInputValue('brand_id');
            if (empty($brand_id)) {
                $this->addError('brand_id', 'Brand ID cannot be empty if provided.');
            } elseif (!$this->isValidUuid($brand_id)) {
                $this->addError('brand_id', 'Brand ID must be a valid UUID if provided.');
            }
            $validatedData['brand_id'] = $brand_id;
        }

        // Category ID (Optional, UUID format)
        if (array_key_exists('category_id', $this->dataToValidate)) {
            $category_id = $this->getInputValue('category_id');
            if (empty($category_id)) {
                $this->addError('category_id', 'Category ID cannot be empty if provided.');
            } elseif (!$this->isValidUuid($category_id)) {
                $this->addError('category_id', 'Category ID must be a valid UUID if provided.');
            }
            $validatedData['category_id'] = $category_id;
        }

        // Size ML (Optional, Integer, >= 0)
        if (array_key_exists('size_ml', $this->dataToValidate)) {
            $size_ml = $this->getInputValue('size_ml');
            if ($size_ml === null || $size_ml === '') {
                $this->addError('size_ml', 'Size (ML) cannot be empty if provided for update.');
            } elseif (!is_numeric($size_ml) || (int)$size_ml < 0 || filter_var($size_ml, FILTER_VALIDATE_INT) === false) {
                $this->addError('size_ml', 'Size (ML) must be a non-negative integer if provided.');
            }
            $validatedData['size_ml'] = isset($this->errors['size_ml']) ? $size_ml : (int)$size_ml;
        }

        // Price (Optional, Numeric, >= 0)
        if (array_key_exists('price', $this->dataToValidate)) {
            $price = $this->getInputValue('price');
            if ($price === null || $price === '') {
                $this->addError('price', 'Price cannot be empty if provided for update.');
            } elseif (!is_numeric($price) || (float)$price < 0) {
                $this->addError('price', 'Price must be a non-negative number if provided.');
            }
            $validatedData['price'] = isset($this->errors['price']) ? $price : (float)$price;
        }

        // Stock Quantity (Optional, Integer, >= 0)
        if (array_key_exists('stock_quantity', $this->dataToValidate)) {
            $stock_quantity = $this->getInputValue('stock_quantity');
            if ($stock_quantity !== null && $stock_quantity !== '' && (!is_numeric($stock_quantity) || (int)$stock_quantity < 0 || filter_var($stock_quantity, FILTER_VALIDATE_INT) === false)) {
                $this->addError('stock_quantity', 'Stock quantity must be a non-negative integer if provided.');
            }
            $validatedData['stock_quantity'] = isset($this->errors['stock_quantity']) ? $stock_quantity : ($stock_quantity === null || $stock_quantity === '' ? null : (int)$stock_quantity);
            if ($validatedData['stock_quantity'] === null && ($stock_quantity !== null && $stock_quantity !== '')) { /* error already added */
            } else if ($validatedData['stock_quantity'] === null && ($stock_quantity === null || $stock_quantity === '')) {
                // If client sends "stock_quantity": null or "stock_quantity": "", allow it to be set to null
                // if your DB/logic allows. Otherwise, this field might need a default if not provided.
                // For now, if explicitly sent as empty/null, it will be in validatedData as null (if no other error).
            }
        }

        // Notes (Optional, Text) - Allow empty string to clear, or null
        if (array_key_exists('top_notes', $this->dataToValidate)) $validatedData['top_notes'] = $this->getInputValue('top_notes');
        if (array_key_exists('middle_notes', $this->dataToValidate)) $validatedData['middle_notes'] = $this->getInputValue('middle_notes');
        if (array_key_exists('base_notes', $this->dataToValidate)) $validatedData['base_notes'] = $this->getInputValue('base_notes');

        // Gender Affinity (Optional, String)
        if (array_key_exists('gender_affinity', $this->dataToValidate)) {
            $gender_affinity = $this->getInputValue('gender_affinity');
            if (mb_strlen($gender_affinity) > 50) {
                $this->addError('gender_affinity', 'Gender affinity must not exceed 50 characters if provided.');
            }
            $validatedData['gender_affinity'] = $gender_affinity;
        }

        // Is Active (Optional, Boolean)
        if (array_key_exists('is_active', $this->dataToValidate)) {
            $is_active_input = $this->getInputValue('is_active');
            if ($is_active_input === null || $is_active_input === '') { // If sent empty
                $this->addError('is_active', 'Is Active cannot be empty if provided; send true or false.');
            } else {
                $validatedData['is_active'] = filter_var($is_active_input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($validatedData['is_active'] === null) {
                    $this->addError('is_active', 'Is Active must be a valid boolean (true, false, 1, 0) if provided.');
                }
            }
        }

        // Image handling for update
        $validatedData['image_file'] = null; // Default to no new image
        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->validateProductImageFile();
            if (!$this->hasErrors() && $this->imageFileToValidate['error'] === UPLOAD_ERR_OK) {
                $validatedData['image_file'] = $this->imageFileToValidate;
            }
        } elseif (filter_var($this->getInputValue('remove_product_image'), FILTER_VALIDATE_BOOLEAN)) {
            $validatedData['remove_product_image'] = true;
            $validatedData['cloudinary_public_id'] = null; // Signal to controller to set DB field to null
        }
        // If client wants to explicitly set cloudinary_public_id to null without remove_product_image flag
        // (e.g. if they manage public_ids externally and want to clear it)
        if (array_key_exists('cloudinary_public_id', $this->dataToValidate) && $this->dataToValidate['cloudinary_public_id'] === null) {
            $validatedData['cloudinary_public_id'] = null;
        }


        $this->throwValidationExceptionIfNeeded();
        return $validatedData;
    }

    private function validateProductImageFile(): void
    {
        // This method uses $this->imageFileToValidate which is set in constructor
        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            // This method should be in BaseRequest or here. It adds errors to $this->errors.
            $this->validateImageFile($this->imageFileToValidate, 'product_image', $allowedTypes, $maxSize);
        }
    }

    private function isValidUuid(?string $uuid): bool
    {
        if ($uuid === null) return false; // Or true if nullable UUID is allowed for a field
        return preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $uuid) === 1;
    }

    // Ensure these methods are available from BaseRequest or implement them here
    // protected $errors = [];
    // protected function addError(string $field, string $message): void { $this->errors[$field][] = $message; }
    // public function hasErrors(): bool { return !empty($this->errors); }
    // public function getErrors(): array { return $this->errors; }
    protected function throwValidationExceptionIfNeeded(): void
    {
        if ($this->hasErrors()) {
            throw new ValidationException('Validation failed.', 400, $this->getErrors());
        }
    }
}
