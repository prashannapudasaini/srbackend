<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
// Added X-Admin-Token to allowed headers to support the new security check
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Admin-Token");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. INITIALIZE DATABASE FIRST
require_once '../../config/database.php';
$db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
if (!$db_conn && class_exists('Database')) {
    $database = new Database();
    $db_conn = $database->getConnection();
}
$pdo = $db_conn;

// 2. DYNAMIC TOKEN SECURITY CHECK (Previously missing in this file!)
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

if(isset($_FILES['image'])) {
    $target_dir = "../../uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $file_name;

    if(move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Return the public URL to React
        echo json_encode(["status" => "success", "url" => "/uploads/" . $file_name]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Upload failed"]);
    }
}
?>