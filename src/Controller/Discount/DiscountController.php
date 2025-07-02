<?php

namespace App\Controller\Discount;

use App\Core\Request;
use App\Repository\Discount\DiscountRepository;

class DiscountController
{
    private DiscountRepository $discountRepository;

    public function __construct()
    {
        $this->discountRepository = new DiscountRepository();
    }

    public function index(): void
    {
        $discounts = $this->discountRepository->findAll();
        echo json_encode(['status' => 'success', 'data' => $discounts]);
    }


    public function getById(string $id): void
    {
        $discount = $this->discountRepository->findById($id);
        if (!$discount) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Discount not found.']);
            return;
        }
        echo json_encode(['status' => 'success', 'data' => $discount]);
    }

    public function update(Request $request, string $id): void
    {
        $data = json_decode($request->body, true);

        // Update main discount details
        $this->discountRepository->update($id, $data);

        // If product_ids are provided, update the links
        if (isset($data['product_ids'])) {
            $this->discountRepository->linkProductsToDiscount($id, $data['product_ids']);
        }

        echo json_encode(['status' => 'success', 'message' => 'Discount updated successfully.']);
    }

    public function delete(string $id): void
    {
        $this->discountRepository->delete($id);
        echo json_encode(['status' => 'success', 'message' => 'Discount deactivated successfully.']);
    }

    /**
     * ADMIN-ONLY: Creates a new discount and links it to products.
     */
    public function create(Request $request): void
    {
        // Note: In a real app, you would add logic to check if the user has an 'admin' role.

        $data = json_decode($request->body, true);

        // Basic validation (can be expanded in a dedicated validation class)
        if (empty($data['code']) || empty($data['discount_type']) || !isset($data['value'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Code, type, and value are required.']);
            return;
        }

        try {
            // Step 1: Create the main discount record
            $discountId = $this->discountRepository->createDiscount($data);

            // Step 2: Link the discount to products, if any are provided
            $productIds = $data['product_ids'] ?? []; // Expects an array of UUIDs
            if (!empty($productIds)) {
                $this->discountRepository->linkProductsToDiscount($discountId, $productIds);
            }

            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Discount created successfully.',
                'data' => ['id' => $discountId]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create discount: ' . $e->getMessage()]);
        }
    }


    public function apply(Request $request): void
    {
        $data = json_decode($request->body, true);
        $promoCode = $data['promoCode'] ?? null;
        $cartItems = $data['cartItems'] ?? []; // Expects [['productId' => 'uuid', 'quantity' => 2], ...]

        if (!$promoCode || empty($cartItems)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Promo code and cart items are required.']);
            return;
        }

        // 1. Find the active discount code
        $discount = $this->discountRepository->findActiveByCode($promoCode);
        if (!$discount) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired promo code.']);
            return;
        }

        // 2. We need the ProductRepository to get prices
        $productRepository = new \App\Repository\Product\ProductRepository();
        $totalDiscount = 0;
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $product = $productRepository->findById($item['productId']);
            if (!$product) continue;

            $itemPrice = $product['price'] * $item['quantity'];
            $subtotal += $itemPrice;

            // Check if this product is eligible for the discount
            $isEligible = empty($discount['applicable_product_ids']) || in_array($product['id'], $discount['applicable_product_ids']);

            if ($isEligible) {
                if ($discount['discount_type'] === 'percentage') {
                    $totalDiscount += $itemPrice * ($discount['value'] / 100);
                } elseif ($discount['discount_type'] === 'fixed_amount') {
                    $totalDiscount += ($discount['value'] * $item['quantity']);
                }
            }
        }

        $totalDiscount = min($subtotal, $totalDiscount); // Can't discount more than the total
        $newTotal = $subtotal - $totalDiscount;

        // 3. Return the calculated prices
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Promo code applied!',
            'data' => [
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discountApplied' => number_format($totalDiscount, 2, '.', ''),
                'newTotal' => number_format($newTotal, 2, '.', ''),
            ]
        ]);
    }
}
