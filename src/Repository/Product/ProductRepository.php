<?php

namespace App\Repository\Product;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Ramsey\Uuid\Uuid;


use App\Repository\DuplicateEntryException; // Make sure to include this line
class ProductRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }


    public function GetAllProduct(array $filters = []): array
    {
        try {
            $sql = "
                SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.size_ml,
                    p.price,
                    p.cloudinary_public_id,
                    p.stock_quantity,
                    p.top_notes,
                    p.middle_notes,
                    p.base_notes,
                    p.gender_affinity,
                    p.is_active,
                    p.created_at,
                    b.name AS brand_name,
                    c.name AS category_name
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN categories c ON p.category_id = c.id
            ";

            $whereClauses = [];
            $bindings = [];

            if (!empty($filters['categoryId'])) {
                $whereClauses[] = "p.category_id = :categoryId";
                $bindings[':categoryId'] = $filters['categoryId'];
            }

            if (!empty($filters['brandId'])) {
                $whereClauses[] = "p.brand_id = :brandId";
                $bindings[':brandId'] = $filters['brandId'];
            }

            if (isset($filters['isActive'])) {
                $whereClauses[] = "p.is_active = :isActive";
                $bindings[':isActive'] = $filters['isActive'] ? 1 : 0;
            }

            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            $sql .= " ORDER BY p.created_at DESC";

            try {
                $stmt = $this->db->prepare($sql);

                foreach ($bindings as $placeholder => $value) {
                    $stmt->bindValue($placeholder, $value);
                }

                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("ProductRepository::findAll Error: " . $e->getMessage() . " SQL: " . $sql);
                throw new RuntimeException("Could not retrieve products from database: " . $e->getMessage(), 0, $e);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    public function findById(string $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $product = $stmt->fetch();

            return $product ?: null;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    public function create(array $data): string
    {
        try {

            $newId = Uuid::uuid4()->toString();

            $stmt = $this->db->prepare("
                INSERT INTO products (
                id, name, description, price, cloudinary_public_id,
                brand_id, category_id, size_ml, stock_quantity,
                top_notes, middle_notes, base_notes, gender_affinity,
                is_active, slug
            ) VALUES (
                :id, :name, :description, :price, :cloudinary_public_id,
                :brand_id, :category_id, :size_ml, :stock_quantity,
                :top_notes, :middle_notes, :base_notes, :gender_affinity,
                :is_active, :slug
            )

            ");

            // Removed: $stmt->bindValue(':id', $newId, PDO::PARAM_STR); // No longer binding ID
            $stmt->bindParam(':id', $newId, PDO::PARAM_STR);
            $stmt->bindValue(':brand_id', $data['brand_id'], PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':price', $data['price'], PDO::PARAM_STR);
            $stmt->bindValue(':cloudinary_public_id', $data['cloudinary_public_id'], PDO::PARAM_STR);
            $stmt->bindValue(':category_id', $data['category_id'], PDO::PARAM_STR);
            $stmt->bindValue(':size_ml', $data['size_ml'], PDO::PARAM_INT);
            $stmt->bindValue(':stock_quantity', $data['stock_quantity'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':top_notes', $data['top_notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':middle_notes', $data['middle_notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':base_notes', $data['base_notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':is_active', !empty($data['is_active']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':slug', $data['slug'], PDO::PARAM_STR);
            $stmt->bindValue(':gender_affinity', $data['gender_affinity'] ?? 'Unisex', PDO::PARAM_STR);


            $stmt->execute();
            return $newId;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new DuplicateEntryException("Product with this name already exists.");
            }
            throw new RuntimeException("Could not create product in database: " . $e->getMessage());
        }
    }
    /**
     * Updates an existing product by its ID.
     * @param string $id The product ID from the URL (now a UUID string).
     * @param array $data The data to update for the product.
     * @return bool True if the update was successful, false otherwise.
     * @throws \App\Exception\RuntimeException If the update failed.
     */
    public function update(string $id, array $data): bool
    {
        try {
            $updateFields = [];
            $bindValues = []; // Use this array for values to bind

            if (isset($data['name'])) {
                $updateFields[] = 'name = :name';
                $bindValues[':name'] = [$data['name'], PDO::PARAM_STR]; // Store value and type
            }
            if (isset($data['description'])) {
                $updateFields[] = 'description = :description';
                $bindValues[':description'] = [$data['description'], PDO::PARAM_STR];
            }
            if (isset($data['price'])) {
                $updateFields[] = 'price = :price';
                $bindValues[':price'] = [$data['price'], PDO::PARAM_STR];
            }
            if (array_key_exists('cloudinary_public_id', $data)) {
                $updateFields[] = 'cloudinary_public_id = :cloudinary_public_id';
                $bindValues[':cloudinary_public_id'] = [$data['cloudinary_public_id'], PDO::PARAM_STR];
            }


            if (empty($updateFields)) {
                return false;
            }


            $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            // Bind the ID parameter separately as it's always there
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);

            // Now loop through other parameters and bind their values
            foreach ($bindValues as $paramName => $value) {
                // $value is now always an array [value, type]
                $stmt->bindValue($paramName, $value[0], $value[1]);
            }

            // --- REMOVE dd($bindParams); from here now that we've switched ---

            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error in ProductRepository::update: " . $e->getMessage());
            throw new RuntimeException("Could not update product in database: " . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error in ProductRepository::update: " . $e->getMessage());
            if ($e->getCode() === '23000') { // SQLSTATE for Integrity Constraint Violation
                throw new DuplicateEntryException("Product with this name already exists."); // <--- Throw custom exception
            }
            throw new RuntimeException("Could not update product in database: " . $e->getMessage());
        }
    }
}
