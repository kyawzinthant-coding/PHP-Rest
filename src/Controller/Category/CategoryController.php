<?php

namespace App\Controller\Category;

use App\Repository\Category\CategoryRepository;
use App\Validate\CategoryValidate;
use App\Exception\ValidationException; // Import for type hinting if needed, or if you catch it specifically
use Exception;
use RuntimeException;
use App\Repository\DuplicateEntryException;
use App\Utils\ImageUrlHelper;
use App\Service\CloudinaryImageUploader;

class CategoryController
{
    private CategoryRepository $categoryRepository;
    private CloudinaryImageUploader $imageUploader;

    public function __construct()
    {
        $this->categoryRepository = new CategoryRepository();
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
            /*************  ✨ Windsurf Command 🌟  *************/
            $categories = $this->categoryRepository->getAllCategories();

            if (empty($categories)) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No categories found.'
                ]);
                return;
            }
            $transformedCategory = ImageUrlHelper::transformItemsWithImageUrls($categories, 'category_cloudinary_public_id', 'image_url');

            echo json_encode([
                'status' => 'success',
                'message' => 'Category list retrieved successfully',
                'data' => $transformedCategory
            ]);
            http_response_code(200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function show(string $id): ?array
    {
        /*******  5760bb32-7f69-4b0d-8022-6d76bacdb131  *******/
        return $this->categoryRepository->findById($id);
    }

    public function create(): void

    {
        $cloudinaryPublicId = null;
        $displayedImageUrl = null;
        try {
            $validate = new CategoryValidate();
            $validatedData = $validate->validate();

            $imageFile = $validatedData['image_file'] ?? null;

            if ($imageFile) {
                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'category/');
                    $displayedImageUrl = $uploadResult['secure_url'];
                    $cloudinaryPublicId = $uploadResult['public_id'];
                } catch (Exception $e) {
                    // No need to cleanup orphaned image here, as it wasn't associated with a DB record yet
                    throw new RuntimeException('Image upload failed: ' . $e->getMessage(), 500, $e);
                }
            }

            $categoryDataForRepo = array_merge(
                ['id' => null, 'category_cloudinary_public_id' => $cloudinaryPublicId],
                $validatedData
            );




            $newCategoryID =  $this->categoryRepository->create($categoryDataForRepo);


            if ($newCategoryID) {
                $response = [
                    'status' => 'success',
                    'message' => 'Category created successfully',
                    'data' => [
                        'id' => $newCategoryID,
                        'name' => $validatedData['name'],
                        'category_cloudinary_public_id' => $cloudinaryPublicId,
                        'image_url' => $displayedImageUrl
                    ]
                ];
                http_response_code(201);
                echo json_encode($response);
            } else {
                throw new RuntimeException('Failed to create category in the database.');
            }
        } catch (ValidationException $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw new RuntimeException("Validation failed: " . implode(", ", $e->getErrors()));
        } catch (DuplicateEntryException $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw new RuntimeException("Category already exists: " . $e->getMessage());
        } catch (Exception $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw new RuntimeException("Failed to create category: " . $e->getMessage());
        }
    }

    public function delete(string $id): void
    {
        try {
            $category = $this->categoryRepository->findById($id);
            if (!$category) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Category not found.'
                ]);
                return;
            }

            if ($category['category_cloudinary_public_id']) {
                try {
                    $this->imageUploader->deleteImage($category['category_cloudinary_public_id']);
                } catch (Exception $e) {
                    error_log("Failed to delete Cloudinary image {$category['category_cloudinary_public_id']} during product deletion: " . $e->getMessage());
                }
            }


            $deletedCategory = $this->categoryRepository->delete($id);

            if ($deletedCategory) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Category deleted successfully.'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to delete category.'
                ]);
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to delete category: " . $e->getMessage());
        }
    }
}
