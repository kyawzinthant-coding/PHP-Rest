<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Exception;
use Cloudinary\Api\Exception\ApiError;

class CloudinaryImageUploader
{
    private Cloudinary $cloudinary;

    public function __construct()
    {

        $config = Configuration::instance([
            'cloud' => [
                'cloud_name' => CLOUDINARY_CLOUD_NAME,
                'api_key' => CLOUDINARY_API_KEY,
                'api_secret' => CLOUDINARY_API_SECRET,
            ],
            'url' => [
                'secure' => true,
            ]
        ]);

        $this->cloudinary = new Cloudinary($config);
    }

    /**
     * Uploads an image file to Cloudinary.
     * @param string $filePath The temporary path to the uploaded file on the server.
     * @param string $publicIdPrefix A prefix for the public ID (e.g., 'products/').
     * @return string The URL of the uploaded image.
     * @throws Exception If the upload fails.
     */
    public function uploadImage(string $filePath, string $publicIdPrefix = ''): array
    {
        // This method handles sending an image from your server's temporary location to Cloudinary.

        try {


            $uploadResult = $this->cloudinary->uploadApi()->upload($filePath, [
                'folder' => $publicIdPrefix . 'ecommerce_products', // Organize images in a specific folder
                'use_filename' => true, // Use original filename as part of public ID
                'unique_filename' => true, // Ensure unique public ID even if filename is same
                'overwrite' => false, // Do not overwrite existing images with same public ID if unique_filename is false
            ]);

            // Check if upload was successful and return the URL
            if (isset($uploadResult['secure_url'])) {
                return [
                    'secure_url' => $uploadResult['secure_url'], // Full URL for display
                    'public_id' => $uploadResult['public_id'] // The ID needed for deletion
                ];
            } else {
                throw new Exception("Cloudinary upload failed: No secure_url returned.");
            }
        } catch (ApiError $e) {
            error_log("Cloudinary API Error: " . $e->getMessage());
            // Re-throw a new, more descriptive Exception. The controller will catch this.
            throw new Exception("Image upload to Cloudinary failed due to API error: " . $e->getMessage());
        } catch (Exception $e) {
            // This `catch` block catches any other general PHP exceptions that might occur.
            error_log("General Image Upload Error: " . $e->getMessage());
            throw new Exception("Image upload to Cloudinary failed: " . $e->getMessage());
        }
    }

    /**
     * Deletes an image from Cloudinary.
     * @param string $imageUrl The full URL of the image to delete.
     * @return bool True if deletion was successful.
     * @throws Exception If deletion fails.
     */
    public function deleteImage(string $publicId): bool // <--- NOW ACCEPTS PUBLIC_ID DIRECTLY
    {
        try {
            // No need for complex URL parsing here, as we directly receive the public_id
            $deleteResult = $this->cloudinary->uploadApi()->destroy($publicId, [ // <--- USE $publicId DIRECTLY
                'resource_type' => 'image',
            ]);

            if (isset($deleteResult['result']) && $deleteResult['result'] === 'ok') {
                return true;
            } else {
                throw new Exception("Cloudinary image deletion failed: " . ($deleteResult['error']['message'] ?? 'Unknown error'));
            }
        } catch (ApiError $e) {
            error_log("Cloudinary API Error (delete): " . $e->getMessage());
            throw new Exception("Image deletion from Cloudinary failed due to API error: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("General Image Deletion Error: " . $e->getMessage());
            throw new Exception("Image deletion from Cloudinary failed: " . $e->getMessage());
        }
    }
}
