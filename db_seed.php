<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php'; // Should define DB constants, CLOUDINARY constants

use App\Core\Database;
use App\Repository\Product\ProductRepository;
use App\Service\CloudinaryImageUploader;
use Ramsey\Uuid\Uuid;

echo "Starting database seeding with UUIDs and actual image uploads...\n";

// Helper function to generate slugs
function slugify($text, string $divider = '-')
{
    // Remove pua marks (except apostrophes if you want to keep them for some reason)
    $text = preg_replace('/[^\pL\pN\s' . preg_quote($divider) . '\']/u', '', $text); // Allow apostrophes
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove', $text);
    $text = preg_replace('![^' . preg_quote($divider) . '\w\s]+!u', '', mb_strtolower($text));
    $text = preg_replace('![' . preg_quote($divider) . '\s]+!u', $divider, $text);
    return trim($text, $divider);
}

try {
    $pdo = Database::getInstance();
    $imageUploader = new CloudinaryImageUploader(); // Instantiate once

    // --- 1. Seed Users ---
    echo "Seeding Users...\n";
    $users_data = [
        ['name' => 'Admin User', 'email' => 'admin@exampleshop.com', 'password_plain' => 'AdminPass123!', 'role' => 'admin'],
        ['name' => 'Kyaw Zin Thant', 'email' => 'kyaw@example.com', 'password_plain' => 'CustomerPass123!', 'role' => 'customer'],
        ['name' => 'Jane Doe', 'email' => 'jane.doe@example.com', 'password_plain' => 'JanePass123!', 'role' => 'customer'],
    ];
    $user_ids_map = [];
    $stmtUser = $pdo->prepare("INSERT INTO Users (id, name, email, password, role) VALUES (:id, :name, :email, :password, :role)");
    foreach ($users_data as $userData) {
        $userId = Uuid::uuid4()->toString();
        $hashedPassword = password_hash($userData['password_plain'], PASSWORD_ARGON2ID);
        $stmtUser->execute([':id' => $userId, ':name' => $userData['name'], ':email' => $userData['email'], ':password' => $hashedPassword, ':role' => $userData['role']]);
        $user_ids_map[$userData['email']] = $userId;
    }
    echo count($users_data) . " Users seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 2. Seed Brands ---
    echo "Seeding Brands...\n";
    // Define a default local image for brands if specific one not provided
    $defaultBrandImagePath = __DIR__ . '/seed_data/images/coin.jpg'; // Using coin.jpg as a generic placeholder

    $brands_data = [
        // If you have specific images for brands, add an 'img_filename' key
        ['name' => 'Generic Goods Co.' /*, 'img_filename' => 'ggc_logo.png' */],
        ['name' => 'Tech Universe'     /*, 'img_filename' => 'techu_logo.png' */],
        ['name' => 'Artful Prints'     /*, 'img_filename' => 'artful_logo.png' */],
        ['name' => 'Lifestyle Wares'   /*, 'img_filename' => 'lifestyle_logo.png' */],
    ];
    $brand_ids_map = [];
    $stmtBrand = $pdo->prepare("INSERT INTO Brands (id, name, brand_cloudinary_public_id) VALUES (:id, :name, :brand_cloudinary_public_id)");
    foreach ($brands_data as $brandData) {
        $brandId = Uuid::uuid4()->toString();
        $brandCloudinaryPublicId = null;

        // Use specific image if defined, else default, else null
        $imageToUploadPath = $defaultBrandImagePath; // Default
        if (isset($brandData['img_filename'])) {
            $specificImagePath = __DIR__ . '/seed_data/images/' . $brandData['img_filename'];
            if (file_exists($specificImagePath)) {
                $imageToUploadPath = $specificImagePath;
            } else {
                echo "Warning: Specific image '{$brandData['img_filename']}' not found for brand '{$brandData['name']}'. Using default or null.\n";
            }
        }

        if (file_exists($imageToUploadPath)) {
            try {
                echo "Uploading brand image '{$imageToUploadPath}' for '{$brandData['name']}' to Cloudinary...\n";
                // Assuming 'brand/' prefix for Cloudinary folder structure
                $uploadResult = $imageUploader->uploadImage($imageToUploadPath, 'brand');
                $brandCloudinaryPublicId = $uploadResult['public_id'];
                echo "Brand image uploaded. Public ID: {$brandCloudinaryPublicId}\n";
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for brand '{$brandData['name']}': " . $e->getMessage() . "\n";
            }
        } else {
            echo "Default/specific brand image not found at '{$imageToUploadPath}' for brand '{$brandData['name']}'. Skipping image upload.\n";
        }

        $stmtBrand->execute([
            ':id' => $brandId,
            ':name' => $brandData['name'],
            ':brand_cloudinary_public_id' => $brandCloudinaryPublicId
        ]);
        $brand_ids_map[$brandData['name']] = $brandId;
    }
    echo count($brands_data) . " Brands seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 3. Seed Categories ---
    echo "Seeding Categories...\n";
    $defaultCategoryImagePath = __DIR__ . '/seed_data/images/bitcoin.jpg'; // Using bitcoin.jpg as a generic placeholder

    $categories_data = [
        // If you have specific images for categories, add an 'img_filename' key
        ['name' => 'Wall Art & Decor'],
        ['name' => 'Electronics'],
        ['name' => 'Lifestyle Items'],
        ['name' => 'Food & Drink Prints'],
    ];
    $category_ids_map = [];
    $stmtCategory = $pdo->prepare("INSERT INTO Categories (id, name, category_cloudinary_public_id) VALUES (:id, :name, :category_cloudinary_public_id)");
    foreach ($categories_data as $categoryData) {
        $categoryId = Uuid::uuid4()->toString();
        $categoryCloudinaryPublicId = null;

        $imageToUploadPath = $defaultCategoryImagePath; // Default
        if (isset($categoryData['img_filename'])) {
            $specificImagePath = __DIR__ . '/seed_data/images/' . $categoryData['img_filename'];
            if (file_exists($specificImagePath)) {
                $imageToUploadPath = $specificImagePath;
            } else {
                echo "Warning: Specific image '{$categoryData['img_filename']}' not found for category '{$categoryData['name']}'. Using default or null.\n";
            }
        }

        if (file_exists($imageToUploadPath)) {
            try {
                echo "Uploading category image '{$imageToUploadPath}' for '{$categoryData['name']}' to Cloudinary...\n";
                // Assuming 'category/' prefix for Cloudinary folder structure
                $uploadResult = $imageUploader->uploadImage($imageToUploadPath, 'category');
                $categoryCloudinaryPublicId = $uploadResult['public_id'];
                echo "Category image uploaded. Public ID: {$categoryCloudinaryPublicId}\n";
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for category '{$categoryData['name']}': " . $e->getMessage() . "\n";
            }
        } else {
            echo "Default/specific category image not found at '{$imageToUploadPath}' for category '{$categoryData['name']}'. Skipping image upload.\n";
        }

        $stmtCategory->execute([
            ':id' => $categoryId,
            ':name' => $categoryData['name'],
            ':category_cloudinary_public_id' => $categoryCloudinaryPublicId
        ]);
        $category_ids_map[$categoryData['name']] = $categoryId;
    }
    echo count($categories_data) . " Categories seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 4. Seed Products ---
    echo "Seeding Products...\n";
    $productRepository = new ProductRepository(); // Uses Database::getInstance()

    $seedProducts_data = [
        // Filenames here should exactly match your files in `seed_data/images/`
        // as per your screenshot
        ['name' => 'Hot Air Balloon Adventure Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 29.99, 'stock' => 50, 'img' => 'ballon.jpg', 'desc' => 'Colorful hot air balloon soaring in the sky. Perfect for dreamers.', 'size_ml' => 0],
        ['name' => 'Majestic Bird Watercolor', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 24.99, 'stock' => 40, 'img' => 'bird.jpg', 'desc' => 'Elegant watercolor print of a majestic bird in flight.', 'size_ml' => 0],
        ['name' => 'Bitcoin Crypto Canvas', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 39.99, 'stock' => 30, 'img' => 'bitcoin.jpg', 'desc' => 'Modern canvas print featuring the Bitcoin logo. For the crypto enthusiast.', 'size_ml' => 0],
        ['name' => 'Healthy Breakfast Poster', 'brand_name' => 'Artful Prints', 'category_name' => 'Food & Drink Prints', 'price' => 19.99, 'stock' => 60, 'img' => 'breakfast.jpg', 'desc' => 'Vibrant poster showcasing a delicious and healthy breakfast spread.', 'size_ml' => 0],
        ['name' => 'Enchanted Castle Illustration', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 34.99, 'stock' => 25, 'img' => 'castle.jpg', 'desc' => 'Whimsical illustration of an enchanted castle. Sparks the imagination.', 'size_ml' => 0],
        ['name' => 'Golden Coin Replica', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 15.50, 'stock' => 70, 'img' => 'coin.jpg', 'desc' => 'A shiny replica of an ancient golden coin. Great for collectors.', 'size_ml' => 0],
        ['name' => 'Dollar Bill Stack Prop', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 9.99, 'stock' => 100, 'img' => 'dollor.jpg', 'desc' => 'Realistic prop of a stack of dollar bills.', 'size_ml' => 0],
        ['name' => 'Gourmet Food Photography Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Food & Drink Prints', 'price' => 22.00, 'stock' => 45, 'img' => 'food.jpg', 'desc' => 'High-quality print of a gourmet food platter.', 'size_ml' => 0],
        ['name' => 'Curious Goat Farm Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 18.99, 'stock' => 55, 'img' => 'goat.jpg', 'desc' => 'Charming print of a curious goat on a farm.', 'size_ml' => 0],
        ['name' => 'Cozy Suburban House Model', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 45.00, 'stock' => 20, 'img' => 'house.jpg', 'desc' => 'Detailed model of a cozy suburban house.', 'size_ml' => 0],
        ['name' => 'RGB Mechanical Keyboard', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 129.99, 'stock' => 30, 'img' => 'keyboard_rgb.jpg', 'desc' => 'Clicky mechanical keyboard with customizable RGB lighting.', 'size_ml' => 1],
        ['name' => 'Pro Developer Laptop', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 1499.99, 'stock' => 15, 'img' => 'laptop_pro.jpg', 'desc' => 'High-performance laptop designed for developers and professionals.', 'size_ml' => 1],
        ['name' => 'Serene Mountain Landscape Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 27.50, 'stock' => 33, 'img' => 'mountain.jpg', 'desc' => 'Breathtaking print of a serene mountain landscape at dawn.', 'size_ml' => 0],
        ['name' => 'Panoramic Mountains Poster', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 32.00, 'stock' => 28, 'img' => 'mountains.jpg', 'desc' => 'Wide panoramic poster of a majestic mountain range.', 'size_ml' => 0],
        ['name' => 'Ergonomic Pro Mouse', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 79.50, 'stock' => 40, 'img' => 'mouse_pro.webp', 'desc' => 'Wireless ergonomic mouse for maximum comfort and productivity.', 'size_ml' => 1],
        ['name' => 'Red Girl Abstract Portrait', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 49.99, 'stock' => 18, 'img' => 'redgirl.jpg', 'desc' => 'Striking abstract portrait featuring a girl in red.', 'size_ml' => 0],
    ];
    $product_ids_map = [];
    foreach ($seedProducts_data as $productData) {
        $productCloudinaryPublicId = null;
        echo "Processing product: {$productData['name']}...\n";
        $localImagePath = __DIR__ . '/seed_data/images/' . $productData['img'];
        if (file_exists($localImagePath)) {
            try {
                echo "Uploading product image '{$localImagePath}' for '{$productData['name']}' to Cloudinary...\n";
                // Assuming 'products/' prefix for Cloudinary folder structure
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'products');
                $productCloudinaryPublicId = $uploadResult['public_id'];
                echo "Product image uploaded. Public ID: {$productCloudinaryPublicId}\n";
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for product '{$productData['name']}': " . $e->getMessage() . "\n";
            }
        } else {
            echo "Local image NOT FOUND for product '{$productData['name']}' at: {$localImagePath}.\n";
        }

        $brandId = $brand_ids_map[$productData['brand_name']] ?? null;
        $categoryId = $category_ids_map[$productData['category_name']] ?? null;
        if (!$brandId || !$categoryId) {
            echo "SKIPPING product '{$productData['name']}' due to missing Brand/Category UUID.\n";
            continue;
        }
        $repoData = [
            'name' => $productData['name'],
            'description' => $productData['desc'],
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'size_ml' => $productData['size_ml'],
            'price' => $productData['price'],
            'slug' => slugify($productData['name']),
            'cloudinary_public_id' => $productCloudinaryPublicId,
            'stock_quantity' => $productData['stock'],
            'top_notes' => null,
            'middle_notes' => null,
            'base_notes' => null,
            'gender_affinity' => 'Unisex',
            'is_active' => true,
        ];
        try {
            $newProductId = $productRepository->create($repoData);
            $product_ids_map[$productData['name']] = $newProductId;
            echo "Product '{$productData['name']}' created. ID: {$newProductId}.\n";
        } catch (App\Repository\DuplicateEntryException $e) {
            echo "DUPLICATE: Product '{$productData['name']}'. Msg: " . $e->getMessage() . "\n";
        } catch (Exception $e) {
            echo "ERROR creating product '{$productData['name']}'. Msg: " . $e->getMessage() . "\n";
        }
    }
    echo count($product_ids_map) . " products created.\n";
    echo "--------------------------------------------------\n";

    // --- 5. Seed Orders ---
    // (Using the same logic as before, ensuring $user_ids_map and $product_ids_map are correct)
    echo "Seeding Orders...\n";
    $orders_seed_data = [];
    if (isset($user_ids_map['kyaw@example.com']) && isset($user_ids_map['jane.doe@example.com'])) {
        $orders_seed_data = [
            [
                'user_email_key' => 'kyaw@example.com',
                'order_number' => 'ORD-' . date('Ymd') . '-G001',
                'status' => 'Delivered',
                'total_amount' => 0,
                'shipping_customer_name' => 'Kyaw Zin Thant',
                'shipping_customer_email' => 'kyaw@example.com',
                'shipping_phone_number' => '09123456789',
                'shipping_address_line1' => '123 Tech Rd',
                'shipping_city' => 'Yangon',
                'shipping_postal_code' => '11221',
                'shipping_country' => 'Myanmar',
                'shipping_state_province' => 'Yangon Region'
            ],
            [
                'user_email_key' => 'jane.doe@example.com',
                'order_number' => 'ORD-' . date('Ymd') . '-G002',
                'status' => 'Shipped',
                'total_amount' => 0,
                'shipping_customer_name' => 'Jane Doe',
                'shipping_customer_email' => 'jane.doe@example.com',
                'shipping_phone_number' => '09987654321',
                'shipping_address_line1' => '456 Art Plaza',
                'shipping_city' => 'Mandalay',
                'shipping_postal_code' => '05051',
                'shipping_country' => 'Myanmar',
                'shipping_state_province' => 'Mandalay Region'
            ],
        ];
    }
    $order_ids_map_by_order_number = [];
    $stmtOrder = $pdo->prepare("
        INSERT INTO Orders (id, user_id, order_number, status, total_amount, shipping_customer_name, shipping_customer_email, shipping_phone_number, shipping_address_line1, shipping_city, shipping_state_province, shipping_postal_code, shipping_country, order_date)
        VALUES (:id, :user_id, :order_number, :status, :total_amount, :shipping_customer_name, :shipping_customer_email, :shipping_phone_number, :shipping_address_line1, :shipping_city, :shipping_state_province, :shipping_postal_code, :shipping_country, NOW())
    ");
    foreach ($orders_seed_data as $orderData) {
        $orderId = Uuid::uuid4()->toString();
        $userId = $user_ids_map[$orderData['user_email_key']] ?? null;
        $stmtOrder->execute(array_merge($orderData, ['id' => $orderId, 'user_id' => $userId]));
        $order_ids_map_by_order_number[$orderData['order_number']] = $orderId;
    }
    echo count($orders_seed_data) . " Orders seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 6. Seed OrderItems & Update Order Totals ---
    echo "Seeding OrderItems and updating Order totals...\n";
    $order_items_seed_data = [];
    if (!empty($order_ids_map_by_order_number) && !empty($product_ids_map)) {
        if (isset($product_ids_map['Pro Developer Laptop']) && isset($product_ids_map['RGB Mechanical Keyboard'])) {
            $order_items_seed_data[] = ['order_number_key' => 'ORD-' . date('Ymd') . '-G001', 'product_name_key' => 'Pro Developer Laptop', 'quantity' => 1, 'price_at_purchase' => 1499.99];
            $order_items_seed_data[] = ['order_number_key' => 'ORD-' . date('Ymd') . '-G001', 'product_name_key' => 'RGB Mechanical Keyboard', 'quantity' => 1, 'price_at_purchase' => 129.99];
        }
        if (isset($product_ids_map['Bitcoin Crypto Canvas']) && isset($product_ids_map['Red Girl Abstract Portrait']) && isset($product_ids_map['Hot Air Balloon Adventure Print'])) {
            $order_items_seed_data[] = ['order_number_key' => 'ORD-' . date('Ymd') . '-G002', 'product_name_key' => 'Bitcoin Crypto Canvas', 'quantity' => 1, 'price_at_purchase' => 39.99];
            $order_items_seed_data[] = ['order_number_key' => 'ORD-' . date('Ymd') . '-G002', 'product_name_key' => 'Red Girl Abstract Portrait', 'quantity' => 1, 'price_at_purchase' => 49.99];
            $order_items_seed_data[] = ['order_number_key' => 'ORD-' . date('Ymd') . '-G002', 'product_name_key' => 'Hot Air Balloon Adventure Print', 'quantity' => 2, 'price_at_purchase' => 29.99];
        }
    }
    $stmtOrderItem = $pdo->prepare("
        INSERT INTO OrderItems (id, order_id, product_id, quantity, price_at_purchase, product_name_at_purchase)
        VALUES (:id, :order_id, :product_id, :quantity, :price_at_purchase, :product_name_at_purchase)
    ");
    $order_totals_to_update = [];
    foreach ($order_items_seed_data as $itemData) {
        $orderItemUuid = Uuid::uuid4()->toString();
        $orderUuid = $order_ids_map_by_order_number[$itemData['order_number_key']] ?? null;
        $productUuid = $product_ids_map[$itemData['product_name_key']] ?? null;
        if ($orderUuid && $productUuid) {
            $stmtOrderItem->execute([
                ':id' => $orderItemUuid,
                ':order_id' => $orderUuid,
                ':product_id' => $productUuid,
                ':quantity' => $itemData['quantity'],
                ':price_at_purchase' => $itemData['price_at_purchase'],
                ':product_name_at_purchase' => $itemData['product_name_key']
            ]);
            $order_totals_to_update[$orderUuid] = ($order_totals_to_update[$orderUuid] ?? 0.0) + ($itemData['quantity'] * $itemData['price_at_purchase']);
        } else {
            echo "SKIPPING order item for '{$itemData['product_name_key']}' (OrderKey: {$itemData['order_number_key']}) - Missing Order/Product UUID.\n";
        }
    }
    if (!empty($order_totals_to_update)) {
        $stmtUpdateOrderTotal = $pdo->prepare("UPDATE Orders SET total_amount = :total_amount WHERE id = :order_id");
        foreach ($order_totals_to_update as $order_uuid_to_update => $new_total) {
            $stmtUpdateOrderTotal->execute([':total_amount' => $new_total, ':order_id' => $order_uuid_to_update]);
        }
        echo "Order totals updated.\n";
    }
    echo count($order_items_seed_data) . " OrderItems processed.\n";
    echo "--------------------------------------------------\n";

    // --- 7. Seed Payments ---
    echo "Seeding Payments...\n";
    $payments_seed_data = [];
    foreach ($order_ids_map_by_order_number as $order_num => $order_uuid_for_payment) {
        if (isset($order_totals_to_update[$order_uuid_for_payment]) && $order_totals_to_update[$order_uuid_for_payment] > 0) {
            $payments_seed_data[] = [
                'order_id' => $order_uuid_for_payment,
                'payment_method_type' => ($order_num === 'ORD-' . date('Ymd') . '-G001') ? 'Stripe' : 'PayPal',
                'payment_provider_txn_id' => 'txn_sd_' . uniqid() . rand(100, 999), // Ensure more uniqueness
                'amount' => $order_totals_to_update[$order_uuid_for_payment],
                'status' => 'Succeeded',
                'currency' => 'USD'
            ];
        }
    }
    $stmtPayment = $pdo->prepare("
        INSERT INTO Payments (id, order_id, payment_method_type, payment_provider_txn_id, amount, status, currency, payment_date)
        VALUES (:id, :order_id, :payment_method_type, :payment_provider_txn_id, :amount, :status, :currency, NOW())
    ");
    foreach ($payments_seed_data as $paymentData) {
        $paymentId = Uuid::uuid4()->toString();
        $stmtPayment->execute(array_merge($paymentData, ['id' => $paymentId]));
    }
    echo count($payments_seed_data) . " Payments seeded.\n";
    echo "--------------------------------------------------\n";

    echo "\n🎉 Database seeding finished successfully! 🎉\n";
} catch (PDOException $e) {
    echo "\n❌ Database seeding FAILED (PDOException): " . $e->getMessage() . "\n";
    error_log("Seeding PDOException: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
} catch (Exception $e) {
    echo "\n❌ Database seeding FAILED (Exception): " . $e->getMessage() . "\n";
    error_log("Seeding Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
