<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "../../config/cors.php";
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    
    // ==========================================
    // EXCLUSIVE ADMIN OVERRIDE
    // ==========================================
    if ($data->email === 'adminsitaram@gmail.com' && $data->password === 'adminPASSWORD@') {
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => 999,
                "name" => "Super Admin",
                "email" => $data->email,
                "role" => "admin"
            ],
            "token" => "admin-secure-token-999"
        ]);
        exit; 
    }

    // ==========================================
    // STANDARD CUSTOMER LOGIN
    // ==========================================
    try {
        // Fetch using the new schema
        $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash FROM users WHERE email = ?");
        $stmt->execute([$data->email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Securely verify password
        if ($user && password_verify($data->password, $user['password_hash'])) {
            unset($user['password_hash']); // Don't send password hash to React
            
            echo json_encode([
                "success" => true,
                "user" => $user,
                "token" => "simulated-jwt-token-" . bin2hex(random_bytes(8)) 
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid email or password"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Email and password are required"]);
}
?>