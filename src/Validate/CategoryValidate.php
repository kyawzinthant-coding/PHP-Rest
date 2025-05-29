<?php

namespace App\Validate;

use App\Exception\ValidationException;

class CategoryValidate extends BaseRequest
{
    public function __construct()
    {
        parent::__construct();
        // Specific file input name for category images
        $this->imageFile = $_FILES['category_image'] ?? null;
    }
    public function validate(bool $isUpdate = false): array
    {
        $name = trim($this->input('name'));
        $category_cloudinary_public_id = $this->input('category_cloudinary_public_id');

        // Basic validation rules
        if (empty($name)) {
            $this->addError('name', 'Category name is required.');
        } elseif (strlen($name) < 3 || strlen($name) > 255) {
            $this->addError('name', 'Category name must be between 3 and 255 characters.');
        }


        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5 MB
        $this->validateImageFile($this->imageFile, 'category_image', $allowedTypes, $maxSize);

        $this->throwValidationException(); //


        return [
            'name' => $name,
            'category_cloudinary_public_id' => $category_cloudinary_public_id,
            'image_file' => $this->imageFile,
        ];
    }
}
