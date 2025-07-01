<?php

namespace App\Repository\Wishlist;

use App\Core\Database;
use PDO;
use RuntimeException;

class WishlistRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Checks if a specific product is already in a user's wishlist.
     */
    public function find(string $userId, string $productId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Wishlists WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Adds a product to a user's wishlist.
     */
    public function add(string $userId, string $productId): bool
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO Wishlists (user_id, product_id) VALUES (:user_id, :product_id)");
            return $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);
        } catch (\PDOException $e) {
            // Ignore duplicate entry errors, as it means the item is already there.
            if ($e->getCode() === '23000') {
                return true;
            }
            throw new RuntimeException("Could not add item to wishlist: " . $e->getMessage());
        }
    }

    /**
     * Removes a product from a user's wishlist.
     */
    public function remove(string $userId, string $productId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Wishlists WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Gets all products in a user's wishlist.
     * This joins with the Products table to get full product details.
     */
    public function findByUser(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                b.name AS brand_name,
                c.name AS category_name
            FROM Wishlists w
            JOIN Products p ON w.product_id = p.id
            LEFT JOIN Brands b ON p.brand_id = b.id
            LEFT JOIN Categories c ON p.category_id = c.id
            WHERE w.user_id = :user_id AND p.is_active = 1
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWishlistedProductIds(string $userId): array
    {
        $stmt = $this->db->prepare("SELECT product_id FROM Wishlists WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
