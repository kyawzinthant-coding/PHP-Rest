<?php

namespace App\Controller\Product;

use App\Repository\Product\ProductRepository;
use App\Service\CloudinaryImageUploader;
use Exception;
use RuntimeException;
use App\Repository\DuplicateEntryException;

class ProductController
{
    private ProductRepository $productRepository;
    private CloudinaryImageUploader $imageUploader;

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
        $this->imageUploader = new CloudinaryImageUploader();
    }

    public function index(): void
    {
        try {
            $products = $this->productRepository->GetALlProduct();

            $transformedProducts = array_map(function ($product) {
                if (!empty($product['cloudinary_public_id'])) {
                    $product['image_url'] = "https://res.cloudinary.com/" . CLOUDINARY_CLOUD_NAME . "/image/upload/" . $product['cloudinary_public_id'] . ".webp";
                } else {
                    $product['image_url'] = null;
                }
                return $product;
            }, $products);

            echo json_encode([
                'status' => 'success',
                'message' => 'Product list retrieved successfully',
                'data' => $transformedProducts
            ]);
            http_response_code(200);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to retrieve products: ' . $e->getMessage()
            ]);
        }
    }

    public function store(): void
    {
        $data = $_POST;
        $imageFile = $_FILES['product_image'] ?? null;

        if (empty($data['name']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid input data. Name and price are required.'
            ]);
            return;
        }

        $cloudinaryPublicId = null;
        $displayedImageUrl = null;

        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($imageFile['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid image file type. Only JPEG, PNG, GIF, and WEBP are allowed.'
                ]);
                return;
            }

            try {
                $uploadResult = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'products/');
                $displayedImageUrl = $uploadResult['secure_url'];
                $cloudinaryPublicId = $uploadResult['public_id'];
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Image upload failed: ' . $e->getMessage()
                ]);
                return;
            }
        } elseif ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Image upload error: ' . $imageFile['error'] . ' (Code: ' . $imageFile['error'] . '). Check PHP upload limits.'
            ]);
            return;
        }

        $productData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => (float)$data['price'],
            'cloudinary_public_id' => $cloudinaryPublicId
        ];

        try {
            $newProductId = $this->productRepository->create($productData);

            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'id' => $newProductId,
                'data' => array_merge($productData, ['id' => $newProductId, 'image_url' => $displayedImageUrl])
            ]);
            http_response_code(201);
        } catch (DuplicateEntryException $e) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            if ($cloudinaryPublicId) {
                try {
                    $this->imageUploader->deleteImage($cloudinaryPublicId);
                    error_log("Cleaned up orphaned Cloudinary image: {$cloudinaryPublicId} after duplicate entry.");
                } catch (Exception $cleanupE) {
                    error_log("Failed to clean up Cloudinary image after duplicate entry: " . $cleanupE->getMessage());
                }
            }
        } catch (RuntimeException $e) {
            if ($cloudinaryPublicId) {
                try {
                    $this->imageUploader->deleteImage($cloudinaryPublicId);
                    error_log("Cleaned up orphaned Cloudinary image: {$cloudinaryPublicId} after DB error.");
                } catch (Exception $cleanupE) {
                    error_log("Failed to clean up Cloudinary image: {$cloudinaryPublicId} after DB error: " . $cleanupE->getMessage());
                }
            }
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create product: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            if ($cloudinaryPublicId) {
                try {
                    $this->imageUploader->deleteImage($cloudinaryPublicId);
                    error_log("Cleaned up orphaned Cloudinary image: {$cloudinaryPublicId} after unexpected error.");
                } catch (Exception $cleanupE) {
                    error_log("Failed to clean up Cloudinary image: {$cloudinaryPublicId} after unexpected error: " . $cleanupE->getMessage());
                }
            }
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred during product creation: ' . $e->getMessage()
            ]);
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

            if (!empty($product['cloudinary_public_id'])) {
                $product['image_url'] = "https://res.cloudinary.com/" . CLOUDINARY_CLOUD_NAME . "/image/upload/" . $product['cloudinary_public_id'] . ".webp";
            } else {
                $product['image_url'] = null;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
            http_response_code(200);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to retrieve product: ' . $e->getMessage()
            ]);
        }
    }

    public function update(string $id): void
    {
        $data = $_POST;
        $imageFile = $_FILES['product_image'] ?? null;

        if (empty($data) && $imageFile === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
        }

        if (empty($data) && $imageFile === null) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No data or file provided for update.'
            ]);
            return;
        }

        try {
            $existingProduct = $this->productRepository->findById($id);
            if (!$existingProduct) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found for update.'
                ]);
                return;
            }

            $productData = [
                'name' => $data['name'] ?? $existingProduct['name'],
                'description' => $data['description'] ?? $existingProduct['description'],
                'price' => (float)($data['price'] ?? $existingProduct['price']),
                'cloudinary_public_id' => $existingProduct['cloudinary_public_id']
            ];

            $displayedImageUrl = null;
            $newCloudinaryPublicId = null;

            if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imageFile['type'], $allowedTypes)) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Invalid image file type for update. Only JPEG, PNG, GIF, and WEBP are allowed.'
                    ]);
                    return;
                }

                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'products/');
                    $displayedImageUrl = $uploadResult['secure_url'];
                    $newCloudinaryPublicId = $uploadResult['public_id'];
                    $productData['cloudinary_public_id'] = $newCloudinaryPublicId;

                    if ($existingProduct['cloudinary_public_id']) {
                        $this->imageUploader->deleteImage($existingProduct['cloudinary_public_id']);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Image update failed: ' . $e->getMessage()
                    ]);
                    return;
                }
            } elseif ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                // More specific error handling for PHP file upload errors
                $errorMessage = 'Unknown image upload error.';
                switch ($imageFile['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errorMessage = 'Uploaded file exceeds PHP configured size limits.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errorMessage = 'The uploaded file was only partially uploaded.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $errorMessage = 'Missing a temporary folder for uploads.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $errorMessage = 'Failed to write file to disk.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $errorMessage = 'A PHP extension stopped the file upload.';
                        break;
                    default:
                        $errorMessage = 'An unexpected upload error occurred.';
                        break;
                }
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Image upload error: ' . $errorMessage . ' (Code: ' . $imageFile['error'] . ').'
                ]);
                return;
            } elseif (array_key_exists('cloudinary_public_id', $data) && $data['cloudinary_public_id'] === null) {
                if ($existingProduct['cloudinary_public_id']) {
                    try {
                        $this->imageUploader->deleteImage($existingProduct['cloudinary_public_id']);
                        $productData['cloudinary_public_id'] = null;
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Failed to delete old image during update: ' . $e->getMessage()
                        ]);
                        return;
                    }
                }
            }

            $updated = $this->productRepository->update($id, $productData);

            if ($updated) {
                $updatedProduct = $this->productRepository->findById($id);
                if (!empty($updatedProduct['cloudinary_public_id'])) {
                    $updatedProduct['image_url'] = "https://res.cloudinary.com/" . CLOUDINARY_CLOUD_NAME . "/image/upload/" . $updatedProduct['cloudinary_public_id'] . ".webp";
                } else {
                    $updatedProduct['image_url'] = null;
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Product updated successfully',
                    'data' => $updatedProduct
                ]);
                http_response_code(200);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product update failed or no changes were made.'
                ]);
            }
        } catch (DuplicateEntryException $e) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            if ($newCloudinaryPublicId) {
                try {
                    $this->imageUploader->deleteImage($newCloudinaryPublicId);
                    error_log("Cleaned up orphaned Cloudinary image: {$newCloudinaryPublicId} after duplicate update entry.");
                } catch (Exception $cleanupE) {
                    error_log("Failed to clean up Cloudinary image after duplicate update entry: " . $cleanupE->getMessage());
                }
            }
        } catch (RuntimeException $e) {
            if ($newCloudinaryPublicId) {
                try {
                    $this->imageUploader->deleteImage($newCloudinaryPublicId);
                    error_log("Cleaned up orphaned Cloudinary image: {$newCloudinaryPublicId} after DB error during update.");
                } catch (Exception $cleanupE) {
                    error_log("Failed to clean up Cloudinary image: {$newCloudinaryPublicId} after DB error during update: " . $cleanupE->getMessage());
                }
            }
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update product in DB: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            if ($newCloudinaryPublicId) {
                try {
                    $this->imageUploader->deleteImage($newCloudinaryPublicId);
                    error_log("Cleaned up orphaned Cloudinary image: {$newCloudinaryPublicId} after unexpected error during update.");
                } catch (Exception $cleanupE) {
                    error_log("Failed to clean up Cloudinary image: {$newCloudinaryPublicId} after unexpected error during update: " . $cleanupE->getMessage());
                }
            }
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred during product update: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a product by ID.
     * @param int $id The product ID from the URL.
     */
    public function destroy(string $id): void
    {
        try {
            // Check if product exists before attempting to delete
            $existingProduct = $this->productRepository->findById($id);

            if ($existingProduct['cloudinary_public_id']) {
                $this->imageUploader->deleteImage($existingProduct['cloudinary_public_id']);
            }

            if (!$existingProduct) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found for deletion.'
                ]);
                return;
            }

            $deleted = $this->productRepository->delete($id);


            if ($deleted) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Product deleted successfully'
                ]);
                http_response_code(201); // No Content, successful deletion
                // No content to return for a 204 response
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product deletion failed.'
                ]);
            }
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete product: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    }
}
