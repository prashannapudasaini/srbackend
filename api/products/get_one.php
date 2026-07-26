<?php
// ALLOW CORS (Crucial for Web Browser)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

// Get ID from URL parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id) {
    echo json_encode(["status" => "error", "message" => "No product ID provided"]);
    exit();
}

try {
    // 1. Fetch the single product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Product not found"]);
        exit();
    }

    // 2. Fetch variants for this product
    $varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
    $varStmt->execute([$id]);
    $variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format exactly like your index.php
    $formattedProduct = [
        "id"           => (int)$product['id'],
        "category"     => $product['category'],
        "name"         => $product['name'],
        "image"        => $product['base_image'],
        "badge"        => $product['badge'],
        "is_premium"   => (bool)$product['is_premium'],
        "is_essential" => (bool)$product['is_essential'],
        "description"  => $product['description'] ?? null,
        "nutrition"    => $product['nutrition'] ?? null,
        "features"     => $product['features'] ?? null,
        
        "variants"     => array_map(function($v) {
            return [
                "size"           => $v['size_flavor'],
                "price_npr"      => (float)$v['price_npr'],
                "stock_quantity" => (int)$v['stock_quantity'],
                "description"    => $v['variant_description'],
                "image"          => $v['variant_image'],
                "nutrition"      => $v['nutrition'] ?? null,
                "features"       => $v['features'] ?? null
            ];
        }, $variants)
    ];

    // Fallback: If variants exist, attach the default price/size to the root object 
    // so the React Native cart can easily read it.
    if (count($formattedProduct['variants']) > 0) {
        $formattedProduct['price'] = $formattedProduct['variants'][0]['price_npr'];
        $formattedProduct['size'] = $formattedProduct['variants'][0]['size'];
    } else {
        // Safe fallback if a product has no variants in the database yet
        $formattedProduct['price'] = 0;
        $formattedProduct['size'] = "Standard";
    }

    echo json_encode(["status" => "success", "data" => $formattedProduct]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>