<?php

namespace App\Repository\Brand;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Ramsey\Uuid\Uuid;

use App\Repository\DuplicateEntryException;

class BrandRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllBrands(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM brands ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function findById(string $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM brands WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function create(array $data): string
    {
        try {
            $newId = Uuid::uuid4()->toString();
            $stmt = $this->db->prepare("INSERT INTO brands (id, name, brand_cloudinary_public_id) VALUES (:id, :name, :brand_cloudinary_public_id)");

            $stmt->bindParam(':id', $newId, PDO::PARAM_STR);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':brand_cloudinary_public_id', $data['brand_cloudinary_public_id'], PDO::PARAM_STR);

            if (!$stmt->execute()) {
                throw new DuplicateEntryException("Brand with name '{$data['name']}' already exists.");
            }

            return $newId;
        } catch (DuplicateEntryException $e) {
            throw new DuplicateEntryException($e->getMessage());
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new RuntimeException("Failed to create brand: " . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM brands WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
