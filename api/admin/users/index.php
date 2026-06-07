<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// IMPORT DATABASE CONNECTION
require_once '../../../config/database.php';

try {
    // Left join to see if a user has an 'Active' subscription AND get total orders
    $query = "
        SELECT u.*, 
        (SELECT COUNT(*) FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'Active') as is_subscriber,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as total_orders
        FROM users u 
        ORDER BY u.created_at DESC
    ";
    $stmt = $pdo->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $users]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>