<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token, Accept");

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
$pdo = $db_conn; // Categories relies on $pdo specifically

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

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'));

try {
    if ($method === 'POST') {
        // CREATE NEW CATEGORY
        if (!empty($data->name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$data->name]);
            echo json_encode(["status" => "success"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Category name required"]);
        }
    } 
    elseif ($method === 'PUT') {
        // UPDATE EXISTING CATEGORY
        if (!empty($data->id) && !empty($data->name)) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$data->name, $data->id]);
            echo json_encode(["status" => "success", "message" => "Category updated successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing category ID or Name"]);
        }
    }
    elseif ($method === 'DELETE') {
        // DELETE CATEGORY
        if (!empty($data->id)) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$data->id]);
            echo json_encode(["status" => "success"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Category ID required"]);
        }
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>