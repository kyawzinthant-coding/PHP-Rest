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

        $sql = "INSERT INTO users (id, email, password, role) VALUES (:id,  :email, :password, :role)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $userId); // Or bind null for auto-increment
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
