<?php

namespace App\Service;

use App\Service\CloudinaryImageUploader;
use Exception;

class ProductImageService
{
    private CloudinaryImageUploader $uploader;

    public function __construct()
    {
        $this->uploader = new CloudinaryImageUploader();
    }
    /**
     * Uploads an image file to Cloudinary.
     *
     * @param array $imageFile The image file from the request.
     * @return string The public ID of the uploaded image.
     * @throws Exception If the file type is not allowed.
     */
    public function upload(array $imageFile): string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageFile['type'], $allowedTypes)) {
            throw new Exception('Invalid image file type. Only JPEG, PNG, GIF, and WEBP are allowed.');
        }
        $result = $this->uploader->uploadImage($imageFile['tmp_name'], 'products/');
        if (!isset($result['public_id'])) {
            throw new Exception('Failed to retrieve public ID from upload result.');
        }
        return $result['public_id'];
    }



    public function delete(?string $publicId): void
    {
        if ($publicId) {
            $this->uploader->deleteImage($publicId);
        }
    }
}
