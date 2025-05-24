<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';

use App\Repository\Product\ProductRepository;
use App\Service\CloudinaryImageUploader;
use Ramsey\Uuid\Uuid;

echo "Starting database seeding...\n";

$productRepository = new ProductRepository();
$imageUploader = new CloudinaryImageUploader(); // Ensure CLOUDINARY_ constants are loaded

$seedProducts = [
    [
        'name' => 'Developer Laptop Pro',
        'description' => 'High-performance laptop for all your coding needs.',
        'price' => 1499.99,
        'image_source' => __DIR__ . '/seed_data/images/laptop_pro.jpg', // Local path
        'source_type' => 'local'
    ],
    [
        'name' => 'Mechanical Keyboard RGB',
        'description' => 'Clicky and responsive keyboard with RGB lighting.',
        'price' => 129.99,
        'image_source' => __DIR__ . '/seed_data/images/keyboard_rgb.jpg', // Local path
        'source_type' => 'local'
    ],
    [
        'name' => 'Wireless Ergonomic Mouse',
        'description' => 'Comfortable mouse for long hours of work.',
        'price' => 79.50,
        'image_source' => 'https://placehold.co/600x400/EANDDE/31343C?text=ErgoMouse', // Remote URL
        'source_type' => 'remote'
    ],
    // Add more products
];

foreach ($seedProducts as $productData) {
    $cloudinaryPublicId = null;
    $uploadedImageUrl = null; // For logging

    echo "Processing product: {$productData['name']}...\n";


    // Handle Image Upload
    if (!empty($productData['image_source'])) {
        $filePathToUpload = null;
        $isTempFile = false;

        if ($productData['source_type'] === 'local' && file_exists($productData['image_source'])) {
            $filePathToUpload = $productData['image_source'];
        } elseif ($productData['source_type'] === 'remote') {
            try {
                echo "Downloading remote image from: {$productData['image_source']}...\n";
                $imageContents = file_get_contents($productData['image_source']);
                if ($imageContents === false) {
                    throw new Exception("Failed to download image.");
                }
                // Create a temporary file to upload to Cloudinary
                $tempFileName = sys_get_temp_dir() . '/' . Uuid::uuid4()->toString() . '_' . basename($productData['image_source']);
                file_put_contents($tempFileName, $imageContents);
                $filePathToUpload = $tempFileName;
                $isTempFile = true;
                echo "Remote image downloaded to: {$filePathToUpload}\n";
            } catch (Exception $e) {
                echo "Could not process remote image for {$productData['name']}: " . $e->getMessage() . "\n";
                $filePathToUpload = null; // Ensure it's null if download failed
            }
        }

        if ($filePathToUpload) {
            try {
                echo "Uploading to Cloudinary: {$filePathToUpload}...\n";
                // The second argument to uploadImage is a prefix for the public ID (folder in Cloudinary)
                $uploadResult = $imageUploader->uploadImage($filePathToUpload, 'products/seed/');
                $cloudinaryPublicId = $uploadResult['public_id'];
                $uploadedImageUrl = $uploadResult['secure_url']; // For logging
                echo "Image uploaded successfully. Public ID: {$cloudinaryPublicId}, URL: {$uploadedImageUrl}\n";
            } catch (Exception $e) {
                echo "Cloudinary upload failed for {$productData['name']}: " . $e->getMessage() . "\n";
                // If it was a downloaded temp file, it will be cleaned up below
            } finally {
                if ($isTempFile && file_exists($filePathToUpload)) {
                    unlink($filePathToUpload); // Clean up temporary file
                    echo "Temporary file {$filePathToUpload} deleted.\n";
                }
            }
        }
    }

    // Prepare data for repository
    $repoData = [
        'name' => $productData['name'],
        'description' => $productData['description'],
        'price' => $productData['price'],
        'cloudinary_public_id' => $cloudinaryPublicId // This will be null if upload failed or no image
    ];

    try {
        $newProductId = $productRepository->create($repoData);
        echo "Product '{$productData['name']}' created successfully with ID: {$newProductId}.\n";
    } catch (App\Repository\DuplicateEntryException $e) {
        echo "Database error (likely duplicate): Failed to create product '{$productData['name']}'. Message: " . $e->getMessage() . "\n";
        // If product creation failed but image was uploaded, you might want to delete the orphaned image.
        if ($cloudinaryPublicId) {
            try {
                // $imageUploader->deleteImage($cloudinaryPublicId);
                // echo "Orphaned Cloudinary image {$cloudinaryPublicId} deleted.\n";
            } catch (Exception $delE) {
                // error_log("Failed to delete orphaned image {$cloudinaryPublicId}: " . $delE->getMessage());
            }
        }
    } catch (Exception $e) {
        echo "Failed to create product '{$productData['name']}'. Message: " . $e->getMessage() . "\n";
        // Similar orphan cleanup if needed
        if ($cloudinaryPublicId) {
            // Consider deleting orphaned image here too.
        }
    }
    echo "--------------------------------------------------\n";
}

echo "Database seeding finished.\n";
