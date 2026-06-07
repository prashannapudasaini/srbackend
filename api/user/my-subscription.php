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
    // Fetch subscriptions
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the delivery days for each subscription
    foreach ($subscriptions as &$sub) {
        $dayStmt = $pdo->prepare("SELECT DISTINCT day_of_week FROM subscription_items WHERE subscription_id = ?");
        $dayStmt->execute([$sub['id']]);
        $days = $dayStmt->fetchAll(PDO::FETCH_COLUMN);
        $sub['days'] = $days;
    }

    echo json_encode(["status" => "success", "data" => $subscriptions]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>