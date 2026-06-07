<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }

require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

// Works for both App (which might send 'loginId' or 'email') and Web
$loginIdentifier = !empty($data->loginId) ? $data->loginId : (!empty($data->email) ? $data->email : null);

if ($loginIdentifier && !empty($data->password)) {
    try {
        // Fetch user by Email OR Phone
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, password_hash, can_create_admins, failed_attempts, locked_until FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$loginIdentifier, $loginIdentifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 1. Check if the account is currently locked
            if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                http_response_code(429);
                echo json_encode(["status" => "error", "message" => "Account is locked for 24 hours due to too many failed attempts."]);
                exit();
            }

            // 2. Verify password
            if (password_verify($data->password, $user['password_hash'])) {
                unset($user['password_hash']); 
                
                $user['can_create_admins'] = (int)$user['can_create_admins']; 
                $api_token = bin2hex(random_bytes(32)); 
                
                // Reset failed attempts and set token
                $updateStmt = $pdo->prepare("UPDATE users SET api_token = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                $updateStmt->execute([$api_token, $user['id']]);

                echo json_encode([
                    "status" => "success",
                    "message" => "Login successful",
                    "data" => ["user" => $user, "token" => $api_token]
                ]);
            } else {
                // 3. Handle Failed Attempt
                $attempts = (int)$user['failed_attempts'];
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) <= time()) {
                    $attempts = 0; // Reset if lock expired
                }
                
                $attempts += 1;
                $locked_until = null;
                $errorMessage = "Invalid credentials";

                if ($attempts >= 5) {
                    $locked_until = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $errorMessage = "Account locked for 24 hours due to too many failed attempts.";
                }

                $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                $updateStmt->execute([$attempts, $locked_until, $user['id']]);

                http_response_code(401);
                echo json_encode(["status" => "error", "message" => $errorMessage]);
            }
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Login ID and password are required"]);
}
?>