<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../config/database.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "User ID required"]);
    exit;
}

try {
    // Fetch user's standard orders
    $stmt = $pdo->prepare("SELECT id, created_at, total_amount, payment_status, order_status, payment_method FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch items for each order
    foreach ($orders as &$order) {
        $itemStmt = $pdo->prepare("
            SELECT oi.quantity, oi.unit_price as price, p.name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(["status" => "success", "data" => $orders]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>