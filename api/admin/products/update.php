<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Admin-Token");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. INITIALIZE DATABASE FIRST
require_once '../../../config/database.php';
$db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
if (!$db_conn && class_exists('Database')) {
    $database = new Database();
    $db_conn = $database->getConnection();
}
$pdo = $db_conn;

// 2. DYNAMIC TOKEN SECURITY CHECK
$adminToken = '';
if (isset($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
    $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $adminToken = isset($headers['X-Admin-Token']) ? $headers['X-Admin-Token'] : '';
}

if (empty($adminToken)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Token missing."]);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$adminToken]);

if (!$stmt->fetch()) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid or expired Admin Token."]);
    exit();
}
// --- END SECURITY CHECK ---

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    exit;
}

try {
    $pdo->beginTransaction();
    $productId = null;
    
    // Extract boolean values for checkboxes
    $is_premium = !empty($data['is_premium']) ? 1 : 0;
    $is_essential = !empty($data['is_essential']) ? 1 : 0;

    // Convert arrays to JSON strings for database storage
    $description = $data['description'] ?? null;
    $nutrition = isset($data['nutrition']) ? json_encode($data['nutrition']) : null;
    $features = isset($data['features']) ? json_encode($data['features']) : null;

    if (isset($data['id']) && is_numeric($data['id'])) {
        // UPDATE existing product
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

        // Clear existing variants before re-inserting the updated list
        $pdo->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$productId]);
    } else {
        // INSERT new product
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

    // Save Variants
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