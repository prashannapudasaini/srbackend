<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

// 1. INITIALIZE DATABASE FIRST
require_once '../../../config/database.php';
$db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
if (!$db_conn && class_exists('Database')) {
    $database = new Database();
    $db_conn = $database->getConnection();
}
$pdo = $db_conn;

// 2. DYNAMIC TOKEN SECURITY CHECK
$adminToken = '';
if (isset($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
    $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $adminToken = isset($headers['X-Admin-Token']) ? $headers['X-Admin-Token'] : '';
}

if (empty($adminToken)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Token missing."]);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$adminToken]);

if (!$stmt->fetch()) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid or expired Admin Token."]);
    exit();
}
// --- END SECURITY CHECK ---

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'POST') {
        // Strictly strip hidden spaces from inputs
        $name = trim($data->name ?? '');
        $email = trim($data->email ?? '');
        $password = trim($data->password ?? '');
        $phone = trim($data->phone ?? '');
        $address = trim($data->address ?? ''); 
        $role = trim($data->role ?? 'delivery');

        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Email is already registered."]);
            exit;
        }

        // Hash and Insert
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $can_create = isset($data->can_create_admins) && $data->can_create_admins ? 1 : 0;
        $api_token = bin2hex(random_bytes(32)); 

        $query = "INSERT INTO users (name, email, password_hash, phone, address, role, can_create_admins, api_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name, $email, $hashed_password, $phone, $address, $role, $can_create, $api_token]);

        echo json_encode(["status" => "success", "message" => ucfirst($role) . " account created successfully."]);

    } elseif ($method === 'PUT') {
        if (empty($data->id) || empty($data->new_password)) {
            echo json_encode(["status" => "error", "message" => "User ID and new password required."]);
            exit;
        }

        $clean_password = trim($data->new_password);
        $hashed_password = password_hash($clean_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$hashed_password, $data->id]);

        echo json_encode(["status" => "success", "message" => "Password updated successfully."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>