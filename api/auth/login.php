<?php
// 1. SET CORS HEADERS (Must be at the very top)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");
header("Content-Type: application/json; charset=UTF-8");

// 2. HANDLE PREFLIGHT (CORS OPTIONS REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. INCLUDE DATABASE
require_once "../../config/database.php";

// 4. BLOCK NON-POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Please use the React frontend to log in (POST request required)."]);
    exit();
}

// 5. SECURE JSON PARSING
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true); 

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty data received."]);
    exit();
}

// 6. SAFELY EXTRACT VARIABLES
$loginIdentifier = $data['email'] ?? $data['loginId'] ?? $data['phone'] ?? null;
$password = $data['password'] ?? null;

if ($loginIdentifier) {
    $loginIdentifier = trim($loginIdentifier);
}

if ($loginIdentifier && $password) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, address, subscription_count, loyalty_points, role, password_hash, can_create_admins, failed_attempts, locked_until FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$loginIdentifier, $loginIdentifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                http_response_code(429);
                echo json_encode(["status" => "error", "message" => "Account is locked. Please try again later."]);
                exit();
            }

            if (password_verify($password, $user['password_hash'])) {
                
                // --- ADMIN 2FA INTERCEPTION ---
                if ($user['role'] === 'admin') {
                    $otp = sprintf("%06d", mt_rand(1, 999999));
                    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $updateStmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                    $updateStmt->execute([$otp, $expiry, $user['id']]);
                    
                    echo json_encode([
                        "status" => "2fa_required",
                        "message" => "Security code sent to admin email.",
                        "data" => ["user_id" => $user['id']]
                    ]);
                    exit();
                }

                // --- STANDARD USER LOGIN ---
                unset($user['password_hash']); 
                $user['can_create_admins'] = (int)$user['can_create_admins']; 
                $api_token = bin2hex(random_bytes(32)); 
                
                $updateStmt = $pdo->prepare("UPDATE users SET api_token = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                $updateStmt->execute([$api_token, $user['id']]);

                echo json_encode([
                    "status" => "success",
                    "message" => "Login successful",
                    "data" => ["user" => $user, "token" => $api_token]
                ]);
            } else {
                $attempts = (int)$user['failed_attempts'];
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) <= time()) {
                    $attempts = 0; 
                }
                
                $attempts += 1;
                $locked_until = ($attempts >= 5) ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null;
                $errorMessage = ($attempts >= 5) ? "Account locked for 24 hours." : "Invalid credentials";

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
        error_log("Login Error: " . $e->getMessage()); 
        echo json_encode(["status" => "error", "message" => "Database error occurred."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Login ID and password are required"]);
}
?>