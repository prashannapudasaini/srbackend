<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Instantly resolve preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200);
    exit; 
}

require_once '../../config/database.php';
$data = json_decode(file_get_contents("php://input"));

if(isset($data->order_id) && isset($data->status)) {
    try {
        $database = new Database();
        $pdo = $database->connect();
        $pdo->beginTransaction();

        // 1. Update the Order Status
        $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt->execute([$data->status, $data->order_id]);

        // 2. Fetch the User ID linked to this order
        $userStmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $userStmt->execute([$data->order_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        // 3. Send Notification to the User (if a user is linked to this order)
        if($user && $user['user_id']) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $title = "Order Update: " . ucfirst($data->status);
            
            $messages = [
                'processing' => "Your standard order #" . $data->order_id . " is currently being processed by our team.",
                'dispatched' => "Your standard order #" . $data->order_id . " has been dispatched from our facility.",
                'out for delivery' => "Great news! Your order #" . $data->order_id . " is out for delivery today.",
                'delivered' => "Your order #" . $data->order_id . " has been successfully delivered. Enjoy!",
                'cancelled' => "Your order #" . $data->order_id . " has been cancelled."
            ];
            
            $msg = isset($messages[strtolower($data->status)]) ? $messages[strtolower($data->status)] : "Your order #" . $data->order_id . " status changed to: " . $data->status;

            $notifStmt->execute([$user['user_id'], $title, $msg]);
        }

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Order updated and user notified successfully."]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing order_id or status data."]);
}
?>