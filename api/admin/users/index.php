<?php
// ALLOW CORS & STRICTLY PREVENT CACHING
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }

// 1. INITIALIZE DATABASE FIRST
require_once '../../../config/database.php';
$db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
if (!$db_conn && class_exists('Database')) {
    $database = new Database();
    $db_conn = $database->getConnection();
}
$pdo = $db_conn;

// 2. DYNAMIC TOKEN SECURITY CHECK
$adminToken = '';
if (isset($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
    $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $adminToken = isset($headers['X-Admin-Token']) ? $headers['X-Admin-Token'] : '';
}

if (empty($adminToken)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Token missing."]);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$adminToken]);

if (!$stmt->fetch()) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid or expired Admin Token."]);
    exit();
}
// --- END SECURITY CHECK ---

try {
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