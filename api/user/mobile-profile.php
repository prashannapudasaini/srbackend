<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }
require_once "../../config/database.php";

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "User ID required"]); exit();
}

try {
    // Fetch the REAL loyalty_points from the DB
    $stmt = $pdo->prepare("SELECT id, name, email, phone, address, subscription_count, loyalty_points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $namePrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $user['name']), 0, 4));
        $user['referral_code'] = "SITA" . $namePrefix . $user['id'];
        
        // Ensure it defaults to 0 if null
        $user['loyalty_points'] = (int)$user['loyalty_points'];

        echo json_encode(["status" => "success", "data" => $user]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>