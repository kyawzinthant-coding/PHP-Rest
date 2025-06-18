<?php

namespace App\Controller\Review;

use App\Core\Request;
use App\Exception\ValidationException;
use App\Repository\Product\ProductRepository;
use App\Repository\Review\ReviewRepository;
use App\Validate\ReviewValidate;
use Ramsey\Uuid\Uuid;

class ReviewController
{
    private ReviewRepository $reviewRepository;
    private ProductRepository $productRepository;

    public function __construct()
    {
        $this->reviewRepository = new ReviewRepository();
        $this->productRepository = new ProductRepository(); // Needed to check if product exists
    }

    /**
     * Gets all reviews for a specific product.
     */
    public function getReviewsForProduct(string $productId): void
    {
        if (!Uuid::isValid($productId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid Product ID format.']);
            return;
        }

        $reviews = $this->reviewRepository->findByProduct($productId);

        echo json_encode([
            'status' => 'success',
            'message' => 'Reviews retrieved successfully.',
            'data' => $reviews
        ]);
    }

    /**
     * Creates a new review for a product.
     */
    public function create(Request $request, string $productId): void
    {
        // 1. Get authenticated user
        $user = $request->getAttribute('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to leave a review.']);
            return;
        }

        // 2. Validate input data (rating, review_text)
        try {
            $validator = new ReviewValidate($request);
            $validatedData = $validator->validateCreate();
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->getErrors()]);
            return;
        }

        // 3. Check Business Rules
        if (!$this->productRepository->findById($productId)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
            return;
        }
        if ($this->reviewRepository->hasUserReviewedProduct($user->id, $productId)) {
            http_response_code(409); // 409 Conflict
            echo json_encode(['status' => 'error', 'message' => 'You have already reviewed this product.']);
            return;
        }

        // (Optional but recommended: Check if user has purchased the product. This requires a new repository method.)

        // 4. Create the Review
        $reviewData = array_merge($validatedData, [
            'user_id' => $user->id,
            'product_id' => $productId
        ]);
        $newReviewId = $this->reviewRepository->create($reviewData);

        // 5. IMPORTANT: Update the product's summary stats
        $this->reviewRepository->updateProductReviewSummary($productId);

        // 6. Respond with success
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Thank you for your review!',
            'data' => ['id' => $newReviewId]
        ]);
    }
}
