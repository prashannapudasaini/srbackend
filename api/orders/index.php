<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../../config/database.php';

// Detect DB connection
if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

try {
    // Fetch all orders, join with users, and check if they have an active subscription
    $query = "
        SELECT 
            o.id, 
            o.created_at as date, 
            o.total_amount, 
            o.status as payment_status, 
            o.delivery_address, 
            o.phone_number,
            o.esewa_ref,
            u.name as registered_name,
            u.email,
            (SELECT COUNT(*) FROM subscriptions s WHERE s.user_id = o.user_id AND s.status = 'Active') as is_subscriber
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $db->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $orders
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to load orders: " . $e->getMessage()]);
}
?>