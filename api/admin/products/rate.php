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

require_once "../../../config/cors.php";
require_once "../../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->product_id) && !empty($data->rating)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO ratings (product_id, rating, customer_id) VALUES (?, ?, ?)");
        $stmt->execute([$data->product_id, $data->rating, $data->customer_id ?? null]);
        
        echo json_encode(["success" => true, "message" => "Rating submitted"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}
?>