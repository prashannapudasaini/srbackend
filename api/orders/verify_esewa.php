<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (empty($data->transaction_uuid) || empty($data->transaction_code)) {
    echo json_encode(["status" => "error", "message" => "Missing transaction details."]); exit();
}

$uuid = $data->transaction_uuid;
$esewa_ref_id = $data->transaction_code;

// Split the uniqid() we appended earlier (e.g., "SUB_15-64a8b" -> "SUB_15")
$uuid_parts = explode('-', $uuid); 
$real_id_string = $uuid_parts[0]; 

// ... inside your verify_esewa.php file ...

    if (strpos($real_id_string, 'SUB_') === 0) {
        // --- SUBSCRIPTION VERIFICATION FLOW ---
        $sub_db_id = (int) str_replace('SUB_', '', $real_id_string);
        // ADD user_id and weekly_total_cost to the SELECT
        $stmt = $pdo->prepare("SELECT id, user_id, weekly_total_cost as total_amount, payment_status FROM subscriptions WHERE id = ?");
        $stmt->execute([$sub_db_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ... (Keep your existing check for $order and 'Paid' status) ...
        
        $updateStmt = $pdo->prepare("UPDATE subscriptions SET payment_status = 'Paid' WHERE id = ?");
        if ($updateStmt->execute([$sub_db_id])) {
            // 🔥 NEW: Add 2% Loyalty Points
            $pointsEarned = round((float)$order['total_amount'] * 0.02);
            $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$pointsEarned, $order['user_id']]);

            echo json_encode(["status" => "success", "message" => "Subscription activated."]);
        }
        
    } else {
        // --- STANDARD ORDER VERIFICATION FLOW ---
        $order_id = (int) $real_id_string;
        // ADD user_id and total_amount to the SELECT
        $stmt = $pdo->prepare("SELECT id, user_id, total_amount, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ... (Keep your existing check for $order and 'completed' status) ...
        
        $updateStmt = $pdo->prepare("UPDATE orders SET payment_status = 'completed', esewa_ref = ? WHERE id = ?");
        if ($updateStmt->execute([$esewa_ref_id, $order_id])) {
            // 🔥 NEW: Add 2% Loyalty Points (Only if user is registered/logged in)
            if (!empty($order['user_id'])) {
                $pointsEarned = round((float)$order['total_amount'] * 0.02);
                $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$pointsEarned, $order['user_id']]);
            }

            echo json_encode(["status" => "success", "message" => "Payment verified."]);
        }
    }

try {
    if (strpos($real_id_string, 'SUB_') === 0) {
        
        // --- SUBSCRIPTION VERIFICATION FLOW ---
        $sub_db_id = (int) str_replace('SUB_', '', $real_id_string);
        
        $stmt = $pdo->prepare("SELECT id, payment_status FROM subscriptions WHERE id = ?");
        $stmt->execute([$sub_db_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) { echo json_encode(["status" => "error", "message" => "Subscription not found."]); exit(); }
        if (strtolower($order['payment_status']) === 'paid' || strtolower($order['payment_status']) === 'completed') {
            echo json_encode(["status" => "success", "message" => "Payment already verified."]); exit();
        }
        
        $updateStmt = $pdo->prepare("UPDATE subscriptions SET payment_status = 'Paid' WHERE id = ?");
        if ($updateStmt->execute([$sub_db_id])) {
            echo json_encode(["status" => "success", "message" => "Subscription activated."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update subscription."]);
        }
        
    } else {
        
        // --- STANDARD ORDER VERIFICATION FLOW ---
        $order_id = (int) $real_id_string;
        
        $stmt = $pdo->prepare("SELECT id, payment_status FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) { echo json_encode(["status" => "error", "message" => "Order not found."]); exit(); }
        if ($order['payment_status'] === 'completed') {
            echo json_encode(["status" => "success", "message" => "Payment already verified."]); exit();
        }
        
        $updateStmt = $pdo->prepare("UPDATE orders SET payment_status = 'completed', esewa_ref = ? WHERE id = ?");
        if ($updateStmt->execute([$esewa_ref_id, $order_id])) {
            echo json_encode(["status" => "success", "message" => "Payment verified and order updated."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update order status."]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>