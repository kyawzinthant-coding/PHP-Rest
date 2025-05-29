<?php

namespace App\Controller\Brand;

use App\Repository\Brand\BrandRepository;
use App\Validate\BrandValidation;
use App\Exception\ValidationException;
use Exception;
use RuntimeException;
use App\Repository\DuplicateEntryException;
use App\Utils\ImageUrlHelper;
use App\Service\CloudinaryImageUploader;


class BrandController
{
    private BrandRepository $brandRepository;
    private CloudinaryImageUploader $imageUploader;

    public function __construct()
    {
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

    public function index(): void
    {
        try {
            $brands = $this->brandRepository->getAllBrands();

            if (empty($brands)) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No brands found.'
                ]);
                return;
            }
            $transformedBrands = ImageUrlHelper::transformItemsWithImageUrls($brands, 'brand_cloudinary_public_id', 'image_url');



            echo json_encode([
                'status' => 'success',
                'message' => 'Brand list retrieved successfully',
                'data' => $transformedBrands
            ]);
            http_response_code(200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An error occurred while retrieving brands.'
            ]);
        }
    }

    public function create(): void
    {

        $cloudinaryPublicId = null;
        $displayedImageUrl = null;

        try {
            $validation = new BrandValidation();
            $data = $validation->validate();

            $imageFile = $data['image_file'];



            if ($imageFile) {
                try {
                    $uploadResult = $this->imageUploader->uploadImage($imageFile['tmp_name'], 'brand/');
                    $displayedImageUrl = $uploadResult['secure_url'];
                    $cloudinaryPublicId = $uploadResult['public_id'];
                } catch (Exception $e) {
                    // No need to cleanup orphaned image here, as it wasn't associated with a DB record yet
                    throw new RuntimeException('Image upload failed: ' . $e->getMessage(), 500, $e);
                }
            }
            $brandDataForRepo = [
                'name' => $data['name'],
                'brand_cloudinary_public_id' => $cloudinaryPublicId,
            ];

            $newBrandId = $this->brandRepository->create($brandDataForRepo);

            if (!$newBrandId) {
                throw new RuntimeException('Failed to create brand.');
            }
            echo json_encode([
                'status' => 'success',
                'message' => 'Brand created successfully',
                'data' => [
                    'id' => $newBrandId,
                    'name' => $data['name'],
                    'brand_cloudinary_public_id' => $cloudinaryPublicId,
                    'image_url' => $displayedImageUrl
                ]
            ]);
            http_response_code(201);
        } catch (ValidationException $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw new RuntimeException("Validation failed: " . implode(", ", $e->getErrors()));
        } catch (DuplicateEntryException $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw new RuntimeException("Brand already exists: " . $e->getMessage());
        } catch (Exception $e) {
            $this->cleanupOrphanedImage($cloudinaryPublicId, "after duplicate entry during store");
            throw new RuntimeException("Failed to create Brand: " . $e->getMessage());
        }
    }

    public function delete(string $id): void
    {
        try {
            $brand = $this->brandRepository->findById($id);
            if (!$brand) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Brand not found.'
                ]);
                return;
            }

            if ($brand['brand_cloudinary_public_id']) {
                try {
                    $this->imageUploader->deleteImage($brand['brand_cloudinary_public_id']);
                } catch (Exception $e) {
                    error_log("Failed to delete Cloudinary image: " . $e->getMessage());
                }
            }

            if ($this->brandRepository->delete($id)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Brand deleted successfully'
                ]);
                http_response_code(200);
            } else {
                throw new RuntimeException('Failed to delete brand.');
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'An error occurred while deleting the brand.'
            ]);
        }
    }
}
