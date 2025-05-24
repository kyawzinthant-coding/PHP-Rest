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
        $cloudinaryPublicId = $this->input('cloudinary_public_id'); // For explicit removal of image

        // Basic validation rules
        if (!$isUpdate || !empty($name)) {
            if (empty($name)) {
                $this->addError('name', 'Product name is required.');
            } elseif (strlen($name) < 3 || strlen($name) > 255) {
                $this->addError('name', 'Product name must be between 3 and 255 characters.');
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
