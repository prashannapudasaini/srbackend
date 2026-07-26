<?php
// Load centralized CORS and Database configs
require_once '../../config/cors.php';
require_once '../../config/database.php';

// Get JSON payload
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing user ID or subscription ID"]);
    exit;
}

try {
    // Update the subscription status to 'Paused'
    $stmt = $pdo->prepare("UPDATE subscriptions SET status = 'Paused' WHERE id = ? AND user_id = ?");
    $stmt->execute([$data->id, $data->user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["status" => "success", "message" => "Subscription paused successfully."]);
    } else {
        // If rowCount is 0, it means the ID didn't match the user, or it was already paused
        echo json_encode(["status" => "error", "message" => "Subscription not found or already paused."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>