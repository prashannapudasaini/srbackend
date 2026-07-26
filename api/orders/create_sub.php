<?php
// 1. BULLETPROOF CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// 2. HANDLE AXIOS PREFLIGHT 'OPTIONS' REQUEST
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
$data = json_decode(file_get_contents('php://input'));

if (!$data || empty($data->user_id) || empty($data->schedule)) {
    echo json_encode(["status" => "error", "message" => "Missing required data."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Generate a clean ID for the user to see (e.g., 'SUB-8F3A2B')
    $sub_id_display = 'SUB-' . strtoupper(substr(uniqid(), -6));
    
    // Automatically apply Free Ghee logic if it's a daily plan
    $free_ghee = ($data->plan_type === 'daily') ? 1 : 0; 

    // Insert into subscriptions table
    $stmt = $pdo->prepare("INSERT INTO subscriptions (sub_id, user_id, location, plan_type, delivery_time, qualifies_free_ghee, weekly_total_cost, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 'Pending')");
    $stmt->execute([
        $sub_id_display, 
        $data->user_id, 
        $data->location, 
        $data->plan_type, 
        $data->delivery_time, 
        $free_ghee,
        $data->weekly_total_cost
    ]);
    
    // Capture the real integer ID from the database
    $new_sub_db_id = $pdo->lastInsertId();

    // Loop through the schedule and insert items
    $itemStmt = $pdo->prepare("INSERT INTO subscription_items (subscription_id, day_of_week, product_id, product_name, size, qty, price) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach($data->schedule as $dayObj) {
        foreach($dayObj->items as $item) {
            $itemStmt->execute([
                $new_sub_db_id, 
                $dayObj->day, 
                $item->id, 
                $item->name, 
                $item->size, 
                $item->qty, 
                $item->price
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "id" => $new_sub_db_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>