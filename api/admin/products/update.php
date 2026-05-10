<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    exit;
}

try {
    $pdo->beginTransaction();
    $productId = null;
    
    // Extract boolean values for checkboxes[cite: 1]
    $is_premium = !empty($data['is_premium']) ? 1 : 0;
    $is_essential = !empty($data['is_essential']) ? 1 : 0;

    // Convert arrays to JSON strings for database storage
    $description = $data['description'] ?? null;
    $nutrition = isset($data['nutrition']) ? json_encode($data['nutrition']) : null;
    $features = isset($data['features']) ? json_encode($data['features']) : null;

    if (isset($data['id']) && is_numeric($data['id'])) {
        // UPDATE existing product[cite: 1]
        $productId = $data['id'];
        $sql = "UPDATE products SET 
                name=?, category=?, base_image=?, badge=?, 
                is_premium=?, is_essential=?, description=?, 
                nutrition=?, features=? 
                WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'], $data['category'], $data['image'] ?? null, $data['badge'] ?? null, 
            $is_premium, $is_essential, $description, 
            $nutrition, $features, $productId
        ]);

        // Clear existing variants before re-inserting the updated list[cite: 1]
        $pdo->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$productId]);
    } else {
        // INSERT new product[cite: 1]
        $sql = "INSERT INTO products 
                (name, category, base_image, badge, is_premium, is_essential, description, nutrition, features) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'], $data['category'], $data['image'] ?? null, $data['badge'] ?? null, 
            $is_premium, $is_essential, $description, $nutrition, $features
        ]);
        $productId = $pdo->lastInsertId();
    }

    // Save Variants[cite: 1]
    if (isset($data['variants']) && is_array($data['variants'])) {
        $varSql = "INSERT INTO product_variants 
                   (product_id, size_flavor, price_npr, stock_quantity, variant_description, variant_image) 
                   VALUES (?, ?, ?, ?, ?, ?)";
        $varStmt = $pdo->prepare($varSql);
        foreach ($data['variants'] as $v) {
            $varStmt->execute([
                $productId, 
                $v['size'] ?? '', 
                $v['price_npr'] ?? 0, 
                $v['stock_quantity'] ?? 0, 
                $v['description'] ?? null, 
                $v['image'] ?? null
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Product and variants saved successfully", "id" => $productId]);

} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>