<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
require_once '../../config/database.php';

if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

try {
    $tasks = [];

    // 1. Fetch Pending One-Time Orders
    $orderQuery = "SELECT o.id, o.total_amount, o.delivery_address, o.phone_number, o.status, u.name as customer_name 
                   FROM orders o LEFT JOIN users u ON o.user_id = u.id 
                   WHERE o.status IN ('Pending', 'On Way')";
    $orders = $db->query($orderQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as $o) {
        $tasks[] = [
            'task_id' => 'ORD-' . $o['id'],
            'real_id' => $o['id'],
            'type' => 'One-Time Order',
            'customer' => $o['customer_name'] ?: 'Guest',
            'address' => $o['delivery_address'],
            'phone' => $o['phone_number'],
            'amount' => 'NPR ' . number_format($o['total_amount']),
            'status' => $o['status']
        ];
    }

    // 2. Fetch Today's Active Subscriptions (Simplified for driver view)
    $subQuery = "SELECT s.sub_id, s.location, s.delivery_time, s.status, u.name as customer_name, u.phone 
                 FROM subscriptions s LEFT JOIN users u ON s.user_id = u.id 
                 WHERE s.status = 'Active'";
    $subs = $db->query($subQuery)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subs as $s) {
        $tasks[] = [
            'task_id' => 'SUB-' . $s['sub_id'],
            'real_id' => $s['sub_id'],
            'type' => 'Routine Subscription',
            'customer' => $s['customer_name'] ?: 'Guest',
            'address' => $s['location'],
            'phone' => $s['phone'] ?: 'N/A',
            'amount' => 'Pre-Paid',
            'status' => 'Pending Dispatch' // Daily default state
        ];
    }

    echo json_encode(["status" => "success", "data" => $tasks]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>