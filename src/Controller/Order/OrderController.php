<?php

namespace App\Controller\Order;

use App\Core\Request;
use App\Repository\Order\OrderRepository;
use App\Repository\Product\ProductRepository;
use App\Repository\Discount\DiscountRepository;
use App\Service\EmailService;

class OrderController
{
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;
    private DiscountRepository $discountRepository;

    public function __construct()
    {
        $this->orderRepository = new OrderRepository();
        $this->productRepository = new ProductRepository();
        $this->discountRepository = new DiscountRepository();
    }

    /**
     * Creates a new order from a checkout request.
     */


    public function getOrders(Request $request): void
    {
        $user = $request->getAttribute('user');


        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
            return;
        }

        if ($user->role === 'admin') {
            $orders = $this->orderRepository->findAll();
        } else {
            $orders = $this->orderRepository->findByUser($user->id);
            foreach ($orders as &$order) {
                $order['first_item_image_url'] = \App\Utils\ImageUrlHelper::generateUrl($order['first_item_image_id']);
            }
        }



        echo json_encode(['status' => 'success', 'data' => $orders]);
    }

    public function getOrderById(Request $request, string $id): void
    {
        $user = $request->getAttribute('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
            return;
        }

        $order = $this->orderRepository->findDetailsById($id);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
            return;
        }

        // Security Check: Admins can see any order, but customers can only see their own.
        if ($user->role !== 'admin' && $order['user_id'] !== $user->id) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'You are not authorized to view this order.']);
            return;
        }

        if (!empty($order['items'])) {
            foreach ($order['items'] as &$item) { // Use "&" to modify in place
                $item['image_url'] = \App\Utils\ImageUrlHelper::generateUrl($item['cloudinary_public_id']);
            }
        }

        echo json_encode(['status' => 'success', 'data' => $order]);
    }

    public function updateStatus(Request $request, string $id): void
    {
        $user = $request->getAttribute('user');

        if (!$user || $user->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: Admins only.']);
            return;
        }

        $data = json_decode($request->body, true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'New status is required.']);
            return;
        }

        $success = $this->orderRepository->updateStatus($id, $newStatus);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Order status updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update order status.']);
        }
    }

    public function create(Request $request): void
    {
        // 1. Get authenticated user
        $user = $request->getAttribute('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to place an order.']);
            return;
        }

        $data = json_decode($request->body, true);
        $cartItems = $data['cartItems'] ?? [];
        $promoCode = $data['promoCode'] ?? null;
        $shippingDetails = $data['shippingDetails'] ?? null;

        if (empty($cartItems) || empty($shippingDetails)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cart items and shipping details are required.']);
            return;
        }

        try {
            // 2. Verify products and calculate final price on the backend
            $verifiedItems = [];
            $subtotal = 0;

            foreach ($cartItems as $item) {
                $product = $this->productRepository->findById($item['productId']);

                // Security check: product exists and has enough stock
                if (!$product || $product['stock_quantity'] < $item['quantity']) {
                    throw new \RuntimeException("Product '{$product['name']}' is out of stock or unavailable.");
                }

                $verifiedItems[] = [
                    'id' => $product['id'],
                    'price' => $product['price'],
                    'quantity' => $item['quantity']
                ];
                $subtotal += $product['price'] * $item['quantity'];
            }

            // 3. Apply discount, if any
            $totalDiscount = 0;
            if ($promoCode) {
                $discount = $this->discountRepository->findActiveByCode($promoCode);
                if ($discount) {
                    // (This is a simplified version of your discount calculation logic)
                    foreach ($verifiedItems as $item) {
                        $isEligible = empty($discount['applicable_product_ids']) || in_array($item['id'], $discount['applicable_product_ids']);
                        if ($isEligible) {
                            if ($discount['discount_type'] === 'percentage') {
                                $totalDiscount += ($item['price'] * $item['quantity']) * ($discount['value'] / 100);
                            }
                        }
                    }
                }
            }

            $finalTotal = $subtotal - $totalDiscount;

            // 4. MOCK PAYMENT: Assume payment is successful
            // In a real app, you would integrate with Stripe/PayPal here.

            // 5. Create the order
            $orderData = [
                'userId' => $user->id,
                'items' => $verifiedItems,
                'totalAmount' => $finalTotal,
                'shippingDetails' => $shippingDetails
            ];

            $newOrderId = $this->orderRepository->create($orderData);

            $emailService = new EmailService();
            $emailService->sendOrderConfirmation(
                $shippingDetails['email'],
                $shippingDetails['name'],
                [
                    'orderNumber' => 'ORD-' . substr($newOrderId, 0, 8), // Just an example
                    'items' => $verifiedItems,
                    'totalAmount' => $finalTotal
                ]
            );

            // 6. Respond with success
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Order placed successfully!',
                'data' => ['orderId' => $newOrderId]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
