<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id) && !empty($data->otp_code)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, can_create_admins, otp_code, otp_expires_at FROM users WHERE id = ?");
        $stmt->execute([$data->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if OTP matches and is not expired
            if ($user['otp_code'] === $data->otp_code && strtotime($user['otp_expires_at']) > time()) {
                
                $user['can_create_admins'] = (int)$user['can_create_admins'];
                $api_token = bin2hex(random_bytes(32)); 
                
                // Clear OTP and set final Token
                $updateStmt = $pdo->prepare("UPDATE users SET api_token = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
                $updateStmt->execute([$api_token, $user['id']]);
                
                unset($user['otp_code']);
                unset($user['otp_expires_at']);

                echo json_encode([
                    "status" => "success",
                    "message" => "Admin verified securely",
                    "data" => ["user" => $user, "token" => $api_token]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Invalid or expired security code."]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "User not found."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
}
?>