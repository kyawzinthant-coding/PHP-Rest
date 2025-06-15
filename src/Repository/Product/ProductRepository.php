<?php

namespace App\Repository\Product;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Ramsey\Uuid\Uuid;
use App\Repository\DuplicateEntryException;
use Error;

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
            error_log("Repository Error ", $e->getMessage());
            throw new RuntimeException("Could not retrieve product from database.", 500, $e);
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
                throw new Error($e->getMessage());
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
        if (empty($data)) {
            return false; // No data provided to update
        }

        $updateFields = [];
        $bindings = [];

        // Define all updatable fields and their PDO types from your Products schema
        $fieldMap = [
            'name' => PDO::PARAM_STR,
            'description' => PDO::PARAM_STR,
            'brand_id' => PDO::PARAM_STR,    // Assuming UUID string, FK
            'category_id' => PDO::PARAM_STR, // Assuming UUID string, FK
            'size_ml' => PDO::PARAM_INT,
            'price' => PDO::PARAM_STR,       // DECIMAL in DB, send as string for PDO
            'slug' => PDO::PARAM_STR,
            'cloudinary_public_id' => PDO::PARAM_STR, // Can be null
            'stock_quantity' => PDO::PARAM_INT,
            'top_notes' => PDO::PARAM_STR,    // TEXT, can be null
            'middle_notes' => PDO::PARAM_STR, // TEXT, can be null
            'base_notes' => PDO::PARAM_STR,   // TEXT, can be null
            'gender_affinity' => PDO::PARAM_STR,
            'is_active' => PDO::PARAM_INT,   // Storing boolean as 0 or 1
        ];

        foreach ($fieldMap as $field => $pdoType) {
            if (array_key_exists($field, $data)) { // Check if the field was actually sent for update
                $updateFields[] = "`{$field}` = :{$field}";
                $valueToBind = $data[$field];
                if ($field === 'is_active') { // Convert boolean to int for DB
                    $valueToBind = (int)(bool)$data[$field];
                }
                // If a field like cloudinary_public_id is explicitly set to null
                if ($valueToBind === null && ($field === 'cloudinary_public_id' || $field === 'top_notes' || $field === 'middle_notes' || $field === 'base_notes')) {
                    $bindings[":{$field}"] = [null, PDO::PARAM_NULL];
                } else {
                    $bindings[":{$field}"] = [$valueToBind, $pdoType];
                }
            }
        }

        if (empty($updateFields)) {
            return false; // No valid fields to update based on $fieldMap
        }

        // Add updated_at manually for every update
        $updateFields[] = "`updated_at` = CURRENT_TIMESTAMP";

        $sql = "UPDATE `Products` SET " . implode(', ', $updateFields) . " WHERE `id` = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);

            foreach ($bindings as $placeholder => $valueAndType) {
                $stmt->bindValue($placeholder, $valueAndType[0], $valueAndType[1]);
            }

            $stmt->execute();
            return $stmt->rowCount() > 0; // True if any row was affected
        } catch (PDOException $e) {
            error_log("ProductRepository::update Error for ID {$id}: " . $e->getMessage() . "\nSQL: " . $sql . "\nData: " . json_encode($data));
            if ($e->getCode() === '23000') { // Integrity constraint violation
                // More specific check if possible, e.g., for unique slug or name
                if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false && (strpos(strtolower($e->getMessage()), 'slug') !== false || strpos(strtolower($e->getMessage()), 'name') !== false)) {
                    throw new DuplicateEntryException("Update failed. The product name or slug conflicts with an existing product.");
                }
                throw new DuplicateEntryException("Update failed due to a data conflict (e.g., unique constraint).");
            }
            throw new RuntimeException("Could not update product (ID: {$id}) in database: " . $e->getMessage(), 0, $e);
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
