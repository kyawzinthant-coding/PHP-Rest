<?php

namespace App\Controller\Product;

use App\Repository\Product\ProductRepository;
use App\Service\CloudinaryImageUploader;
use App\Validate\ProductValidate; // Import the validation class
use App\Exception\ValidationException; // Import for type hinting if needed, or if you catch it specifically
use Exception;
use RuntimeException;
use App\Repository\DuplicateEntryException;
use App\Utils\ImageUrlHelper;

class ProductController
{
    private ProductRepository $productRepository;
    private CloudinaryImageUploader $imageUploader;

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
        $this->imageUploader = new CloudinaryImageUploader();
    }

    private function cleanupOrphanedImage(?string $publicId, string $context): void
    {
        if ($publicId) {
            try {
                $this->imageUploader->deleteImage($publicId);
                error_log("Cleaned up orphaned Cloudinary image: {$publicId} {$context}.");
            } catch (Exception $cleanupE) {
                error_log("Failed to clean up Cloudinary image {$publicId} {$context}: " . $cleanupE->getMessage());
            }
        }
    }

    public function index(): void
    {
        try {
            $products = $this->productRepository->GetALlProduct();

            if (empty($products)) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No products found.'
                ]);
                return;
            }
            $transformedProducts = ImageUrlHelper::transformItemsWithImageUrls($products, 'cloudinary_public_id', 'image_url');

            echo json_encode([
                'status' => 'success',
                'message' => 'Product list retrieved successfully',
                'data' => $transformedProducts
            ]);
            http_response_code(200);
        } catch (Exception $e) {
            throw $e; // Let index.php handle
        }
    }

    public function store(): void
    {
        $cloudinaryPublicId = null;
        $displayedImageUrl = null;

        try {
            $validator = new ProductValidate();
            // The validate method will throw ValidationException if validation fails
            // which will be caught by index.php
            $validatedData = $validator->validate(); // isUpdate = false by default

            $imageFile = $validatedData['image_file']; // Get validated image file info

            if ($imageFile) { // image_file will be null if no valid image was uploaded
                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'products/');
                    $displayedImageUrl = $uploadResult['secure_url'];
                    $cloudinaryPublicId = $uploadResult['public_id'];
                } catch (Exception $e) {
                    // No need to cleanup orphaned image here, as it wasn't associated with a DB record yet
                    throw new RuntimeException('Image upload failed: ' . $e->getMessage(), 500, $e);
                }
            }

            $productDataForRepo = [
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'price' => $validatedData['price'],
                'cloudinary_public_id' => $cloudinaryPublicId
            ];

            $newProductId = $this->productRepository->create($productDataForRepo);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'id' => $newProductId,
                'data' => array_merge($productDataForRepo, ['id' => $newProductId, 'image_url' => $displayedImageUrl])
            ]);
            http_response_code(201);
        } catch (DuplicateEntryException $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw $e;
        } catch (RuntimeException $e) { // Catches image upload failure too
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after runtime error during store");
            throw $e;
        } catch (ValidationException $e) { // Though index.php catches it, good to be explicit if any specific logic was needed
            // No specific cleanup needed here as DB operation hasn't happened.
            throw $e; // Re-throw for index.php to handle standard response
        } catch (Exception $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after unexpected error during store");
            throw $e;
        }
    }

    public function GetProductById(string $id): void
    {
        try {
            $product = $this->productRepository->findById($id);

            if (!$product) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found.'
                ]);
                return;
            }

            $product['image_url'] = ImageUrlHelper::generateUrl($product['cloudinary_public_id']);

            echo json_encode([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
            http_response_code(200);
        } catch (Exception $e) {
            throw $e; // Let index.php handle
        }
    }

    public function update(string $id): void
    {
        $newCloudinaryPublicId = null;
        $oldCloudinaryPublicId = null;

        try {
            $existingProduct = $this->productRepository->findById($id);
            if (!$existingProduct) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Product not found for update.']);
                return;
            }
            $oldCloudinaryPublicId = $existingProduct['cloudinary_public_id'];

            // Use ProductValidate for update
            $validator = new ProductValidate();
            // The validate method will throw ValidationException if validation fails
            $validatedData = $validator->validate(true); // Pass true for isUpdate



            $imageFileToUpload = $validatedData['image_file']; // Get validated image file info

            // Prepare data for repository, starting with existing product data
            // and overwriting with validated fields if they were provided
            $productDataForRepo = [
                'name' => $validatedData['name'] ?? $existingProduct['name'],
                'description' => $validatedData['description'] ?? $existingProduct['description'],

                'price' => isset($validatedData['price']) ? $validatedData['price'] : $existingProduct['price'],
                'cloudinary_public_id' => $oldCloudinaryPublicId // Start with the old public ID
            ];

            // Image handling
            if ($imageFileToUpload) { // A new valid image is uploaded
                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFileToUpload['tmp_name'], 'products/');
                    $newCloudinaryPublicId = $uploadResult['public_id'];
                    $productDataForRepo['cloudinary_public_id'] = $newCloudinaryPublicId;

                    // If new image uploaded successfully and there was an old one (and it's different), delete the old one
                    if ($oldCloudinaryPublicId && $oldCloudinaryPublicId !== $newCloudinaryPublicId) {
                        try {
                            $this->imageUploader->deleteImage($oldCloudinaryPublicId);
                        } catch (Exception $e) {
                            error_log("Failed to delete old Cloudinary image {$oldCloudinaryPublicId} during update: " . $e->getMessage());
                        }
                    }
                } catch (Exception $e) {
                    // If new image upload fails, don't proceed with DB update with potentially new ID
                    throw new RuntimeException('Image update failed: ' . $e->getMessage(), 500, $e);
                }
            }
            // If no new image and no explicit removal, cloudinary_public_id remains $oldCloudinaryPublicId

            $updated = $this->productRepository->update($id, $productDataForRepo);

            // Even if $updated is false (0 rows affected), it might not be an error
            // if the submitted data was identical to existing data.
            $finalProduct = $this->productRepository->findById($id);
            $finalProduct['image_url'] = ImageUrlHelper::generateUrl($finalProduct['cloudinary_public_id']);
            echo json_encode([
                'status' => 'success',
                'message' => $updated ? 'Product updated successfully' : 'Product update processed; no data changes or data reflects current state.',
                'data' => $finalProduct
            ]);
            http_response_code(200);
        } catch (DuplicateEntryException $e) {
            $this->cleanupOrphanedImage($newCloudinaryPublicId, "after duplicate update entry");
            throw $e;
        } catch (ValidationException $e) {
            // A new image might have been uploaded before validation of other fields failed
            $this->cleanupOrphanedImage($newCloudinaryPublicId, "after validation error during update");
            throw $e;
        } catch (RuntimeException $e) { // Catches image update/delete failures too
            // If $newCloudinaryPublicId is set, an image was uploaded before this runtime error
            $this->cleanupOrphanedImage($newCloudinaryPublicId, "after runtime error during update");
            throw $e;
        } catch (Exception $e) {
            $this->cleanupOrphanedImage($newCloudinaryPublicId, "after unexpected error during update");
            throw $e;
        }
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Delete a product by ID.
     * @param string $id The product ID from the URL (now a UUID string).
     * @throws RuntimeException If the product deletion failed in the database after existence check.
     */
    public function destroy(string $id): void
    {
        try {
            $existingProduct = $this->productRepository->findById($id);

            if (!$existingProduct) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found for deletion.'
                ]);
                return;
            }

            if ($existingProduct['cloudinary_public_id']) {
                try {
                    $this->imageUploader->deleteImage($existingProduct['cloudinary_public_id']);
                } catch (Exception $e) {
                    error_log("Failed to delete Cloudinary image {$existingProduct['cloudinary_public_id']} during product deletion: " . $e->getMessage());
                }
            }

            $deleted = $this->productRepository->delete($id);

            if ($deleted) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Product deleted successfully'
                ]);
                http_response_code(200);
            } else {
                throw new RuntimeException("Product deletion failed in the database after existence check.");
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
