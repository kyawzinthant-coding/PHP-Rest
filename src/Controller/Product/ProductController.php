<?php

namespace App\Controller\Product;

use App\Repository\Product\ProductRepository;
use Exception; // Import the generic Exception class for general error handling

class ProductController
{
    private ProductRepository $productRepository;

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
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
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['name']) || !isset($data['price'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid input data. Name and price are required.'
            ]);
            return;
        }

        try {
            $newProductId = $this->productRepository->create($data);

            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'id' => $newProductId,
                'data' => $data
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
    public function show(int $id): void
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
    public function update(int $id): void
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['name']) || !isset($data['price'])) {
            http_response_code(400); // Bad Request
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid input data. Name and price are required for update.'
            ]);
            return;
        }

        try {
            // Check if product exists before attempting to update
            $existingProduct = $this->productRepository->findById($id);
            if (!$existingProduct) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found for update.'
                ]);
                return;
            }

            $updated = $this->productRepository->update($id, $data);

            if ($updated) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Product updated successfully',
                    'data' => array_merge($existingProduct, $data) // Merge old data with new for response
                ]);
                http_response_code(200); // OK
            } else {
                // This might happen if execute() returns false but no exception was thrown
                // e.g., if there were no changes to apply, although rowCount() would be 0 then
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
                'message' => 'Failed to update product: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a product by ID.
     * @param int $id The product ID from the URL.
     */
    public function destroy(int $id): void
    {
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
                http_response_code(204); // No Content, successful deletion
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
