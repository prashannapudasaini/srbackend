<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

// FIX: Correct relative path to config directory
require_once '../../config/database.php';

try {
    // Fetch all orders using correct database schema columns
    $query = "
        SELECT 
            id, 
            created_at as date, 
            total_amount, 
            payment_status, 
            order_status,
            delivery_address, 
            phone_number,
            customer_name,
            payment_method
        FROM orders
        ORDER BY created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Loop through orders and fetch matching line items
    foreach ($orders as &$order) {
        $itemQuery = "
            SELECT oi.quantity, oi.unit_price as price, p.name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ";
        $itemStmt = $pdo->prepare($itemQuery);
        $itemStmt->execute([$order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "status" => "success",
        "data" => $orders
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to load orders: " . $e->getMessage()]);
}
?>