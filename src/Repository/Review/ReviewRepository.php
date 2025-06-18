<?php

namespace App\Repository\Review;

use App\Core\Database;
use PDO;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class ReviewRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Creates a new review in the database.
     */
    public function create(array $data): string
    {
        $newId = Uuid::uuid4()->toString();
        $stmt = $this->db->prepare(
            "INSERT INTO Reviews (id, user_id, product_id, rating, review_text) 
             VALUES (:id, :user_id, :product_id, :rating, :review_text)"
        );
        $stmt->execute([
            ':id' => $newId,
            ':user_id' => $data['user_id'],
            ':product_id' => $data['product_id'],
            ':rating' => $data['rating'],
            ':review_text' => $data['review_text'],
        ]);
        return $newId;
    }

    /**
     * Checks if a user has already reviewed a specific product.
     */
    public function hasUserReviewedProduct(string $userId, string $productId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM Reviews WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);
        return $stmt->fetchColumn() !== false;
    }


    public function updateProductReviewSummary(string $productId): bool
    {
        // This single, powerful query does all the work.
        $sql = "
            UPDATE Products p
            SET 
                p.review_count = (SELECT COUNT(*) FROM Reviews r WHERE r.product_id = :product_id_1),
                p.average_rating = (SELECT AVG(r.rating) FROM Reviews r WHERE r.product_id = :product_id_2)
            WHERE p.id = :product_id_3
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind the same product ID to all three placeholders
            $stmt->bindParam(':product_id_1', $productId);
            $stmt->bindParam(':product_id_2', $productId);
            $stmt->bindParam(':product_id_3', $productId);
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Failed to update product review summary: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets all reviews for a specific product, joining with the Users table.
     */
    public function findByProduct(string $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.rating, r.review_text, r.created_at, u.name as user_name
            FROM Reviews r
            JOIN Users u ON r.user_id = u.id
            WHERE r.product_id = :product_id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
