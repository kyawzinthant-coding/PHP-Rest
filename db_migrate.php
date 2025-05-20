<?php

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config/bootstrap.php';

use App\Core\Database;

echo "Starting database migration...\n";

try {
    $pdo = Database::getInstance(); // Get the database connection

    $sql = "
    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";

    $pdo->exec($sql); // Execute the SQL statement

    echo "Table 'products' created or already exists successfully.\n";
} catch (PDOException $e) {
    echo "Database migration failed: " . $e->getMessage() . "\n";
    exit(1); // Exit with an error code
}

echo "Database migration finished.\n";
