<?php

namespace App\Validate;

// Removed `use App\Exception\ValidationException;` as it's handled by BaseRequest

class ProductValidate extends BaseRequest // Extend BaseRequest
{
    public function __construct()
    {
        parent::__construct(); // Call the parent constructor to parse input
        // Specific file input name for product images
        $this->imageFile = $_FILES['product_image'] ?? null;
    }

    /**
     * Validates and returns the product data.
     * @param bool $isUpdate True if validating for an update operation.
     * @return array Validated product data including image info.
     * @throws \App\Exception\ValidationException If validation fails.
     */
    public function validate(bool $isUpdate = false): array
    {
        $name = trim($this->input('name'));
        $description = trim($this->input('description'));
        $price = $this->input('price');
        $brand_id = $this->input('brand_id');
        $category_id = $this->input('category_id');
        $size_ml = $this->input('size_ml');
        $stock_quantity = $this->input('stock_quantity', 0); // Default to 0 if not provided
        $top_notes = $this->input('top_notes', '');
        $middle_notes = $this->input('middle_notes', '');
        $base_notes = $this->input('base_notes', '');
        $gender_affinity = $this->input('gender_affinity', 'Unisex');

        // Basic validation rules
        if (!$isUpdate || !empty($name)) {
            if (empty($name)) {
                $this->addError('name', 'Product name is required.');
            } elseif (strlen($name) < 3 || strlen($name) > 255) {
                $this->addError('name', 'Product name must be between 3 and 255 characters.');
            }
        }
        if (!$isUpdate || !empty($description)) {
            if (empty($description)) {
                $this->addError('description', 'Product description is required.');
            } elseif (strlen($description) < 10 || strlen($description) > 1000) {
                $this->addError('description', 'Product description must be between 10 and 1000 characters.');
            }
        }
        if (!$isUpdate || !empty($brand_id)) {
            if (empty($brand_id)) {
                $this->addError('brand_id', 'Brand ID is required.');
            } elseif (!is_numeric($brand_id) || (int)$brand_id <= 0) {
                $this->addError('brand_id', 'Brand ID must be a positive integer.');
            }
        }
        if (!$isUpdate || !empty($category_id)) {
            if (empty($category_id)) {
                $this->addError('category_id', 'Category ID is required.');
            } elseif (!is_numeric($category_id) || (int)$category_id <= 0) {
                $this->addError('category_id', 'Category ID must be a positive integer.');
            }
        }
        if (!$isUpdate || !empty($size_ml)) {
            if (empty($size_ml)) {
                $this->addError('size_ml', 'Size in ml is required.');
            } elseif (!is_numeric($size_ml) || (int)$size_ml <= 0) {
                $this->addError('size_ml', 'Size in ml must be a positive integer.');
            }
        }
        if (!$isUpdate || !empty($stock_quantity)) {
            if (!is_numeric($stock_quantity) || (int)$stock_quantity < 0) {
                $this->addError('stock_quantity', 'Stock quantity must be a non-negative integer.');
            }
            $stock_quantity = (int)$stock_quantity; // Cast to int
        }
        if (!$isUpdate || !empty($top_notes)) {
            if (strlen($top_notes) > 500) {
                $this->addError('top_notes', 'Top notes must not exceed 500 characters.');
            }
        }
        if (!$isUpdate || !empty($middle_notes)) {
            if (strlen($middle_notes) > 500) {
                $this->addError('middle_notes', 'Middle notes must not exceed 500 characters.');
            }
        }
        if (!$isUpdate || !empty($base_notes)) {
            if (strlen($base_notes) > 500) {
                $this->addError('base_notes', 'Base notes must not exceed 500 characters.');
            }
        }


        // Price validation: required for create, optional for update (can be 0)
        // Check if value is set OR if it's an update and no price was provided (meaning we don't validate it)
        if (!$isUpdate || (isset($this->data['price']) && $this->data['price'] !== '')) {
            if (!is_numeric($price) || (float)$price < 0) {
                $this->addError('price', 'Product price must be a non-negative number.');
            }
            $price = (float)$price; // Cast to float
        }


        // Image file validation using the base method
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5 MB
        $this->validateImageFile($this->imageFile, 'product_image', $allowedTypes, $maxSize);

        $this->throwValidationException(); // Throws exception if errors exist

        return [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'image_file' => $this->imageFile && $this->imageFile['error'] === UPLOAD_ERR_OK ? $this->imageFile : null,
        ];
    }
}
