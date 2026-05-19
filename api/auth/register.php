<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../config/database.php';

// Detect DB connection
if (isset($pdo)) { $db = $pdo; } 
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); } 
elseif (isset($conn)) { $db = $conn; }

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->name) && !empty($data->email) && !empty($data->password)) {
    try {
        // Check if email already exists
        $checkQuery = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute(['email' => $data->email]);
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "Email already registered."]);
            exit;
        }

        // Hash password and insert user (NOW WITH PHONE & ADDRESS)
        $hashed_password = password_hash($data->password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO users (name, email, password, phone, address) VALUES (:name, :email, :password, :phone, :address)";
        $stmt = $db->prepare($query);
        
        $stmt->execute([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $hashed_password,
            'phone' => $data->phone ?? null,
            'address' => $data->address ?? null
        ]);

        echo json_encode(["status" => "success", "message" => "Registration successful."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Registration failed: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete data."]);
}
?>