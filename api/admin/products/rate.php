<?php
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