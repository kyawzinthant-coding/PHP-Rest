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

        // dd(CLOUDINARY_URL);
        // Initialize Cloudinary configuration
        // Configuration::instance([
        //     'cloud' => [
        //         'cloud_name' => CLOUDINARY_CLOUD_NAME,
        //         'api_key' => CLOUDINARY_API_KEY,
        //         'api_secret' => CLOUDINARY_API_SECRET,
        //     ],
        //     'url' => [
        //         'secure' => true, // Use HTTPS for URLs
        //     ],
        // ]);

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
    public function uploadImage(string $filePath, string $publicIdPrefix = ''): string
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
                return $uploadResult['secure_url']; // Return the URL if successful.
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
    public function deleteImage(string $imageUrl): bool
    {
        // This method handles deleting an image from your Cloudinary account using its URL.

        try {
            // Extract public ID from the image URL
            // Example URL: https://res.cloudinary.com/cloud_name/image/upload/v123456789/folder/public_id.jpg
            // parse_url($imageUrl, PHP_URL_PATH): Extracts the path part of the URL (e.g., /cloud_name/image/upload/v123456789/folder/public_id.jpg).
            $pathParts = explode('/', parse_url($imageUrl, PHP_URL_PATH)); // Splits the path into an array of segments.
            // end($pathParts): Gets the last segment (e.g., public_id.jpg).
            $publicIdWithExtension = end($pathParts);
            // pathinfo(..., PATHINFO_FILENAME): Extracts just the filename without the extension (e.g., public_id).
            $publicId = pathinfo($publicIdWithExtension, PATHINFO_FILENAME);

            $folderAndPublicId = implode('/', array_slice($pathParts, array_search('upload', $pathParts) + 2));
            // pathinfo(..., PATHINFO_FILENAME): Again extracts just the filename (which is the public ID here, possibly with a folder prefix).
            $publicIdToDelete = pathinfo($folderAndPublicId, PATHINFO_FILENAME);

            // $this->cloudinary->uploadApi()->destroy(...): Calls the Cloudinary SDK method to delete an asset.
            // 'resource_type' => 'image': Specifies that we are deleting an image.
            $deleteResult = $this->cloudinary->uploadApi()->destroy($publicIdToDelete, [
                'resource_type' => 'image', // Specify resource type
            ]);

            // Check the result of the deletion operation. 'result' should be 'ok' for success.
            if (isset($deleteResult['result']) && $deleteResult['result'] === 'ok') {
                return true; // Return true on successful deletion.
            } else {
                // If deletion was not 'ok', throw an Exception with details.
                throw new Exception("Cloudinary image deletion failed: " . ($deleteResult['error']['message'] ?? 'Unknown error'));
            }
        } catch (ApiError $e) { // <--- FIX: Now correctly references the imported ApiError class
            // Catch Cloudinary-specific API errors during deletion.
            error_log("Cloudinary API Error (delete): " . $e->getMessage());
            throw new Exception("Image deletion from Cloudinary failed due to API error: " . $e->getMessage());
        } catch (Exception $e) {
            // Catch any other general exceptions during deletion.
            error_log("General Image Deletion Error: " . $e->getMessage());
            throw new Exception("Image deletion from Cloudinary failed: " . $e->getMessage());
        }
    }
}
