<?php

namespace App\Controller\Product;

use App\Repository\Product\ProductRepository;
use Exception; // Import the generic Exception class for general error handling
use App\Service\CloudinaryImageUploader; // Import the CloudinaryImageUploader class

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

            echo json_encode([
                'status' => 'success',
                'message' => 'Product list retrieved successfully',
                'data' => $products
            ]);
            http_response_code(200);
        } catch (Exception $e) { // Catch generic Exception
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
        $image_file = $_FILES['product_image'] ?? null;


        // Basic validation for required fields
        if (empty($data['name']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid input data. Name, price are required.'
            ]);
            return;
        }

        $uploadedImageUrl = null;
        if ($image_file && $image_file['error'] === 0) {
            // Validate the image file type and size
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($image_file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid image file type. Only JPEG, PNG, and GIF are allowed.'
                ]);
                return;
            }

            // Move the uploaded file to a temporary location
            $tempPath = $image_file['tmp_name'];
            $uploadedImageUrl = $this->imageUploader->uploadImage($tempPath, 'products/'); // Upload to Cloudinary
        }

        $productData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null, // Use null if not provided
            'price' => (float)$data['price'], // Cast price to float/decimal
            'product_image_url' => $uploadedImageUrl // Store the Cloudinary URL
        ];


        try {
            $newProductId = $this->productRepository->create($productData);

            if (!$newProductId) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to create product.'
                ]);
                return;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'id' => $newProductId,
                'data' => $productData
            ]);
            http_response_code(201);
        } catch (\RuntimeException $e) { // Catch RuntimeException from repository, meaning a DB issue
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create product: ' . $e->getMessage()
            ]);
        } catch (Exception $e) { // Catch any other unexpected exceptions
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display a single product by ID.
     * @param int $id The product ID from the URL.
     */
    public function GetProductById(string $id): void
    {
        try {
            $product = $this->productRepository->findById($id);

            if (!$product) {
                http_response_code(404); // Not Found
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found.'
                ]);
                return;
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

    /**
     * Update an existing product by ID.
     * @param int $id The product ID from the URL.
     */
    public function update(string $id): void
    {
        $data = $_POST;
        $imageFile = $_FILES['product_image'] ?? null;

        // echo json_encode($data);

        if (empty($data) && $imageFile === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
        }

        //validate required fields
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
                'price' => ($data['price'] ?? $existingProduct['price']),
                'product_image_url' => $existingProduct['product_image_url']
            ];


            $uploadedImageUrl = null;
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
                    $uploadedImageUrl = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'products/');
                    $productData['product_image_url'] = $uploadedImageUrl;

                    if ($existingProduct['product_image_url']) {
                        $this->imageUploader->deleteImage($existingProduct['product_image_url']);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Image update failed: ' . $e->getMessage()
                    ]);
                    return;
                }
            } elseif (isset($data['product_image_url']) && $data['product_image_url'] === null) {
                if ($existingProduct['product_image_url']) {
                    try {
                        $this->imageUploader->deleteImage($existingProduct['product_image_url']);
                        $productData['product_image_url'] = null;
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
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update product in DB: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
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
        echo json_encode($id);
        try {
            // Check if product exists before attempting to delete
            $existingProduct = $this->productRepository->findById($id);
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
