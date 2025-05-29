<?php

namespace App\Validate;

use App\Exception\ValidationException;

class BrandValidation extends BaseRequest
{
    public function __construct()
    {
        parent::__construct();
        // Specific file input name for brand images
        $this->imageFile = $_FILES['brand_image'] ?? null;
    }

    public function validate(bool $isUpdate = false): array
    {
        $name = trim($this->input('name'));
        $brand_cloudinary_public_id = $this->input('brand_cloudinary_public_id');

        // Basic validation rules
        if (empty($name)) {
            $this->addError('name', 'Brand name is required.');
        } elseif (strlen($name) < 3 || strlen($name) > 255) {
            $this->addError('name', 'Brand name must be between 3 and 255 characters.');
        }

        if (empty($this->imageFile)) {
            $this->addError('brand_image', 'Brand image is required.');
        } elseif (!isset($this->imageFile['tmp_name']) || !is_uploaded_file($this->imageFile['tmp_name'])) {
            $this->addError('brand_image', 'Invalid brand image file.');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5 MB
        $this->validateImageFile($this->imageFile, 'brand_image', $allowedTypes, $maxSize);

        $this->throwValidationException();

        return [
            'name' => $name,
            'brand_cloudinary_public_id' => $brand_cloudinary_public_id,
            'image_file' => $this->imageFile,
        ];
    }
}
