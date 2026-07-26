<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

// Ensure we have both a user_id and an action flag from React
if (!empty($data->user_id) && !empty($data->action)) {
    $userId = (int)$data->user_id;
    $action = $data->action;

    try {
        // ==========================================
        // ACTION 1: UPDATE PROFILE & ADDRESS
        // ==========================================
        if ($action === 'update_info') {
            $name = trim($data->name ?? '');
            $phone = trim($data->phone ?? '');
            $address = trim($data->address ?? '');

            // Check if the new phone belongs to someone else
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $stmt->execute([$phone, $userId]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Phone number is already registered to another account."]);
                exit();
            }

            // Update profile (Notice we DO NOT update email here, since it's read-only on the frontend)
            $sql = "UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$name, $phone, $address, $userId])) {
                // Return fresh data for React state
                $freshStmt = $pdo->prepare("SELECT id, name, email, phone, address, role FROM users WHERE id = ?");
                $freshStmt->execute([$userId]);
                $updatedUser = $freshStmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    "status" => "success", 
                    "message" => "Profile and delivery location updated successfully.",
                    "data" => $updatedUser
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Failed to update profile."]);
            }
        } 
        
        // ==========================================
        // ACTION 2: UPDATE PASSWORD
        // ==========================================
        elseif ($action === 'update_password') {
            $currentPassword = $data->current_password ?? '';
            $newPassword = $data->new_password ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Both current and new passwords are required."]);
                exit();
            }

            // Fetch the user's current password hash
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the old password is correct before allowing a change
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Incorrect current password."]);
                exit();
            }

            // Hash the new password and update
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($updateStmt->execute([$newHash, $userId])) {
                echo json_encode(["status" => "success", "message" => "Password updated successfully."]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Failed to update password."]);
            }
        } 
        
        // Catch unknown actions
        else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid action requested."]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID and Action type are required."]);
}
?>