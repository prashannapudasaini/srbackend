<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
// Added X-Admin-Token to allowed headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- SECURITY CHECK ---
// You can change 'sitaram_secret_2026' to a highly secure password later
$headers = apache_request_headers();
$adminToken = isset($headers['X-Admin-Token']) ? $headers['X-Admin-Token'] : '';

if ($adminToken !== 'sitaram_secret_2026') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid Admin Token."]);
    exit();
}
// ----------------------

require_once '../../config/database.php';
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'));

try {
    if ($method === 'POST') {
        if (!empty($data->name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$data->name]);
            echo json_encode(["status" => "success"]);
        }
    } 
    elseif ($method === 'DELETE') {
        if (!empty($data->id)) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$data->id]);
            echo json_encode(["status" => "success"]);
        }
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>