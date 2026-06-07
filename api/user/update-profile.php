<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../config/database.php';

$data = json_decode(file_get_contents('php://input'));

if (!$data || !isset($data->user_id) || !isset($data->action)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

try {
    if ($data->action === 'update_info') {
        // Update Name and Phone
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$data->name, $data->phone, $data->user_id])) {
            echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update profile"]);
        }
    } 
    elseif ($data->action === 'update_password') {
        // Verify current password and update to new password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$data->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($data->current_password, $user['password_hash'])) {
            echo json_encode(["status" => "error", "message" => "Incorrect current password"]);
            exit;
        }

        $new_hash = password_hash($data->new_password, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($updateStmt->execute([$new_hash, $data->user_id])) {
            echo json_encode(["status" => "success", "message" => "Password changed successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to change password"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>