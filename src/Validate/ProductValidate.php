<?php

namespace App\Validate;

use App\Core\Request;
use App\Exception\ValidationException;

class ProductValidate extends BaseRequest // Ensure BaseRequest provides error handling and validateImageFile
{
    protected Request $request;
    protected array $dataToValidate;
    protected ?array $imageFileToValidate;

    public function validate(bool $isUpdate = false): array
    {
        if ($isUpdate) {
            return $this->validateUpdate();
        } else {
            return $this->validateCreate();
        }
    }

    public function __construct(Request $request)
    {
        parent::__construct(); // Initializes $this->errors
        $this->request = $request;
        $this->dataToValidate = $this->request->post; // Assumes body data
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

    private function isValidUuid(?string $uuid): bool
    {
        if ($uuid === null) return false;
        return preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', (string)$uuid) === 1;
    }

    public function validateCreate(): array
    {
        $validatedData = [];

        // Name
        $name = $this->getInputValue('name');
        if (empty($name)) {
            $this->addError('name', 'Product name is required.');
        } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            $this->addError('name', 'Product name must be between 3 and 255 characters.');
        }
        $validatedData['name'] = $name;

        // Description
        $description = $this->getInputValue('description');
        if (empty($description)) {
            $this->addError('description', 'Description is required.');
        } elseif (mb_strlen($description) < 10) {
            $this->addError('description', 'Description must be at least 10 characters.');
        }
        $validatedData['description'] = $description;

        // Brand ID
        $brand_id = $this->getInputValue('brand_id');
        if (empty($brand_id)) {
            $this->addError('brand_id', 'Brand ID is required.');
        } elseif (!$this->isValidUuid($brand_id)) {
            $this->addError('brand_id', 'Brand ID must be a valid UUID.');
        }
        $validatedData['brand_id'] = $brand_id;

        // Category ID
        $category_id = $this->getInputValue('category_id');
        if (empty($category_id)) {
            $this->addError('category_id', 'Category ID is required.');
        } elseif (!$this->isValidUuid($category_id)) {
            $this->addError('category_id', 'Category ID must be a valid UUID.');
        }
        $validatedData['category_id'] = $category_id;

        // Size ML
        $size_ml = $this->getInputValue('size_ml');
        if ($size_ml === null || $size_ml === '') {
            $this->addError('size_ml', 'Size (ML) is required.');
        } elseif (!is_numeric($size_ml) || (int)$size_ml < 0 || filter_var($size_ml, FILTER_VALIDATE_INT) === false) {
            $this->addError('size_ml', 'Size (ML) must be a non-negative integer.');
        }
        $validatedData['size_ml'] = isset($this->errors['size_ml']) ? $size_ml : (int)$size_ml;

        // Price
        $price = $this->getInputValue('price');
        if ($price === null || $price === '') {
            $this->addError('price', 'Price is required.');
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $this->addError('price', 'Price must be a non-negative number.');
        }
        $validatedData['price'] = isset($this->errors['price']) ? $price : (float)$price;

        // Stock Quantity
        $stock_quantity = $this->getInputValue('stock_quantity', 0);
        if ($stock_quantity !== null && (!is_numeric($stock_quantity) || (int)$stock_quantity < 0 || filter_var($stock_quantity, FILTER_VALIDATE_INT) === false)) {
            $this->addError('stock_quantity', 'Stock quantity must be a non-negative integer.');
        }
        $validatedData['stock_quantity'] = isset($this->errors['stock_quantity']) ? $stock_quantity : (int)$stock_quantity;

        // Optional Text Fields
        $validatedData['top_notes'] = $this->getInputValue('top_notes', null);
        $validatedData['middle_notes'] = $this->getInputValue('middle_notes', null);
        $validatedData['base_notes'] = $this->getInputValue('base_notes', null);

        // Gender Affinity
        $gender_affinity = $this->getInputValue('gender_affinity', 'Unisex'); // Default if not provided
        if (mb_strlen((string)$gender_affinity) > 50) {
            $this->addError('gender_affinity', 'Gender affinity must not exceed 50 characters.');
        }
        $validatedData['gender_affinity'] = $gender_affinity;

        // Is Active
        $is_active_input = $this->getInputValue('is_active');
        if ($is_active_input === null || $is_active_input === '') { // If not sent, default to true for create
            $validatedData['is_active'] = true;
        } else {
            $bool_val = filter_var($is_active_input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool_val === null) {
                $this->addError('is_active', 'Is Active must be a valid boolean value (true, false, 1, 0).');
                $validatedData['is_active'] = true; // Default on parse error
            } else {
                $validatedData['is_active'] = $bool_val;
            }
        }

        // Product Image (following BrandValidation example structure)
        // The key 'product_image' will be used in validatedData for the file array.
        if (empty($this->imageFileToValidate) || $this->imageFileToValidate['error'] === UPLOAD_ERR_NO_FILE) {
            // Making product image optional for creation, remove error if not desired
            // $this->addError('product_image', 'Product image is required for creation.');
            $validatedData['product_image'] = null;
        } elseif (!isset($this->imageFileToValidate['tmp_name']) || !is_uploaded_file($this->imageFileToValidate['tmp_name'])) {
            // This case often indicates a problem with the upload process itself or form configuration
            $this->addError('product_image', 'Invalid product image file upload.');
            $validatedData['product_image'] = null;
        } else {
            // Call the validation method from BaseRequest (which adds errors to $this->errors)
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 10 * 1024 * 1024; // 5 MB
            $this->validateImageFile($this->imageFileToValidate, 'product_image', $allowedTypes, $maxSize);

            // If validateImageFile added errors, they will be caught by throwValidationExceptionIfNeeded()
            // Only pass the file if it was OK at PHP level and our validation (if any) passed
            if ($this->imageFileToValidate['error'] === UPLOAD_ERR_OK && (!isset($this->errors['product_image']))) {
                $validatedData['product_image'] = $this->imageFileToValidate;
            } else {
                $validatedData['product_image'] = null; // Don't pass if there was an error
            }
        }

        $this->throwValidationExceptionIfNeeded();
        return $validatedData;
    }

    public function validateUpdate(): array
    {
        $validatedData = [];

        // Iterate over potential fields and validate if present in the input
        $possibleFields = [
            'name',
            'description',
            'brand_id',
            'category_id',
            'size_ml',
            'price',
            'stock_quantity',
            'top_notes',
            'middle_notes',
            'base_notes',
            'gender_affinity',
            'is_active',
            'cloudinary_public_id' // Allow explicit null for cloudinary_public_id
        ];

        foreach ($possibleFields as $field) {
            if (array_key_exists($field, $this->dataToValidate)) {
                $value = $this->getInputValue($field);
                switch ($field) {
                    case 'name':
                        if (empty($value)) $this->addError('name', 'Product name cannot be empty if provided.');
                        elseif (mb_strlen($value) < 3 || mb_strlen($value) > 255) $this->addError('name', 'Name must be 3-255 chars.');
                        $validatedData['name'] = $value;
                        break;
                    case 'description':
                        if (empty($value)) $this->addError('description', 'Description cannot be empty if provided.');
                        elseif (mb_strlen($value) < 10) $this->addError('description', 'Description must be at least 10 chars if provided.');
                        $validatedData['description'] = $value;
                        break;
                    case 'brand_id':
                        if (empty($value)) $this->addError('brand_id', 'Brand ID cannot be empty if provided.');
                        elseif (!$this->isValidUuid($value)) $this->addError('brand_id', 'Brand ID must be a valid UUID if provided.');
                        $validatedData['brand_id'] = $value;
                        break;
                    case 'category_id':
                        if (empty($value)) $this->addError('category_id', 'Category ID cannot be empty if provided.');
                        elseif (!$this->isValidUuid($value)) $this->addError('category_id', 'Category ID must be a valid UUID if provided.');
                        $validatedData['category_id'] = $value;
                        break;
                    case 'size_ml':
                        if ($value === null || $value === '') $this->addError('size_ml', 'Size (ML) cannot be empty if provided.');
                        elseif (!is_numeric($value) || (int)$value < 0 || filter_var($value, FILTER_VALIDATE_INT) === false) $this->addError('size_ml', 'Size (ML) must be a non-negative integer if provided.');
                        $validatedData['size_ml'] = isset($this->errors['size_ml']) ? $value : (int)$value;
                        break;
                    case 'price':
                        if ($value === null || $value === '') $this->addError('price', 'Price cannot be empty if provided.');
                        elseif (!is_numeric($value) || (float)$value < 0) $this->addError('price', 'Price must be a non-negative number if provided.');
                        $validatedData['price'] = isset($this->errors['price']) ? $value : (float)$value;
                        break;
                    case 'stock_quantity':
                        if ($value !== null && $value !== '' && (!is_numeric($value) || (int)$value < 0 || filter_var($value, FILTER_VALIDATE_INT) === false)) $this->addError('stock_quantity', 'Stock must be a non-negative integer if provided.');
                        $validatedData['stock_quantity'] = ($value === null || $value === '') ? null : (isset($this->errors['stock_quantity']) ? $value : (int)$value);
                        break;
                    case 'is_active':
                        if ($value === null || $value === '') {
                            $this->addError('is_active', 'Is Active cannot be empty if provided.');
                        } else {
                            $bool_val = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                            if ($bool_val === null) {
                                $this->addError('is_active', 'Is Active must be a valid boolean if provided.');
                            } else {
                                $validatedData['is_active'] = $bool_val;
                            }
                        }
                        break;
                    case 'cloudinary_public_id': // Allow explicitly setting to null
                        $validatedData['cloudinary_public_id'] = ($value === null) ? null : (string)$value;
                        break;
                    default: // For text fields like notes, gender_affinity
                        $validatedData[$field] = $value;
                        if ($field === 'gender_affinity' && mb_strlen((string)$value) > 50) {
                            $this->addError('gender_affinity', 'Gender affinity max 50 chars.');
                        }
                        break;
                }
            }
        }

        // Image handling for update (new image or remove flag)
        $validatedData['product_image'] = null; // Key for the uploaded file itself
        if ($this->imageFileToValidate && $this->imageFileToValidate['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024;
            $this->validateImageFile($this->imageFileToValidate, 'product_image', $allowedTypes, $maxSize);
            if ($this->imageFileToValidate['error'] === UPLOAD_ERR_OK && !isset($this->errors['product_image'])) {
                $validatedData['product_image'] = $this->imageFileToValidate;
            }
        } elseif (filter_var($this->getInputValue('remove_product_image'), FILTER_VALIDATE_BOOLEAN)) {
            $validatedData['remove_product_image'] = true; // This is a flag for the controller
            // If removing image, controller should set cloudinary_public_id to null
            // We can also signal this here for consistency IF cloudinary_public_id wasn't also sent as null
            if (!array_key_exists('cloudinary_public_id', $validatedData)) {
                $validatedData['cloudinary_public_id'] = null;
            }
        }

        $this->throwValidationExceptionIfNeeded();
        return $validatedData; // Returns only fields that were present in input and validated
    }

    protected function throwValidationExceptionIfNeeded(): void
    {
        if ($this->hasErrors()) {
            throw new ValidationException('Validation failed.', 400, $this->getErrors());
        }
    }
}
