<?php
require_once '../../config/database.php';

try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll();

    $formattedProducts = [];
    foreach ($products as $product) {
        $varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
        $varStmt->execute([$product['id']]);
        $variants = $varStmt->fetchAll();

        $formattedProducts[] = [
            "id" => (int)$product['id'],
            "category" => $product['category'],
            "name" => $product['name'],
            "image" => $product['base_image'],
            "badge" => $product['badge'],
            "is_premium" => (bool)$product['is_premium'],
            "is_essential" => (bool)$product['is_essential'],
            "variants" => array_map(function($v) {
                return [
                    "size" => $v['size_flavor'],
                    "price_npr" => (float)$v['price_npr'],
                    "stock_quantity" => (int)$v['stock_quantity'],
                    "description" => $v['variant_description'],
                    "image" => $v['variant_image']
                ];
            }, $variants)
        ];
    }
    echo json_encode(["status" => "success", "data" => $formattedProducts]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>