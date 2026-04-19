<?php
require_once "../../config/cors.php";
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->name) && !empty($data->email) && !empty($data->password)) {
    try {
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$data->email]);
        
        if ($check->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Email already registered"]);
            exit;
        }

        // Hash the password for security (NEW DB SCHEMA)
        $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);

        // Insert new user (Default role: customer)
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')");
        $stmt->execute([$data->name, $data->email, $hashed_password]);

        echo json_encode(["success" => true, "message" => "Account created successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Registration failed: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
}
?>