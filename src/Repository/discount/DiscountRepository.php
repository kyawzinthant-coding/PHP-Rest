<?php

namespace App\Repository\Discount;

use App\Core\Database;
use PDO;
use Ramsey\Uuid\Uuid;

class DiscountRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }


    public function findById(string $id): ?array
    {
        $sql = "
            SELECT d.*, GROUP_CONCAT(pd.product_id) AS applicable_product_ids
            FROM Discounts d
            LEFT JOIN ProductDiscounts pd ON d.id = pd.discount_id
            WHERE d.id = :id
            GROUP BY d.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($discount && $discount['applicable_product_ids']) {
            $discount['applicable_product_ids'] = explode(',', $discount['applicable_product_ids']);
        }

        return $discount ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM Discounts ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(string $id, array $data): bool
    {
        // This dynamically builds the UPDATE query based on the data provided
        $fields = [];
        $params = ['id' => $id];
        foreach ($data as $key => $value) {
            if (in_array($key, ['code', 'description', 'discount_type', 'value', 'start_date', 'end_date', 'is_active'])) {
                $fields[] = "`$key` = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE Discounts SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("UPDATE Discounts SET is_active = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Creates the main discount record.
     * @return string The ID of the newly created discount.
     */
    public function createDiscount(array $data): string
    {
        $newId = Uuid::uuid4()->toString();
        $sql = "INSERT INTO Discounts (id, code, description, discount_type, value, start_date, end_date, is_active) 
                VALUES (:id, :code, :description, :discount_type, :value, :start_date, :end_date, :is_active)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $newId,
            ':code' => $data['code'],
            ':description' => $data['description'],
            ':discount_type' => $data['discount_type'],
            ':value' => $data['value'],
            ':start_date' => $data['start_date'] ?? null,
            ':end_date' => $data['end_date'] ?? null,
            ':is_active' => $data['is_active'] ?? true,
        ]);

        return $newId;
    }

    /**
     * Links a discount to a list of product IDs.
     * It first removes old links to ensure a clean update.
     */
    public function linkProductsToDiscount(string $discountId, array $productIds): void
    {
        // First, remove any existing links for this discount
        $stmtDelete = $this->db->prepare("DELETE FROM ProductDiscounts WHERE discount_id = :discount_id");
        $stmtDelete->execute([':discount_id' => $discountId]);

        // If there are new products to link, add them
        if (!empty($productIds)) {
            $sql = "INSERT INTO ProductDiscounts (product_id, discount_id) VALUES ";
            $placeholders = [];
            $values = [];
            foreach ($productIds as $productId) {
                $placeholders[] = '(?, ?)';
                $values[] = $productId;
                $values[] = $discountId;
            }
            $sql .= implode(', ', $placeholders);

            $stmtInsert = $this->db->prepare($sql);
            $stmtInsert->execute($values);
        }
    }

    // ... (keep the findActiveByCode method from before) ...
    public function findActiveByCode(string $code): ?array
    {
        // This query finds the discount and also gathers all associated product IDs into a single string
        $sql = "
            SELECT 
                d.*,
                GROUP_CONCAT(pd.product_id) AS applicable_product_ids
            FROM Discounts d
            LEFT JOIN ProductDiscounts pd ON d.id = pd.discount_id
            WHERE d.code = :code 
              AND d.is_active = 1
              AND (d.start_date IS NULL OR d.start_date <= NOW())
              AND (d.end_date IS NULL OR d.end_date >= NOW())
            GROUP BY d.id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($discount && isset($discount['applicable_product_ids'])) {
            // Convert the comma-separated string of IDs into an array
            $discount['applicable_product_ids'] = explode(',', $discount['applicable_product_ids']);
        } else if ($discount) {
            // Handle discounts that apply to all products (no entries in ProductDiscounts)
            $discount['applicable_product_ids'] = []; // An empty array signifies it applies to everything
        }

        return $discount ?: null;
    }
}
