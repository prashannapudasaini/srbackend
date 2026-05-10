<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

// Require your existing database file
require_once '../../config/database.php';

try {
    // Fetch all subscriptions with ALIASES that match React's expectations perfectly
    $query = "
        SELECT 
            s.sub_id AS id, 
            s.plan_type AS plan, 
            s.location, 
            s.delivery_time AS time, 
            s.weekly_total_cost, 
            s.status, 
            s.created_at,
            u.name AS customer, 
            u.email,
            'Paid' AS payment 
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
    ";
    
    // Use the procedural $pdo connection
    $stmt = $pdo->query($query);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $subscriptions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to load subscriptions: " . $e->getMessage()]);
}
?>