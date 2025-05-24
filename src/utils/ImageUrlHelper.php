<?php

namespace App\Utils;

// Ensure CLOUDINARY_CLOUD_NAME is defined from bootstrap.php
if (!defined('CLOUDINARY_CLOUD_NAME')) {
}

class ImageUrlHelper
{
    /**
     * Generates a Cloudinary image URL from a public ID.
     *
     * @param string|null $publicId The Cloudinary public ID.
     * @return string|null The full secure image URL, or null if no public ID or if CLOUDINARY_CLOUD_NAME is not defined.
     */
    public static function generateUrl(?string $publicId): ?string
    {
        if (!defined('CLOUDINARY_CLOUD_NAME')) {
            error_log("CLOUDINARY_CLOUD_NAME is not defined. Image URL cannot be generated.");
            return null;
        }

        if (!empty($publicId)) {
            // Using .webp extension for potentially better compression and modern browser support
            return "https://res.cloudinary.com/" . CLOUDINARY_CLOUD_NAME . "/image/upload/" . $publicId . ".webp";
        }
        return null;
    }

    /**
     * Transforms an array of items (e.g., products, categories) to include image URLs.
     * Assumes each item in the array is an associative array that might contain
     * a key for the Cloudinary public ID.
     *
     * @param array $items An array of items.
     * @param string $publicIdKey The key in each item's array that holds the Cloudinary public ID (e.g., 'cloudinary_public_id').
     * @param string $imageUrlKey The key to assign the generated image URL to (e.g., 'image_url').
     * @return array Transformed items with the image URL added.
     */
    public static function transformItemsWithImageUrls(array $items, string $publicIdKey = 'cloudinary_public_id', string $imageUrlKey = 'image_url'): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_map(function ($item) use ($publicIdKey, $imageUrlKey) {
            $publicId = $item[$publicIdKey] ?? null;
            $item[$imageUrlKey] = self::generateUrl($publicId);
            return $item;
        }, $items);
    }
}
