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


    public function GetALlProduct(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM products ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll();
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
            $stmt = $this->db->prepare("INSERT INTO products (id,name, description, price, cloudinary_public_id) VALUES (:id ,:name, :description, :price, :cloudinary_public_id)");

            // Removed: $stmt->bindValue(':id', $newId, PDO::PARAM_STR); // No longer binding ID
            $stmt->bindParam(':id', $newId, PDO::PARAM_STR);
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $stmt->bindValue(':price', $data['price'], PDO::PARAM_STR);
            $stmt->bindValue(':cloudinary_public_id', $data['cloudinary_public_id'], PDO::PARAM_STR);

            $stmt->execute();
            return $newId;
        } catch (PDOException $e) {
            error_log("Error in ProductRepository::create: " . $e->getMessage());
            if ($e->getCode() === '23000') {
                throw new DuplicateEntryException("Product with this name already exists.");
            }
            throw new RuntimeException("Could not create product in database: " . $e->getMessage());
        }
    }
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
