<?php

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config/bootstrap.php';

use App\Core\Database;

echo "Starting database migration with new, enhanced schema...\n";

try {
    $pdo = Database::getInstance(); // Get the PDO instance

    // Disable foreign key checks to allow dropping tables in any order
    $pdo->exec("SET foreign_key_checks = 0");
    echo "Foreign key checks disabled.\n";

    // Get all tables to drop them
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        echo "Dropping existing tables...\n";
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "Dropped table: $table\n";
        }
    } else {
        echo "No tables found in the database to drop.\n";
    }

    echo "Creating new tables with UUIDs as primary keys...\n";

    // --- Core Tables ---

    $pdo->exec("
    CREATE TABLE `Users` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` VARCHAR(50) NOT NULL DEFAULT 'customer',
         `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Users' created.\n";

    $pdo->exec("
    CREATE TABLE `Brands` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL UNIQUE,
        `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
        `brand_cloudinary_public_id` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Brands' created.\n";

    $pdo->exec("
    CREATE TABLE `Categories` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL UNIQUE,
        `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
        `category_cloudinary_public_id` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Categories' created.\n";

    $pdo->exec("
    CREATE TABLE `Products` (
        `id` CHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `brand_id` CHAR(36) NOT NULL,
        `category_id` CHAR(36) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL,
        `stock_quantity` INT NOT NULL DEFAULT 0,
        `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
        `average_rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
        `review_count` INT NOT NULL DEFAULT 0,
        `size_ml` INT NOT NULL,
        `top_notes` TEXT,
        `middle_notes` TEXT,
        `base_notes` TEXT,
        `gender_affinity` VARCHAR(50) DEFAULT 'Unisex',
        `slug` VARCHAR(255) UNIQUE,
        `cloudinary_public_id` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_product_brand` (`brand_id`),
        INDEX `idx_product_category` (`category_id`),
        CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `Brands`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `Categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Products' created.\n";

    // --- Order & Payment Flow Tables ---

    $pdo->exec("
    CREATE TABLE `Orders` (
        `id` CHAR(36) PRIMARY KEY,
        `user_id` CHAR(36),
        `order_number` VARCHAR(50) NOT NULL UNIQUE,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
        `total_amount` DECIMAL(10,2) NOT NULL,
        `shipping_customer_name` VARCHAR(255) NOT NULL,
        `shipping_customer_email` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_order_user` (`user_id`),
        CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `Users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Orders' created.\n";

    $pdo->exec("
    CREATE TABLE `OrderItems` (
        `id` CHAR(36) PRIMARY KEY,
        `order_id` CHAR(36) NOT NULL,
        `product_id` CHAR(36) NOT NULL,
        `quantity` INT NOT NULL,
        `price_at_purchase` DECIMAL(10,2) NOT NULL,
        INDEX `idx_orderitem_order` (`order_id`),
        INDEX `idx_orderitem_product` (`product_id`),
        CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `Orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_orderitem_product` FOREIGN KEY (`product_id`) REFERENCES `Products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'OrderItems' created.\n";

    $pdo->exec("
    CREATE TABLE `OrderStatusHistory` (
        `id` CHAR(36) PRIMARY KEY,
        `order_id` CHAR(36) NOT NULL,
        `status` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_orderstatushistory_order` FOREIGN KEY (`order_id`) REFERENCES `Orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'OrderStatusHistory' created.\n";

    $pdo->exec("
    CREATE TABLE `Payments` (
        `id` CHAR(36) PRIMARY KEY,
        `order_id` CHAR(36) NOT NULL UNIQUE,
        `payment_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `payment_method_type` VARCHAR(50) NOT NULL,
        `payment_provider_txn_id` VARCHAR(255) UNIQUE,
        `amount` DECIMAL(10,2) NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Succeeded',
        `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `Orders`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Payments' created.\n";

    // --- New Feature Tables ---

    $pdo->exec("
    CREATE TABLE `Wishlists` (
        `user_id` CHAR(36) NOT NULL,
        `product_id` CHAR(36) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `product_id`),
        CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `Users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `Products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Wishlists' created.\n";

    $pdo->exec("
    CREATE TABLE `Reviews` (
        `id` CHAR(36) PRIMARY KEY,
        `user_id` CHAR(36) NOT NULL,
        `product_id` CHAR(36) NOT NULL,
        `rating` INT NOT NULL,
        `review_text` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_user_product_review` (`user_id`, `product_id`),
        CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `Users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `Products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Reviews' created.\n";

    $pdo->exec("
    CREATE TABLE `Discounts` (
        `id` CHAR(36) PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `description` TEXT,
        `discount_type` VARCHAR(20) NOT NULL,
        `value` DECIMAL(10,2) NOT NULL,
        `start_date` TIMESTAMP NULL,
        `end_date` TIMESTAMP NULL,
        `is_active` BOOLEAN NOT NULL DEFAULT TRUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'Discounts' created.\n";

    $pdo->exec("
    CREATE TABLE `ProductDiscounts` (
        `product_id` CHAR(36) NOT NULL,
        `discount_id` CHAR(36) NOT NULL,
        PRIMARY KEY (`product_id`, `discount_id`),
        CONSTRAINT `fk_productdiscounts_product` FOREIGN KEY (`product_id`) REFERENCES `Products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_productdiscounts_discount` FOREIGN KEY (`discount_id`) REFERENCES `Discounts`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table 'ProductDiscounts' created.\n";


    // Re-enable foreign key checks
    $pdo->exec("SET foreign_key_checks = 1");
    echo "Foreign key checks re-enabled.\n";

    echo "All tables created successfully.\n";
} catch (PDOException $e) {
    echo "Database migration failed: " . $e->getMessage() . "\n";
    // Re-enable foreign key checks in case of failure
    if (isset($pdo)) {
        $pdo->exec("SET foreign_key_checks = 1");
        echo "Foreign key checks re-enabled after failure.\n";
    }
    exit(1);
}

echo "Database migration finished.\n";
