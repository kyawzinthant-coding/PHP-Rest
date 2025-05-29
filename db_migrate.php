<?php

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config/bootstrap.php';

use App\Core\Database;

echo "Starting database migration (using UUIDs for IDs)...\n";

try {
    $pdo = Database::getInstance(); // Get the PDO instance

    // Disable foreign key checks
    $pdo->exec("SET foreign_key_checks = 0");
    echo "Foreign key checks disabled.\n";

    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        echo "Dropping existing tables...\n";
        foreach ($tables as $table) {
            $dropQuery = "DROP TABLE IF EXISTS `$table`"; // Use IF EXISTS for safety
            $pdo->exec($dropQuery);
            echo "Dropped table: $table\n";
        }
    } else {
        echo "No tables found in the database to drop.\n";
    }

    echo "Creating tables with UUIDs as primary keys...\n";

    // Create Users Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `Users` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` VARCHAR(50) NOT NULL DEFAULT 'customer',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Users' created.\n";

    // Create Brands Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `Brands` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL UNIQUE,
        `brand_cloudinary_public_id` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Brands' created.\n";

    // Create Categories Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `Categories` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL UNIQUE,
        `category_cloudinary_public_id` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Categories' created.\n";

    // Create Products Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `Products` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `brand_id` CHAR(36) NOT NULL,
        `category_id` CHAR(36) NOT NULL,
        `size_ml` INT NOT NULL,
        `price` DECIMAL(10, 2) NOT NULL,
        `slug` VARCHAR(255), -- Consider adding UNIQUE NOT NULL if used for routing
        `cloudinary_public_id` VARCHAR(255),
        `stock_quantity` INT NOT NULL DEFAULT 0,
        `top_notes` TEXT,
        `middle_notes` TEXT,
        `base_notes` TEXT,
        `gender_affinity` VARCHAR(50) DEFAULT 'Unisex',
        `is_active` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_product_brand` (`brand_id`),
        INDEX `idx_product_category` (`category_id`),
        CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `Brands`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `Categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Products' created.\n";

    // Create Orders Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `Orders` (
        `id` CHAR(36) PRIMARY KEY,
        `user_id` CHAR(36), -- Nullable if guest checkouts allowed
        `order_number` VARCHAR(50) NOT NULL UNIQUE,
        `order_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
        `total_amount` DECIMAL(10, 2) NOT NULL,
        `shipping_customer_name` VARCHAR(255) NOT NULL,
        `shipping_customer_email` VARCHAR(255) NOT NULL,
        `shipping_phone_number` VARCHAR(50),
        `shipping_address_line1` VARCHAR(255) NOT NULL,
        `shipping_city` VARCHAR(100) NOT NULL,
        `shipping_state_province` VARCHAR(100),
        `shipping_postal_code` VARCHAR(20) NOT NULL,
        `shipping_country` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_order_user` (`user_id`),
        CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `Users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE -- Or ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Orders' created.\n";

    // Create OrderItems Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `OrderItems` (
        `id` CHAR(36) PRIMARY KEY,
        `order_id` CHAR(36) NOT NULL,
        `product_id` CHAR(36) NOT NULL,
        `quantity` INT NOT NULL,
        `price_at_purchase` DECIMAL(10, 2) NOT NULL,
        `product_name_at_purchase` VARCHAR(255),
        INDEX `idx_orderitem_order` (`order_id`),
        INDEX `idx_orderitem_product` (`product_id`),
        CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `Orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_orderitem_product` FOREIGN KEY (`product_id`) REFERENCES `Products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE -- RESTRICT so product isn't deleted if in an order
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'OrderItems' created.\n";

    // Create Payments Table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `Payments` (
        `id` CHAR(36) PRIMARY KEY,
        `order_id` CHAR(36) NOT NULL UNIQUE,
        `payment_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `payment_method_type` VARCHAR(50) NOT NULL,
        `payment_provider_txn_id` VARCHAR(255) UNIQUE,
        `amount` DECIMAL(10, 2) NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Succeeded',
        `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `Orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Payments' created.\n";

    // Re-enable foreign key checks
    $pdo->exec("SET foreign_key_checks = 1");
    echo "Foreign key checks re-enabled.\n";

    echo "All tables created successfully with UUIDs as primary keys.\n";
} catch (PDOException $e) {
    echo "Database migration failed: " . $e->getMessage() . "\n";
    // Re-enable foreign key checks in case of failure mid-script
    if (isset($pdo)) {
        $pdo->exec("SET foreign_key_checks = 1");
        echo "Foreign key checks re-enabled after failure.\n";
    }
    exit(1);
}

echo "Database migration finished.\n";
