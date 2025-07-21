<?php

namespace App\Repository\User;

use App\Core\Database;
use PDO;
use Ramsey\Uuid\Uuid;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function update(string $userId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $fields = [];
        $params = ['id' => $userId];
        foreach ($data as $key => $value) {
            // Whitelist of updatable columns to prevent unwanted updates
            if (in_array($key, ['name', 'password'])) {
                $fields[] = "`$key` = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false; // No valid fields to update
        }

        $sql = "UPDATE Users SET " . implode(', ', $fields) . " WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("Failed to update user: " . $e->getMessage());
            return false;
        }
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare("SELECT id, name, email, role,is_active, created_at FROM Users ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRole(string $userId, string $newRole): bool
    {
        $stmt = $this->db->prepare("UPDATE Users SET role = :role WHERE id = :id");
        return $stmt->execute([':role' => $newRole, ':id' => $userId]);
    }

    public function disable(string $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE Users SET is_active = 0 WHERE id = :id");
        return $stmt->execute([':id' => $userId]);
    }

    public function findByEmail(string $email): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("UserRepository::findByEmail Error: " . $e->getMessage());
            return null;
        }
    }

    public function findById(string $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $user = $stmt->fetch();
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("UserRepository::findById Error: " . $e->getMessage());
            return null;
        }
    }

    public function create(array $data): ?string
    {
        // Generate UUID for new user if you're using UUIDs
        $userId = Uuid::uuid4()->toString(); // Or null if using auto-increment INT

        $sql = "INSERT INTO users (id,name, email, password, role) VALUES (:id,:name,:email, :password, :role)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $userId); // Or bind null for auto-increment
            $stmt->bindValue(':name', $data['name']); // Optional name field
            $stmt->bindValue(':email', $data['email']);
            $stmt->bindValue(':password', $data['password']); // Hashed password
            $stmt->bindValue(':role', $data['role'] ?? 'user');

            $stmt->execute();
            return $userId; // Or $this->db->lastInsertId() for auto-increment
        } catch (\PDOException $e) {
            error_log("UserRepository::create Error: " . $e->getMessage());
            // Handle duplicate email specifically if your DB throws a unique constraint error (e.g., 23000)
            if ($e->getCode() === '23000') { // Integrity constraint violation
                throw new \App\Repository\DuplicateEntryException("User with this email already exists.");
            }
            return null;
        }
    }
}
