
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';

use App\Core\Database;
use App\Service\CloudinaryImageUploader;
use Ramsey\Uuid\Uuid;

echo "Starting database seeding for ENHANCED schema with images...\n";

// Helper function to generate slugs
function slugify($text, string $divider = '-')
{
    $text = preg_replace('/[^\pL\pN\s' . preg_quote($divider) . '\']/u', '', $text);
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove', $text);
    $text = preg_replace('![^' . preg_quote($divider) . '\w\s]+!u', '', mb_strtolower($text));
    $text = preg_replace('![' . preg_quote($divider) . '\s]+!u', $divider, $text);
    return trim($text, $divider);
}

try {
    $pdo = Database::getInstance();
    $imageUploader = new CloudinaryImageUploader();

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
    $brands_data = [
        ['name' => 'Artful Prints', 'img_filename' => 'bird.jpg'],
        ['name' => 'Tech Universe', 'img_filename' => 'keyboard_rgb.jpg'],
        ['name' => 'Lifestyle Wares', 'img_filename' => 'coin.jpg'],
    ];
    $brand_ids_map = [];
    $stmtBrand = $pdo->prepare("INSERT INTO Brands (id, name, is_active, brand_cloudinary_public_id) VALUES (:id, :name, :is_active, :public_id)");
    foreach ($brands_data as $brandData) {
        $brandCloudinaryPublicId = null;
        $localImagePath = __DIR__ . '/seed_data/images/' . $brandData['img_filename'];

        if (file_exists($localImagePath)) {
            try {
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'brand');
                $brandCloudinaryPublicId = $uploadResult['public_id'];
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for brand '{$brandData['name']}': " . $e->getMessage() . "\n";
            }
        }

        $brandId = Uuid::uuid4()->toString();
        $stmtBrand->execute([
            ':id' => $brandId,
            ':name' => $brandData['name'],
            ':is_active' => true,
            ':public_id' => $brandCloudinaryPublicId
        ]);
        $brand_ids_map[$brandData['name']] = $brandId;
    }
    echo count($brands_data) . " Brands seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 3. Seed Categories ---
    echo "Seeding Categories...\n";
    $categories_data = [
        ['name' => 'Wall Art & Decor', 'img_filename' => 'mountain.jpg'],
        ['name' => 'Electronics', 'img_filename' => 'laptop_pro.jpg'],
        ['name' => 'Lifestyle Items', 'img_filename' => 'dollor.jpg'],
    ];
    $category_ids_map = [];
    $stmtCategory = $pdo->prepare("INSERT INTO Categories (id, name, is_active, category_cloudinary_public_id) VALUES (:id, :name, :is_active, :public_id)");
    foreach ($categories_data as $categoryData) {
        $categoryCloudinaryPublicId = null;
        $localImagePath = __DIR__ . '/seed_data/images/' . $categoryData['img_filename'];

        if (file_exists($localImagePath)) {
            try {
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'category');
                $categoryCloudinaryPublicId = $uploadResult['public_id'];
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for category '{$categoryData['name']}': " . $e->getMessage() . "\n";
            }
        }

        $categoryId = Uuid::uuid4()->toString();
        $stmtCategory->execute([
            ':id' => $categoryId,
            ':name' => $categoryData['name'],
            ':is_active' => true,
            ':public_id' => $categoryCloudinaryPublicId
        ]);
        $category_ids_map[$categoryData['name']] = $categoryId;
    }
    echo count($categories_data) . " Categories seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 4. Seed Products ---
    echo "Seeding Products...\n";
    $productRepository = new App\Repository\Product\ProductRepository();
    $seedProducts_data = [
        ['name' => 'Pro Developer Laptop', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 1499.99, 'stock' => 15, 'size_ml' => 0, 'img' => 'laptop_pro.jpg', 'desc' => 'High-performance laptop for professionals.'],
        ['name' => 'Serene Mountain Print', 'brand_name' => 'Artful Prints', 'category_name' => 'Wall Art & Decor', 'price' => 27.50, 'stock' => 33, 'size_ml' => 0, 'img' => 'mountain.jpg', 'desc' => 'Breathtaking print of a serene mountain landscape.'],
        ['name' => 'Ergonomic Pro Mouse', 'brand_name' => 'Tech Universe', 'category_name' => 'Electronics', 'price' => 79.50, 'stock' => 40, 'size_ml' => 0, 'img' => 'mouse_pro.webp', 'desc' => 'Wireless ergonomic mouse for maximum comfort.'],
        ['name' => 'Golden Coin Replica', 'brand_name' => 'Lifestyle Wares', 'category_name' => 'Lifestyle Items', 'price' => 15.50, 'stock' => 70, 'size_ml' => 0, 'img' => 'coin.jpg', 'desc' => 'A shiny replica of an ancient golden coin.'],
    ];
    $product_ids_map = [];
    foreach ($seedProducts_data as $productData) {
        $productCloudinaryPublicId = null;
        $localImagePath = __DIR__ . '/seed_data/images/' . $productData['img'];

        if (file_exists($localImagePath)) {
            try {
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'products');
                $productCloudinaryPublicId = $uploadResult['public_id'];
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for product '{$productData['name']}': " . $e->getMessage() . "\n";
            }
        }

        $brandId = $brand_ids_map[$productData['brand_name']] ?? null;
        $categoryId = $category_ids_map[$productData['category_name']] ?? null;
        if (!$brandId || !$categoryId) continue;

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
            'is_active' => true,
        ];
        $newProductId = $productRepository->create($repoData);
        $product_ids_map[$productData['name']] = $newProductId;
    }
    echo count($product_ids_map) . " Products seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 5. Seed Reviews ---
    echo "Seeding Reviews...\n";
    $reviews_data = [
        ['user_email' => 'kyaw@example.com', 'product_name' => 'Pro Developer Laptop', 'rating' => 5, 'text' => 'Absolutely fantastic machine. Worth every penny!'],
        ['user_email' => 'jane.doe@example.com', 'product_name' => 'Pro Developer Laptop', 'rating' => 4, 'text' => 'Very powerful, but the battery could be better.'],
        ['user_email' => 'kyaw@example.com', 'product_name' => 'Ergonomic Pro Mouse', 'rating' => 5, 'text' => 'So comfortable to use for long coding sessions.'],
    ];
    $stmtReview = $pdo->prepare("INSERT INTO Reviews (id, user_id, product_id, rating, review_text) VALUES (:id, :user_id, :product_id, :rating, :text)");
    foreach ($reviews_data as $reviewData) {
        $userId = $user_ids_map[$reviewData['user_email']] ?? null;
        $productId = $product_ids_map[$reviewData['product_name']] ?? null;
        if (!$userId || !$productId) continue;
        $stmtReview->execute([
            ':id' => Uuid::uuid4()->toString(),
            ':user_id' => $userId,
            ':product_id' => $productId,
            ':rating' => $reviewData['rating'],
            ':text' => $reviewData['text']
        ]);
    }
    echo count($reviews_data) . " Reviews seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 6. Update Product Rating Summaries ---
    echo "Updating product review summaries...\n";
    $stmtUpdateRatings = $pdo->prepare("
        UPDATE Products p
        SET 
            p.review_count = (SELECT COUNT(*) FROM Reviews r WHERE r.product_id = p.id),
            p.average_rating = (SELECT AVG(r.rating) FROM Reviews r WHERE r.product_id = p.id)
        WHERE p.id IN (SELECT DISTINCT product_id FROM Reviews)
    ");
    $stmtUpdateRatings->execute();
    echo "Product summaries recalculated.\n";
    echo "--------------------------------------------------\n";

    // ... (The rest of the seeding for Discounts and Wishlists remains the same) ...
    // --- 7. Seed Discounts & ProductDiscounts ---
    echo "Seeding Discounts...\n";
    // ...
    // --- 8. Seed Wishlists ---
    echo "Seeding Wishlists...\n";
    // ...

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
