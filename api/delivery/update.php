<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
require_once '../../config/database.php';

if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->task_id) && !empty($data->status)) {
    try {
        $prefix = substr($data->task_id, 0, 3);
        $real_id = substr($data->task_id, 4);

        if ($prefix === 'ORD') {
            // Update Order Table
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$data->status, $real_id]);
        } else if ($prefix === 'SUB') {
            // For subscriptions, you might log this in a daily dispatch table.
            // For now, we will just return success to clear it from the driver's screen.
        }

        echo json_encode(["status" => "success", "message" => "Status updated successfully."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update status."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid data."]);
}
?>