<?php

namespace App\Controller\Wishlist;

use App\Core\Request;
use App\Utils\ImageUrlHelper;
use Ramsey\Uuid\Uuid;

use App\Repository\Wishlist\WishlistRepository;

class WishlistController
{
    private WishlistRepository $wishlistRepository;

    public function __construct()
    {
        $this->wishlistRepository = new WishlistRepository();
    }

    /**
     * Adds an item to the wishlist if it's not there,
     * or removes it if it is. A "toggle" action.
     */
    public function toggleWishlistItem(Request $request, string $productId): void
    {
        // 1. Get the authenticated user from the request (set by AuthMiddleware)
        $user = $request->getAttribute('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        // 2. Basic Validation: Check if the product ID is a valid UUID
        if (!Uuid::isValid($productId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid Product ID format.']);
            return;
        }

        // 3. Perform the toggle logic
        $isWishlisted = $this->wishlistRepository->find($user->id, $productId);

        if ($isWishlisted) {
            // Item exists, so remove it
            $this->wishlistRepository->remove($user->id, $productId);
            $message = 'Product removed from wishlist.';
        } else {
            // Item does not exist, so add it
            $this->wishlistRepository->add($user->id, $productId);
            $message = 'Product added to wishlist.';
        }

        // 4. Send a success response
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => $message]);
    }

    /**
     * Gets all products on the current user's wishlist.
     */
    public function getWishlist(Request $request): void
    {
        $user = $request->getAttribute('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $wishlistItems = $this->wishlistRepository->findByUser($user->id);

        // Add the full image URL to each product
        $transformedItems = ImageUrlHelper::transformItemsWithImageUrls($wishlistItems, 'cloudinary_public_id');

        echo json_encode([
            'status' => 'success',
            'message' => 'Wishlist retrieved successfully.',
            'data' => $transformedItems
        ]);
    }
}
