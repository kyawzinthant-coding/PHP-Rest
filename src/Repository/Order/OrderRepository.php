<?php

namespace App\Repository\Order;

use App\Core\Database;
use PDO;
use Ramsey\Uuid\Uuid;

class OrderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Creates a new order using a database transaction.
     * This ensures that all parts of the order are created successfully,
     * or none of them are.
     */

    public function findAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM Orders ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM Orders WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDetailsById(string $orderId): ?array
    {
        // 1. Get the main order details
        $orderStmt = $this->db->prepare("SELECT * FROM Orders WHERE id = :id");
        $orderStmt->execute([':id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // 2. Get the order items (line items)
        $itemsStmt = $this->db->prepare("
            SELECT oi.*, p.name as product_name 
            FROM OrderItems oi
            JOIN Products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ");
        $itemsStmt->execute([':order_id' => $orderId]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get the order status history
        $historyStmt = $this->db->prepare("SELECT * FROM OrderStatusHistory WHERE order_id = :order_id ORDER BY created_at ASC");
        $historyStmt->execute([':order_id' => $orderId]);
        $order['history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    public function updateStatus(string $orderId, string $newStatus): bool
    {
        $this->db->beginTransaction();
        try {
            // Step 1: Update the status in the Orders table
            $orderUpdateStmt = $this->db->prepare("UPDATE Orders SET status = :status WHERE id = :id");
            $orderUpdateStmt->execute([':status' => $newStatus, ':id' => $orderId]);

            // Step 2: Insert a new record into the OrderStatusHistory table
            $historyStmt = $this->db->prepare(
                "INSERT INTO OrderStatusHistory (id, order_id, status) VALUES (:id, :order_id, :status)"
            );
            $historyStmt->execute([
                ':id' => Uuid::uuid4()->toString(),
                ':order_id' => $orderId,
                ':status' => $newStatus
            ]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update order status: " . $e->getMessage());
            return false;
        }
    }

    public function create(array $orderData): string
    {
        $this->db->beginTransaction();

        try {
            // Step 1: Create the main order record
            $orderId = Uuid::uuid4()->toString();
            $orderNumber = substr(strtoupper(str_replace('-', '', Uuid::uuid4()->toString())), 0, 10);

            $orderStmt = $this->db->prepare(
                "INSERT INTO Orders (id, user_id, order_number, status, total_amount, shipping_customer_name, shipping_customer_email)
                 VALUES (:id, :user_id, :order_number, 'Pending', :total_amount, :shipping_customer_name, :shipping_customer_email)"
            );
            $orderStmt->execute([
                ':id' => $orderId,
                ':user_id' => $orderData['userId'],
                ':order_number' => $orderNumber,
                ':total_amount' => $orderData['totalAmount'],
                ':shipping_customer_name' => $orderData['shippingDetails']['name'],
                ':shipping_customer_email' => $orderData['shippingDetails']['email'],
            ]);

            // Step 2: Create the OrderItems records
            $itemsStmt = $this->db->prepare(
                "INSERT INTO OrderItems (id, order_id, product_id, quantity, price_at_purchase)
                 VALUES (:id, :order_id, :product_id, :quantity, :price_at_purchase)"
            );
            $stockStmt = $this->db->prepare(
                "UPDATE Products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id"
            );

            foreach ($orderData['items'] as $item) {
                $itemsStmt->execute([
                    ':id' => Uuid::uuid4()->toString(),
                    ':order_id' => $orderId,
                    ':product_id' => $item['id'],
                    ':quantity' => $item['quantity'],
                    ':price_at_purchase' => $item['price']
                ]);

                // Step 3: Update stock quantity for each product
                $stockStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $item['id']
                ]);
            }

            // If all queries were successful, commit the transaction
            $this->db->commit();

            return $orderId;
        } catch (\Exception $e) {
            // If any query fails, roll back all changes
            $this->db->rollBack();
            // Log the error and re-throw it to be handled by the controller
            error_log("Order creation failed: " . $e->getMessage());
            throw new \RuntimeException("Failed to create the order.", 500, $e);
        }
    }
}
