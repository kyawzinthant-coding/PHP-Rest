<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php'; // Should define DB_HOST, DB_NAME, DB_USER, DB_PASS

use App\Core\Database;
use App\Repository\Product\ProductRepository;
use App\Service\CloudinaryImageUploader;
use Ramsey\Uuid\Uuid;

echo "Starting database seeding...\n";

// Helper function to generate slugs
function slugify($text, string $divider = '-')
{
    $text = preg_replace('/\p{P}/u', '', $text); // Remove punctuation
    $text = preg_replace('![^' . preg_quote($divider) . '\pL\pN\s]+!u', '', mb_strtolower($text));
    $text = preg_replace('![' . preg_quote($divider) . '\s]+!u', $divider, $text);
    return trim($text, $divider);
}

try {
    $pdo = Database::getInstance();

    // --- 1. Seed Users ---
    echo "Seeding Users...\n";
    $users = [
        ['name' => 'Admin User', 'email' => 'admin@exampleshop.com', 'password_hash' => password_hash('AdminPass123!', PASSWORD_DEFAULT), 'role' => 'admin'],
        ['name' => 'Kyaw Zin Thant', 'email' => 'kyaw@example.com', 'password_hash' => password_hash('CustomerPass123!', PASSWORD_DEFAULT), 'role' => 'customer'],
        ['name' => 'Jane Doe', 'email' => 'jane.doe@example.com', 'password_hash' => password_hash('JanePass123!', PASSWORD_DEFAULT), 'role' => 'customer'],
    ];
    $user_ids = [];
    $stmt = $pdo->prepare("INSERT INTO Users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)");
    foreach ($users as $user) {
        $stmt->execute($user);
        $user_ids[$user['email']] = $pdo->lastInsertId();
    }
    echo count($users) . " Users seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 2. Seed Brands ---
    echo "Seeding Brands...\n";
    $brands = [
        ['name' => 'Generic Goods Co.', 'description' => 'Providers of quality generic items.', 'brand_cloudinary_public_id' => 'seed_generic/brands/ggc_logo_placeholder'],
        ['name' => 'Tech Universe', 'description' => 'All your tech gadget needs.', 'brand_cloudinary_public_id' => 'seed_generic/brands/techu_logo_placeholder'],
        ['name' => 'Artful Prints', 'description' => 'Decorative prints and art.', 'brand_cloudinary_public_id' => 'seed_generic/brands/artful_logo_placeholder'],
        ['name' => 'Lifestyle Wares', 'description' => 'Items for your everyday life.', 'brand_cloudinary_public_id' => 'seed_generic/brands/lifestyle_logo_placeholder'],
    ];
    $brand_ids = [];
    $stmt = $pdo->prepare("INSERT INTO Brands (name, description, brand_cloudinary_public_id) VALUES (:name, :description, :brand_cloudinary_public_id)");
    foreach ($brands as $brand) {
        $stmt->execute($brand);
        $brand_ids[$brand['name']] = $pdo->lastInsertId();
    }
    echo count($brands) . " Brands seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 3. Seed Categories ---
    echo "Seeding Categories...\n";
    $categories = [
        ['name' => 'Wall Art & Decor', 'description' => 'Prints and art for your walls.', 'category_cloudinary_public_id' => 'seed_generic/categories/wallart_placeholder'],
        ['name' => 'Electronics', 'description' => 'Gadgets and electronic devices.', 'category_cloudinary_public_id' => 'seed_generic/categories/electronics_placeholder'],
        ['name' => 'Lifestyle Items', 'description' => 'Everyday use items and novelties.', 'category_cloudinary_public_id' => 'seed_generic/categories/lifestyle_placeholder'],
        ['name' => 'Food & Drink Prints', 'description' => 'Art related to food and beverages.', 'category_cloudinary_public_id' => 'seed_generic/categories/fooddrink_prints_placeholder'],
    ];
    $category_ids = [];
    $stmt = $pdo->prepare("INSERT INTO Categories (name, description, category_cloudinary_public_id) VALUES (:name, :description, :category_cloudinary_public_id)");
    foreach ($categories as $category) {
        $stmt->execute($category);
        $category_ids[$category['name']] = $pdo->lastInsertId();
    }
    echo count($categories) . " Categories seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 4. Seed Products ---
    $productRepository = new ProductRepository($pdo);
    $imageUploader = new CloudinaryImageUploader();

    // NOTE: For `size_ml` (NOT NULL field), we use a dummy value '0' or '1'.
    // For `*_notes` and `gender_affinity`, we use NULL or default.
    $seedNewProducts = [
        // Name, Brand, Category, Price, Stock, Image Filename, Description (size_ml, notes, gender will be handled)
        ['name' => 'Hot Air Balloon Adventure Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 29.99, 'stock' => 50, 'img' => 'ballon.jpg', 'desc' => 'Colorful hot air balloon soaring in the sky. Perfect for dreamers.'],
        ['name' => 'Majestic Bird Watercolor', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 24.99, 'stock' => 40, 'img' => 'bird.jpg', 'desc' => 'Elegant watercolor print of a majestic bird in flight.'],
        ['name' => 'Bitcoin Crypto Canvas', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 39.99, 'stock' => 30, 'img' => 'bitcoin.jpg', 'desc' => 'Modern canvas print featuring the Bitcoin logo. For the crypto enthusiast.'],
        ['name' => 'Healthy Breakfast Poster', 'brand_name' => 'Artful Prints', 'category_name' => 'Food & Drink Prints', 'price' => 19.99, 'stock' => 60, 'img' => 'breakfast.jpg', 'desc' => 'Vibrant poster showcasing a delicious and healthy breakfast spread.'],
        ['name' => 'Enchanted Castle Illustration', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 34.99, 'stock' => 25, 'img' => 'castle.jpg', 'desc' => 'Whimsical illustration of an enchanted castle. Sparks the imagination.'],
        ['name' => 'Golden Coin Replica', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 15.50, 'stock' => 70, 'img' => 'coin.jpg', 'desc' => 'A shiny replica of an ancient golden coin. Great for collectors.'],
        ['name' => 'Dollar Bill Stack Prop', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 9.99, 'stock' => 100, 'img' => 'dollor.jpg', 'desc' => 'Realistic prop of a stack of dollar bills.'], // Assuming dollor.jpg is dollar
        ['name' => 'Gourmet Food Photography Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Food & Drink Prints', 'price' => 22.00, 'stock' => 45, 'img' => 'food.jpg', 'desc' => 'High-quality print of a gourmet food platter.'],
        ['name' => 'Curious Goat Farm Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 18.99, 'stock' => 55, 'img' => 'goat.jpg', 'desc' => 'Charming print of a curious goat on a farm.'],
        ['name' => 'Cozy Suburban House Model', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 45.00, 'stock' => 20, 'img' => 'house.jpg', 'desc' => 'Detailed model of a cozy suburban house.'],
        ['name' => 'RGB Mechanical Keyboard', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 129.99, 'stock' => 30, 'img' => 'keyboard_rgb.jpg', 'desc' => 'Clicky mechanical keyboard with customizable RGB lighting.'],
        ['name' => 'Pro Developer Laptop', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 1499.99, 'stock' => 15, 'img' => 'laptop_pro.jpg', 'desc' => 'High-performance laptop designed for developers and professionals.'],
        ['name' => 'Serene Mountain Landscape Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 27.50, 'stock' => 33, 'img' => 'mountain.jpg', 'desc' => 'Breathtaking print of a serene mountain landscape at dawn.'],
        ['name' => 'Panoramic Mountains Poster', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 32.00, 'stock' => 28, 'img' => 'mountains.jpg', 'desc' => 'Wide panoramic poster of a majestic mountain range.'],
        ['name' => 'Ergonomic Pro Mouse', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 79.50, 'stock' => 40, 'img' => 'mouse_pro.webp', 'desc' => 'Wireless ergonomic mouse for maximum comfort and productivity.'],
        ['name' => 'Red Girl Abstract Portrait', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 49.99, 'stock' => 18, 'img' => 'redgirl.jpg', 'desc' => 'Striking abstract portrait featuring a girl in red.'],
    ];

    $product_ids = []; // To store created product IDs by name for orders

    foreach ($seedNewProducts as $productData) {
        $cloudinaryPublicId = null;
        $uploadedImageUrl = null;

        echo "Processing product: {$productData['name']}...\n";

        $filePathToUpload = null;
        $isTempFile = false; // Not used here as all are local

        $imagePath = __DIR__ . '/seed_data/images/' . $productData['img'];
        if (file_exists($imagePath)) {
            $filePathToUpload = $imagePath;
        } else {
            echo "Local image not found for {$productData['name']} at {$imagePath}. Skipping image upload.\n";
        }

        if ($filePathToUpload) {
            try {
                echo "Uploading to Cloudinary: {$filePathToUpload}...\n";
                $uploadResult = $imageUploader->uploadImage($filePathToUpload, 'seed_generic/products/');
                $cloudinaryPublicId = $uploadResult['public_id'];
                $uploadedImageUrl = $uploadResult['secure_url'];
                echo "Image uploaded successfully. Public ID: {$cloudinaryPublicId}, URL: {$uploadedImageUrl}\n";
            } catch (Exception $e) {
                echo "Cloudinary upload failed for {$productData['name']}: " . $e->getMessage() . "\n";
            }
        }

        // Prepare data for repository
        $repoData = [
            'name' => $productData['name'],
            'slug' => slugify($productData['name']),
            'description' => $productData['desc'],
            'brand_id' => $brand_ids[$productData['brand_name']] ?? null,
            'category_id' => $category_ids[$productData['category_name']] ?? null,
            'size_ml' => 0, // Dummy value for NOT NULL constraint. Advise making this nullable.
            'price' => $productData['price'],
            'cloudinary_public_id' => $cloudinaryPublicId,
            'stock_quantity' => $productData['stock'],
            'top_notes' => null, // Not applicable for generic items
            'middle_notes' => null,
            'base_notes' => null,
            'gender_affinity' => 'Unisex', // Default
            'is_active' => true
        ];

        if (is_null($repoData['brand_id'])) {
            echo "Skipping product '{$productData['name']}' due to missing Brand ID for '{$productData['brand_name']}'.\n";
            continue;
        }
        if (is_null($repoData['category_id'])) {
            echo "Skipping product '{$productData['name']}' due to missing Category ID for '{$productData['category_name']}'.\n";
            continue;
        }

        try {
            $newProductId = $productRepository->create($repoData);
            $product_ids[$productData['name']] = $newProductId;
            echo "Product '{$productData['name']}' created successfully with ID: {$newProductId}.\n";
        } catch (App\Repository\DuplicateEntryException $e) {
            echo "Database error (likely duplicate): Failed to create product '{$productData['name']}'. Message: " . $e->getMessage() . "\n";
            // Optional: Delete orphaned Cloudinary image
        } catch (Exception $e) {
            echo "Failed to create product '{$productData['name']}'. Message: " . $e->getMessage() . "\n";
            // Optional: Delete orphaned Cloudinary image
        }
        echo "--------------------------------------------------\n";
    }
    echo count($seedNewProducts) . " Generic Products processed.\n";
    echo "--------------------------------------------------\n";


    // --- 5. Seed Orders ---
    echo "Seeding Orders...\n";
    $orders_data = [];
    // Ensure user_ids and product_ids are populated before creating orders
    if (isset($user_ids['kyaw@example.com']) && isset($user_ids['jane.doe@example.com'])) {
        $orders_data = [
            [
                'user_id' => $user_ids['kyaw@example.com'],
                'order_number' => 'ORD-G-' . date('Ymd') . '-001',
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
                'user_id' => $user_ids['jane.doe@example.com'],
                'order_number' => 'ORD-G-' . date('Ymd') . '-002',
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

    $order_ids = [];
    $stmtOrder = $pdo->prepare("
        INSERT INTO Orders (user_id, order_number, status, total_amount, shipping_customer_name, shipping_customer_email, shipping_phone_number, shipping_address_line1, shipping_city, shipping_state_province, shipping_postal_code, shipping_country)
        VALUES (:user_id, :order_number, :status, :total_amount, :shipping_customer_name, :shipping_customer_email, :shipping_phone_number, :shipping_address_line1, :shipping_city, :shipping_state_province, :shipping_postal_code, :shipping_country)
    ");

    foreach ($orders_data as $index => $order) {
        $stmtOrder->execute($order);
        $order_ids[$index] = $pdo->lastInsertId();
    }
    echo count($orders_data) . " Orders seeded (initially with 0 total).\n";
    echo "--------------------------------------------------\n";

    // --- 6. Seed OrderItems & Update Order Totals ---
    echo "Seeding OrderItems and updating Order totals...\n";
    $order_items_data = [];
    if (!empty($order_ids) && !empty($product_ids)) {
        // Order 1 items (Kyaw's order)
        if (isset($product_ids['Pro Developer Laptop']) && isset($product_ids['RGB Mechanical Keyboard'])) {
            $order_items_data[] = ['order_index' => 0, 'product_name' => 'Pro Developer Laptop', 'quantity' => 1, 'price_at_purchase' => 1499.99];
            $order_items_data[] = ['order_index' => 0, 'product_name' => 'RGB Mechanical Keyboard', 'quantity' => 1, 'price_at_purchase' => 129.99];
        }
        // Order 2 items (Jane's order)
        if (isset($product_ids['Bitcoin Crypto Canvas']) && isset($product_ids['Red Girl Abstract Portrait']) && isset($product_ids['Hot Air Balloon Adventure Print'])) {
            $order_items_data[] = ['order_index' => 1, 'product_name' => 'Bitcoin Crypto Canvas', 'quantity' => 1, 'price_at_purchase' => 39.99];
            $order_items_data[] = ['order_index' => 1, 'product_name' => 'Red Girl Abstract Portrait', 'quantity' => 1, 'price_at_purchase' => 49.99];
            $order_items_data[] = ['order_index' => 1, 'product_name' => 'Hot Air Balloon Adventure Print', 'quantity' => 2, 'price_at_purchase' => 29.99];
        }
    } else {
        echo "Warning: Order IDs or Product IDs arrays are empty. Cannot seed OrderItems effectively.\n";
    }


    $stmtOrderItem = $pdo->prepare("
        INSERT INTO OrderItems (order_id, product_id, quantity, price_at_purchase, product_name_at_purchase)
        VALUES (:order_id, :product_id, :quantity, :price_at_purchase, :product_name_at_purchase)
    ");
    $order_totals_update = [];

    foreach ($order_items_data as $item) {
        $order_id = $order_ids[$item['order_index']] ?? null;
        $product_id = $product_ids[$item['product_name']] ?? null;

        if ($order_id && $product_id) {
            $stmtOrderItem->execute([
                'order_id' => $order_id,
                'product_id' => $product_id,
                'quantity' => $item['quantity'],
                'price_at_purchase' => $item['price_at_purchase'],
                'product_name_at_purchase' => $item['product_name']
            ]);
            if (!isset($order_totals_update[$order_id])) {
                $order_totals_update[$order_id] = 0;
            }
            $order_totals_update[$order_id] += ($item['quantity'] * $item['price_at_purchase']);
        } else {
            echo "Skipping order item for '{$item['product_name']}' - Order ID or Product ID not found. (OrderIndex: {$item['order_index']}, OrderID: {$order_id}, ProdID: {$product_id})\n";
        }
    }

    if (!empty($order_totals_update)) {
        $stmtUpdateOrderTotal = $pdo->prepare("UPDATE Orders SET total_amount = :total_amount WHERE id = :order_id");
        foreach ($order_totals_update as $order_id_to_update => $new_total) {
            $stmtUpdateOrderTotal->execute(['total_amount' => $new_total, 'order_id' => $order_id_to_update]);
        }
        echo "Order totals updated.\n";
    }
    echo count($order_items_data) . " OrderItems seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 7. Seed Payments ---
    echo "Seeding Payments...\n";
    $payments_data = [];
    if (!empty($order_ids) && !empty($order_totals_update)) {
        if (isset($order_ids[0]) && isset($order_totals_update[$order_ids[0]])) {
            $payments_data[] = [
                'order_id' => $order_ids[0],
                'payment_method_type' => 'Stripe',
                'payment_provider_txn_id' => 'txn_g_' . uniqid(),
                'amount' => $order_totals_update[$order_ids[0]],
                'status' => 'Succeeded',
                'currency' => 'USD'
            ];
        }
        if (isset($order_ids[1]) && isset($order_totals_update[$order_ids[1]])) {
            $payments_data[] = [
                'order_id' => $order_ids[1],
                'payment_method_type' => 'PayPal',
                'payment_provider_txn_id' => 'pp_g_' . uniqid(),
                'amount' => $order_totals_update[$order_ids[1]],
                'status' => 'Succeeded',
                'currency' => 'USD'
            ];
        }
    }

    $stmtPayment = $pdo->prepare("
        INSERT INTO Payments (order_id, payment_method_type, payment_provider_txn_id, amount, status, currency)
        VALUES (:order_id, :payment_method_type, :payment_provider_txn_id, :amount, :status, :currency)
    ");
    foreach ($payments_data as $payment) {
        $stmtPayment->execute($payment);
    }
    echo count($payments_data) . " Payments seeded.\n";
    echo "--------------------------------------------------\n";

    echo "Database seeding finished successfully.\n";
} catch (PDOException $e) {
    echo "Database seeding failed (PDO): " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Database seeding failed (General Exception): " . $e->getMessage() . "\n";
    exit(1);
}
