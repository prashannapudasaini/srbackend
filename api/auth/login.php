<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }

require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    try {
        // Fetch user including the permission flag
        $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash, can_create_admins FROM users WHERE email = ?");
        $stmt->execute([$data->email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password
        if ($user && password_verify($data->password, $user['password_hash'])) {
            unset($user['password_hash']); // Never send password hash to the frontend
            
            $user['can_create_admins'] = (int)$user['can_create_admins']; // Cast for frontend
            
            // Generate a secure token
            $api_token = bin2hex(random_bytes(32)); 
            
            // ==========================================
            // REQUIRED: Save this token to the database!
            // ==========================================
            // You need to add a column named 'api_token' to your users table
            $updateStmt = $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?");
            $updateStmt->execute([$api_token, $user['id']]);

            // Standardized Success Response
            echo json_encode([
                "status" => "success",
                "message" => "Login successful",
                "data" => [
                    "user" => $user,
                    "token" => $api_token // The Flutter app will save this and send it in the header
                ]
            ]);
        } else {
            http_response_code(401);
            // Standardized Error Response
            echo json_encode([
                "status" => "error",
                "message" => "Invalid email or password",
                "data" => null
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "Database error: " . $e->getMessage(),
            "data" => null
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Email and password are required",
        "data" => null
    ]);
}
?>