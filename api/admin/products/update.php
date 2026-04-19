<?php
require_once '../../../config/database.php';
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) exit(json_encode(["error" => "Invalid JSON"]));

try {
    $pdo->beginTransaction();
    $productId = null;
    
    // Extract boolean values for checkboxes
    $is_premium = !empty($data['is_premium']) ? 1 : 0;
    $is_essential = !empty($data['is_essential']) ? 1 : 0;

    if (isset($data['id']) && is_numeric($data['id'])) {
        $productId = $data['id'];
        $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, base_image=?, badge=?, is_premium=?, is_essential=? WHERE id=?");
        $stmt->execute([$data['name'], $data['category'], $data['image'] ?? null, $data['badge'] ?? null, $is_premium, $is_essential, $productId]);
        $pdo->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$productId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, category, base_image, badge, is_premium, is_essential) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['name'], $data['category'], $data['image'] ?? null, $data['badge'] ?? null, $is_premium, $is_essential]);
        $productId = $pdo->lastInsertId();
    }

    if (isset($data['variants']) && is_array($data['variants'])) {
        $varStmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_flavor, price_npr, stock_quantity, variant_description, variant_image) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['variants'] as $v) {
            $varStmt->execute([$productId, $v['size'] ?? '', $v['price_npr'] ?? 0, $v['stock_quantity'] ?? 0, $v['description'] ?? null, $v['image'] ?? null]);
        }
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Saved successfully", "id" => $productId]);
} catch(PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>