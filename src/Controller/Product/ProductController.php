<?php

namespace App\Controller\Product;

use App\Core\Request;
use App\Repository\Product\ProductRepository;
use App\Repository\Category\CategoryRepository;
use App\Repository\Brand\BrandRepository;
use App\Service\CloudinaryImageUploader;
use App\Validate\ProductValidate; // Import the validation class
use App\Exception\ValidationException; // Import for type hinting if needed, or if you catch it specifically
use Exception;
use RuntimeException;
use App\Repository\DuplicateEntryException;
use App\Utils\ImageUrlHelper;
use App\utils\Tools;



class ProductController
{
    private ProductRepository $productRepository;
    private CategoryRepository $categoryRepository;
    private BrandRepository $brandRepository;
    private CloudinaryImageUploader $imageUploader;

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->brandRepository = new BrandRepository();
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
    public function getCategoryAndBrand(): void
    {
        $categories = $this->categoryRepository->getAllCategories();
        $brands = $this->brandRepository->getAllBrands();

        $categoryAndBrand = [
            'categories' => array_map(function ($category) {
                return [
                    'id' => $category['id'],
                    'name' => $category['name']
                ];
            }, $categories),
            'brands' => array_map(function ($brand) {
                return [
                    'id' => $brand['id'],
                    'name' => $brand['name']
                ];
            }, $brands),
        ];

        header('Content-Type: application/json');
        echo json_encode($categoryAndBrand);
    }




    public function index(Request $request): void // Expect the Request object
    {
        try {
            $filters = [];
            // Get filter parameters from the query string
            // Ensure you validate or sanitize these IDs if necessary
            if (!empty($request->get['categoryId'])) {
                $filters['categoryId'] = $request->get['categoryId'];
            }
            if (!empty($request->get['brandId'])) {
                $filters['brandId'] = $request->get['brandId'];
            }
            // Add more filters from $request->get as needed
            // if (!empty($request->get['isActive'])) {
            //     $filters['isActive'] = filter_var($request->get['isActive'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            // }


            $products = $this->productRepository->GetALlProduct($filters); // Use the new repository method

            // Your response for empty products was a 200 with status "error"
            // It's generally better to return 200 with status "success" and an empty data array.
            if (empty($products)) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'success', // Changed from 'error'
                    'message' => 'No products found matching the criteria.',
                    'length' => 0,
                    'data' => []
                ]);
                return;
            }

            $transformedProducts = ImageUrlHelper::transformItemsWithImageUrls($products, 'cloudinary_public_id', 'image_url');

            echo json_encode([
                'status' => 'success',
                'message' => 'Product list retrieved successfully.',
                'length' => count($transformedProducts),
                'data' => $transformedProducts
            ]);
        } catch (RuntimeException $e) { // Catch specific runtime exceptions from repo
            error_log("Controller Error in ProductController::index: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() // Or a generic "Failed to retrieve products"
            ]);
        } catch (Exception $e) { // Catch any other general exceptions
            error_log("Controller Error in ProductController::index: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred while retrieving products.'
            ]);
        }
    }

    public function store(Request $request): void
    {
        $cloudinaryPublicId = null;
        $displayedImageUrl = null;

        try {
            $validator = new ProductValidate($request);
            $validatedData = $validator->validateCreate(); // isUpdate = false by default

            $imageFile = $validatedData['product_image']; // Get validated image file info



            if ($imageFile) {
                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'products/');
                    $displayedImageUrl = $uploadResult['secure_url'];
                    $cloudinaryPublicId = $uploadResult['public_id'];
                } catch (Exception $e) {
                    // No need to cleanup orphaned image here, as it wasn't associated with a DB record yet
                    throw new RuntimeException('Image upload failed: ' . $e->getMessage(), 500, $e);
                }
            }

            $tools = new Tools();
            $productDataForRepo = array_merge(
                ['cloudinary_public_id' => $cloudinaryPublicId],
                ['slug' => $tools->generateSlug($validatedData['name'])],

                $validatedData
            );;

            $newProductId = $this->productRepository->create($productDataForRepo);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'id' => $newProductId,
                'data' => $productDataForRepo
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
                http_response_code(200);
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

    public function update(Request $request, string $id): void
    {
        $newlyUploadedCloudinaryPublicId = null; // To track if a new image was uploaded in this request
        $oldImageToDeleteOnSuccess = null;    // To track if an old image should be deleted after DB success

        $tools = new Tools();
        try {
            $existingProduct = $this->productRepository->findById($id);
            echo json_encode($existingProduct);
            if (!$existingProduct) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Product not found for update.']);
                return;
            }
            $originalCloudinaryPublicId = $existingProduct['cloudinary_public_id'];

            // Validate only the fields present in the request for an update
            $validator = new ProductValidate($request);
            $validatedData = $validator->validateUpdate(); // This now returns only submitted & validated fields

            $productDataForRepo = $validatedData; // Start with validated data


            // Generate a new slug if 'name' is present in validatedData and has changed
            if (isset($validatedData['name']) && $validatedData['name'] !== $existingProduct['name']) {
                $productDataForRepo['slug'] = $tools->generateSlug($validatedData['name']);
            }

            // Image handling
            if (isset($validatedData['product_image']) && $validatedData['product_image'] !== null) {
                // A new image was uploaded and validated
                $imageFileToUpload = $validatedData['product_image'];
                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFileToUpload['tmp_name'], 'products/');
                    $newlyUploadedCloudinaryPublicId = $uploadResult['public_id']; // Track this new upload
                    $productDataForRepo['cloudinary_public_id'] = $newlyUploadedCloudinaryPublicId;

                    // If there was an old image and it's different from the new one, mark it for deletion
                    if ($originalCloudinaryPublicId && $originalCloudinaryPublicId !== $newlyUploadedCloudinaryPublicId) {
                        $oldImageToDeleteOnSuccess = $originalCloudinaryPublicId;
                    }
                } catch (Exception $e) {
                    // If image upload fails, we might have already uploaded it before other validation failed
                    // or it failed now. Cleanup $newlyUploadedCloudinaryPublicId if it exists from a partial success.
                    $this->cleanupOrphanedImage($newlyUploadedCloudinaryPublicId, "after image upload failure during product update");
                    throw new RuntimeException('Image update failed during upload: ' . $e->getMessage(), 500, $e);
                }
            } elseif (isset($validatedData['remove_product_image']) && $validatedData['remove_product_image'] === true) {
                // Explicit request to remove the image
                if ($originalCloudinaryPublicId) {
                    $oldImageToDeleteOnSuccess = $originalCloudinaryPublicId;
                }
                $productDataForRepo['cloudinary_public_id'] = null; // Set to null in DB data
            } elseif (array_key_exists('cloudinary_public_id', $validatedData) && $validatedData['cloudinary_public_id'] === null) {
                // If cloudinary_public_id was explicitly sent as null (and validated as such)
                if ($originalCloudinaryPublicId) {
                    $oldImageToDeleteOnSuccess = $originalCloudinaryPublicId;
                }
                // $productDataForRepo['cloudinary_public_id'] is already null from $validatedData
            }
            // If no image operations, $productDataForRepo['cloudinary_public_id'] won't be set,
            // and the repository won't try to update it unless it was explicitly part of $validatedData.

            // Remove helper fields from data going to repository
            unset($productDataForRepo['image_file']);
            unset($productDataForRepo['remove_product_image']);

            if (empty($productDataForRepo)) {
                // No actual data changes submitted for product fields
                $finalProduct = $this->productRepository->findById($id);
                if ($finalProduct) $finalProduct['image_url'] = ImageUrlHelper::generateUrl($finalProduct['cloudinary_public_id']);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Product update processed; no textual data changes were submitted.',
                    'data' => $finalProduct
                ]);
                http_response_code(200);
                return;
            }

            $updated = $this->productRepository->update($id, $productDataForRepo);

            // If DB update was successful (or even if it didn't change rows but no error)
            // and an old image was marked for deletion, delete it now.
            if ($oldImageToDeleteOnSuccess) {
                $this->cleanupOrphanedImage($oldImageToDeleteOnSuccess, "after successful product update (image replaced/removed)");
            }

            $finalProduct = $this->productRepository->findById($id); // Fetch the possibly updated product
            if ($finalProduct) {
                $finalProduct['image_url'] = ImageUrlHelper::generateUrl($finalProduct['cloudinary_public_id']);
            }

            echo json_encode([
                'status' => 'success',
                'message' => $updated ? 'Product updated successfully.' : 'Product update processed; data may reflect current state if no actual changes were made to fields.',
                'data' => $finalProduct
            ]);
            http_response_code(200);
        } catch (DuplicateEntryException $e) {
            // If a new image was uploaded but DB update failed due to duplicate (e.g., name/slug)
            $this->cleanupOrphanedImage($newlyUploadedCloudinaryPublicId, "after duplicate entry error during update");
            throw $e; // Re-throw for index.php to handle
        } catch (ValidationException $e) {
            // If a new image was uploaded, but then other field validation (post-image) failed.
            $this->cleanupOrphanedImage($newlyUploadedCloudinaryPublicId, "after validation error during update");
            throw $e; // Re-throw
        } catch (RuntimeException $e) {
            // This might catch an image upload failure that threw RuntimeException,
            // or a RuntimeException from the repository.
            $this->cleanupOrphanedImage($newlyUploadedCloudinaryPublicId, "after runtime error during update");
            throw $e;
        } catch (Exception $e) {
            $this->cleanupOrphanedImage($newlyUploadedCloudinaryPublicId, "after unexpected general error during update");
            throw $e;
        }
    }

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
