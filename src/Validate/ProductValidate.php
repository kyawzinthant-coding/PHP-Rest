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
        $fields = [
            'name' => ['required' => true, 'min' => 3, 'max' => 255],
            'description' => ['required' => true, 'min' => 10, 'max' => 1000],
            'brand_id' => ['required' => true],
            'category_id' => ['required' => true],
            'size_ml' => ['required' => true, 'type' => 'int', 'min' => 1],
            'stock_quantity' => ['required' => false, 'type' => 'int', 'min' => 0, 'default' => 0],
            'top_notes' => ['max' => 500, 'default' => ''],
            'middle_notes' => ['max' => 500, 'default' => ''],
            'base_notes' => ['max' => 500, 'default' => ''],
            'gender_affinity' => ['min' => 3, 'max' => 100, 'default' => 'Unisex']
        ];

        $validated = [];

        foreach ($fields as $field => $rules) {
            $value = trim($this->input($field, $rules['default'] ?? ''));

            if ((!$isUpdate || $value !== '') && ($rules['required'] ?? false) && $value === '') {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required.');
                continue;
            }

            if (isset($rules['type']) && $value !== '') {
                if ($rules['type'] === 'int' && (!is_numeric($value) || (int)$value < ($rules['min'] ?? 0))) {
                    $this->addError($field, ucfirst($field) . ' must be a positive integer.');
                    continue;
                }
                $value = (int)$value;
            }

            if (isset($rules['min']) && strlen($value) < $rules['min']) {
                $this->addError($field, ucfirst($field) . " must be at least {$rules['min']} characters.");
            }

            if (isset($rules['max']) && strlen($value) > $rules['max']) {
                $this->addError($field, ucfirst($field) . " must not exceed {$rules['max']} characters.");
            }

            $validated[$field] = $value;
        }

        // Price validation
        $price = $this->input('price');
        if (!$isUpdate || (isset($this->data['price']) && $price !== '')) {
            if (!is_numeric($price) || (float)$price < 0) {
                $this->addError('price', 'Product price must be a non-negative number.');
            }
            $validated['price'] = (float)$price;
        }

        // Image validation
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $this->validateImageFile($this->imageFile, 'product_image', $allowedTypes, $maxSize);

        $this->throwValidationException();

        $validated['image_file'] = $this->imageFile && $this->imageFile['error'] === UPLOAD_ERR_OK ? $this->imageFile : null;

        return $validated;
    }
}
