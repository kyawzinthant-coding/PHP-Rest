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

    public function update(string $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $updateFields = [];
        $bindings = [];

        $fieldMap = [
            'name' => PDO::PARAM_STR,
            'brand_cloudinary_public_id' => PDO::PARAM_STR,
        ];

        foreach ($data as $field => $value) {

            if (isset($fieldMap[$field])) {
                $updateFields[] = "`{$field}` = :{$field}";
                $bindings[':' . $field] = $value;
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $sql = "UPDATE `Brands` SET " . implode(', ', $updateFields) . " WHERE `id` = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_STR); // Bind the main ID for WHERE clause

            foreach ($bindings as $paramPlaceholder => $paramValue) {
                // Extract field name from placeholder (e.g., ':name' becomes 'name')
                $fieldName = ltrim($paramPlaceholder, ':');

                // Get the PDO type from fieldMap using the extracted fieldName
                $pdoType = $fieldMap[$fieldName] ?? PDO::PARAM_STR; // Default to STR if somehow not in map (should not happen)

                // Handle explicit nulls for nullable fields
                if ($paramValue === null && ($fieldName === 'brand_cloudinary_public_id' /* add other nullable fields here */)) {
                    $stmt->bindValue($paramPlaceholder, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($paramPlaceholder, $paramValue, $pdoType);
                }
            }

            $stmt->execute();
            return $stmt->rowCount() > 0; // True if any row was affected

        } catch (PDOException $e) {
            error_log("Error in BrandRepository::update for ID {$id}: " . $e->getMessage() . "\nSQL: " . $sql . "\nData: " . json_encode($data));
            if ($e->getCode() === '23000') { // Integrity constraint violation
                if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false && strpos(strtolower($e->getMessage()), 'name') !== false) {
                    throw new DuplicateEntryException("Update failed. The brand name already exists.");
                }
                throw new DuplicateEntryException("Update failed due to a data conflict (e.g., unique constraint).");
            }
            throw new RuntimeException("Could not update brand (ID: {$id}) in database: " . $e->getMessage(), 0, $e);
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
