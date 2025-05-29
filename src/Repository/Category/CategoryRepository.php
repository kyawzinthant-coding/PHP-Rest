<?php

namespace App\Repository\Category;


use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Ramsey\Uuid\Uuid;


use App\Repository\DuplicateEntryException;

class CategoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllCategories(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM categories ORDER BY created_at DESC");
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
            $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = :id");
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

            echo json_encode($data);

            $newId = Uuid::uuid4()->toString();
            $stmt = $this->db->prepare("INSERT INTO categories (id, name,category_cloudinary_public_id) VALUES (:id, :name,:category_cloudinary_public_id)");

            $stmt->bindParam(':id', $newId, PDO::PARAM_STR);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':category_cloudinary_public_id', $data['category_cloudinary_public_id'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                return $newId;
            } else {
                throw new RuntimeException("Failed to create category.");
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // Duplicate entry error code
                throw new DuplicateEntryException("Category with this name already exists.");
            }
            error_log($e->getMessage());
            throw new RuntimeException("Database error: " . $e->getMessage());
        }
    }

    public function delete(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
