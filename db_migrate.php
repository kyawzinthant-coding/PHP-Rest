<?php

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config/bootstrap.php';

use App\Core\Database;

echo "Starting database migration...\n";

try {
    $pdo = Database::getInstance(); // Get the PDO instance

    // Disable foreign key checks
    $pdo->exec("SET foreign_key_checks = 0");

    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        foreach ($tables as $table) {
            $dropQuery = "DROP TABLE `$table`";
            $pdo->exec($dropQuery);
            echo "Dropped table: $table\n";
        }
    } else {
        echo "No tables found in the database.\n";
    }

    // Re-enable foreign key checks
    $pdo->exec("SET foreign_key_checks = 1");

    // Create tables
    $sql = "
    CREATE TABLE IF NOT EXISTS products (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        product_image_url VARCHAR(500) DEFAULT NULL,
        cloudinary_public_id VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS users (
        id CHAR(36) PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        refresh_token VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";

    $pdo->exec($sql);
    echo "Tables created successfully.\n";
} catch (PDOException $e) {
    echo "Database migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Database migration finished.\n";
