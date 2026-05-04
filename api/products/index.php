<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    // Fetch products ordered by most recent[cite: 2]
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedProducts = [];
    foreach ($products as $product) {
        // Fetch variants for each product[cite: 2]
        $varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
        $varStmt->execute([$product['id']]);
        $variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedProducts[] = [
            "id"           => (int)$product['id'],
            "category"     => $product['category'],
            "name"         => $product['name'],
            "image"        => $product['base_image'],
            "badge"        => $product['badge'],
            "is_premium"   => (bool)$product['is_premium'],
            "is_essential" => (bool)$product['is_essential'],
            
            // New dynamic columns retrieved as strings[cite: 2]
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
                    
                    // Added for future flexibility if variants have unique data[cite: 2]
                    "nutrition"      => $v['nutrition'] ?? null,
                    "features"       => $v['features'] ?? null
                ];
            }, $variants)
        ];
    }
    
    echo json_encode(["status" => "success", "data" => $formattedProducts]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>