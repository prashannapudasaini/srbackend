<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
require_once '../../../config/database.php';

if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'POST') {
        // CREATE NEW STAFF ACCOUNT
        if (empty($data->name) || empty($data->email) || empty($data->password) || empty($data->role)) {
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit;
        }

        // Check if email exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$data->email]);
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "Email is already registered."]);
            exit;
        }

        $hashed_password = password_hash($data->password, PASSWORD_BCRYPT);
        $can_create = isset($data->can_create_admins) && $data->can_create_admins ? 1 : 0;

        $query = "INSERT INTO users (name, email, password, phone, address, role, can_create_admins) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $data->name, $data->email, $hashed_password, 
            $data->phone ?? null, $data->address ?? null, 
            $data->role, $can_create
        ]);

        echo json_encode(["status" => "success", "message" => ucfirst($data->role) . " account created successfully."]);

    } elseif ($method === 'PUT') {
        // CHANGE PASSWORD
        if (empty($data->id) || empty($data->new_password)) {
            echo json_encode(["status" => "error", "message" => "User ID and new password required."]);
            exit;
        }

        $hashed_password = password_hash($data->new_password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $data->id]);

        echo json_encode(["status" => "success", "message" => "Password updated successfully."]);

    } elseif ($method === 'DELETE') {
        // DELETE ACCOUNT
        if (empty($data->id)) {
            echo json_encode(["status" => "error", "message" => "User ID required."]);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$data->id]);

        // Also clean up subscriptions tied to this user to prevent orphaned data
        $stmt2 = $db->prepare("DELETE FROM subscriptions WHERE user_id = ?");
        $stmt2->execute([$data->id]);

        echo json_encode(["status" => "success", "message" => "Account permanently deleted."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>