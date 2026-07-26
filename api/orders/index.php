<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");

// 🔥 THE FIX: Added X-Admin-Token and X-Requested-With to allow the React Admin Panel to connect
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Instantly resolve preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200);
    exit; 
}

// Correct relative path to config directory
require_once '../../config/database.php';

try {
    // 1. Initialize the Database Connection
    $database = new Database();
    $db = $database->connect();

    // 2. Fetch all orders 
    $query = "
        SELECT 
            id, 
            created_at, 
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
    
    $stmt = $db->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Loop through orders and fetch matching line items
    foreach ($orders as &$order) {
        
        $itemQuery = "
            SELECT oi.quantity, oi.unit_price as price, p.name, p.base_image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = :order_id
        ";
        
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->execute([':order_id' => $order['id']]);
        
        // Attach the items to this specific order
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Output the final JSON
    echo json_encode([
        "status" => "success",
        "data" => $orders
    ]);

} catch (PDOException $e) {
    // Catch Database-specific errors
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    // Catch general errors
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "System Error: " . $e->getMessage()]);
}
?>