<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';

use App\Core\Database;
use App\Service\CloudinaryImageUploader;
use Ramsey\Uuid\Uuid;

echo "Starting database seeding with new perfume data...\n";

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
    // Updated to include the new 'is_active' column
    $stmtUser = $pdo->prepare("INSERT INTO Users (id, name, email, password, role, is_active) VALUES (:id, :name, :email, :password, :role, :is_active)");
    foreach ($users_data as $userData) {
        $userId = Uuid::uuid4()->toString();
        $hashedPassword = password_hash($userData['password_plain'], PASSWORD_ARGON2ID);
        $stmtUser->execute([
            ':id' => $userId,
            ':name' => $userData['name'],
            ':email' => $userData['email'],
            ':password' => $hashedPassword,
            ':role' => $userData['role'],
            ':is_active' => true
        ]);
        $user_ids_map[$userData['email']] = $userId;
    }
    echo count($users_data) . " Users seeded.\n";
    echo "--------------------------------------------------\n";

    echo "Seeding Brands...\n";
    $brands_data = [
        ['name' => 'Dior', 'img_filename' => 'dior_brand.webp'],
        ['name' => 'Versace', 'img_filename' => 'versace_brand.jpeg'],
    ];
    $brand_ids_map = [];
    $stmtBrand = $pdo->prepare("INSERT INTO Brands (id, name, is_active, brand_cloudinary_public_id) VALUES (:id, :name, :is_active, :public_id)");
    foreach ($brands_data as $brandData) {
        $brandCloudinaryPublicId = null;
        // Corrected path to brand_images
        $localImagePath = __DIR__ . '/seed_data/brand_images/' . $brandData['img_filename'];

        if (file_exists($localImagePath)) {
            try {
                echo "Uploading brand image for '{$brandData['name']}'...\n";
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'brands');
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

    echo "Seeding Categories...\n";
    $categories_data = [
        ['name' => 'For Men', 'img_filename' => 'dris_dior.webp'],
        ['name' => 'For Women', 'img_filename' => 'miss_drior.webp'],
        ['name' => 'Sweet Scents', 'img_filename' => 'sweet.avif'],
        ['name' => 'Unisex', 'img_filename' => 'oud_ispahan.webp'],
    ];
    $category_ids_map = [];
    $stmtCategory = $pdo->prepare("INSERT INTO Categories (id, name, is_active, category_cloudinary_public_id) VALUES (:id, :name, :is_active, :public_id)");
    foreach ($categories_data as $categoryData) {
        $categoryCloudinaryPublicId = null;
        // Corrected path to category_images
        $localImagePath = __DIR__ . '/seed_data/category_images/' . $categoryData['img_filename'];

        if (file_exists($localImagePath)) {
            try {
                echo "Uploading category image for '{$categoryData['name']}'...\n";
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'categories');
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
    echo "Seeding Products with real perfume data...\n";
    $productRepository = new App\Repository\Product\ProductRepository();
    $seedProducts_data = [
        [
            'name' => 'Dior Homme Sport',
            'brand_name' => 'Dior',
            'category_name' => 'For Men',
            'price' => 115.00,
            'stock' => 50,
            'img' => 'drior_homme_sport.webp',
            'desc' => 'A new, explosive and elegant Eau de Toilette. A decidedly fresh and woody fragrance that is both vibrant and sensual.',
            'top_notes' => 'Lemon, Bergamot, Aldehydes',
            'middle_notes' => 'Pink Pepper, Elemi',
            'base_notes' => 'Woody Notes, Olibanum, Amber',
            'size_ml' => 125,
            'gender' => 'Men'
        ],
        [
            'name' => 'Sauvage',
            'brand_name' => 'Dior',
            'category_name' => 'Eau de Parfum',
            'price' => 120.00,
            'stock' => 45,
            'img' => 'sauvage_perfume.jpg',
            'desc' => 'A radical, fresh composition, dictated by a name that has the ring of a manifesto. Raw and noble all at once.',
            'top_notes' => 'Calabrian Bergamot, Pepper',
            'middle_notes' => 'Sichuan Pepper, Lavender, Pink Pepper',
            'base_notes' => 'Ambroxan, Cedar, Labdanum',
            'size_ml' => 100,
            'gender' => 'Men'
        ],
        [
            'name' => 'J\'adore Eau de Parfum',
            'brand_name' => 'Dior',
            'category_name' => 'For Women',
            'price' => 145.00,
            'stock' => 60,
            'img' => 'jadore_eau_de_perfume.webp',
            'desc' => 'The grand floral fragrance for women with a generous and well-balanced bouquet, and its richness is a source of inspiration.',
            'top_notes' => 'Ylang-Ylang',
            'middle_notes' => 'Damascus Rose',
            'base_notes' => 'Jasmine',
            'size_ml' => 100,
            'gender' => 'Women'
        ],
        [
            'name' => 'Miss Dior Blooming Bouquet',
            'brand_name' => 'Dior',
            'category_name' => 'For Women',
            'price' => 98.00,
            'stock' => 70,
            'img' => 'miss_dior_blooming_bonquet.jpg',
            'desc' => 'A fresh and sparkling floral composition fashioned like a dress embroidered with a thousand flowers.',
            'top_notes' => 'Sicilian Mandarin',
            'middle_notes' => 'Pink Peony, Damascus Rose, Apricot',
            'base_notes' => 'White Musk',
            'size_ml' => 100,
            'gender' => 'Women'
        ],
        [
            'name' => 'Versace Eros Flame',
            'brand_name' => 'Versace',
            'category_name' => 'Eau de Parfum',
            'price' => 102.00,
            'stock' => 55,
            'img' => 'versace_flame.webp',
            'desc' => 'A fragrance for a strong, passionate, self-confident man who is deeply in touch with his emotions.',
            'top_notes' => 'Mandarin Orange, Black Pepper, Chinotto',
            'middle_notes' => 'Rosemary, Pepper, Geranium',
            'base_notes' => 'Tonka Bean, Vanilla, Sandalwood',
            'size_ml' => 100,
            'gender' => 'Men'
        ],
        [
            'name' => 'Versace Pour Homme',
            'brand_name' => 'Versace',
            'category_name' => 'For Men',
            'price' => 90.00,
            'stock' => 80,
            'img' => 'versace.jpg',
            'desc' => 'An aromatic fougère fragrance with an aquatic and Mediterranean character, embodying the modern man.',
            'top_notes' => 'Lemon, Bergamot, Neroli',
            'middle_notes' => 'Hyacinth, Cedar, Clary Sage',
            'base_notes' => 'Tonka Bean, Musk, Amber',
            'size_ml' => 100,
            'gender' => 'Men'
        ]
    ];
    $product_ids_map = [];
    foreach ($seedProducts_data as $productData) {
        $productCloudinaryPublicId = null;
        $localImagePath = __DIR__ . '/seed_data/product_images/' . $productData['img'];

        if (file_exists($localImagePath)) {
            try {
                echo "Uploading product image for '{$productData['name']}'...\n";
                $uploadResult = $imageUploader->uploadImage($localImagePath, 'products');
                $productCloudinaryPublicId = $uploadResult['public_id'];
            } catch (Exception $e) {
                echo "Cloudinary upload FAILED for product '{$productData['name']}': " . $e->getMessage() . "\n";
            }
        } else {
            echo "Image file not found for '{$productData['name']}' at path: {$localImagePath}\n";
        }

        $brandId = $brand_ids_map[$productData['brand_name']] ?? null;
        $categoryId = $category_ids_map[$productData['category_name']] ?? null;
        if (!$brandId || !$categoryId) {
            echo "SKIPPING product '{$productData['name']}' due to missing Brand/Category.\n";
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
            'is_active' => true,
            'top_notes' => $productData['top_notes'],
            'middle_notes' => $productData['middle_notes'],
            'base_notes' => $productData['base_notes'],
            'gender_affinity' => $productData['gender']
        ];
        $newProductId = $productRepository->create($repoData);
        $product_ids_map[$productData['name']] = $newProductId;
    }
    echo count($product_ids_map) . " Products seeded.\n";
    echo "--------------------------------------------------\n";

    // --- 5. Seed Reviews ---
    echo "Seeding Reviews...\n";
    $reviews_data = [
        ['user_email' => 'kyaw@example.com', 'product_name' => 'Sauvage', 'rating' => 5, 'text' => 'A timeless classic. Always get compliments on this one.'],
        ['user_email' => 'jane.doe@example.com', 'product_name' => 'Sauvage', 'rating' => 4, 'text' => 'Very strong and long-lasting, a bit intense for daily wear for me.'],
        ['user_email' => 'kyaw@example.com', 'product_name' => 'J\'adore Eau de Parfum', 'rating' => 5, 'text' => 'Bought this for my partner, and it smells absolutely divine.'],
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

    // --- 7. Seed Discounts & Wishlists ---
    // (This part can be added back in if you have specific discounts/wishlists to seed)

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
