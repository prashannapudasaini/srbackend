<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../../config/database.php';
$data = json_decode(file_get_contents('php://input'));
if(isset($data->id)) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$data->id]);
    echo json_encode(["status" => "success"]);
}
?>