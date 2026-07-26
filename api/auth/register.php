<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

$name     = trim($data->name ?? '');
$email    = trim($data->email ?? '');
$phone    = trim($data->phone ?? '');
$password = $data->password ?? '';
$address  = trim($data->address ?? ''); 

$errors = [];

if (empty($name)) { $errors['name'] = "Full name is required"; } 
elseif (strlen($name) < 2) { $errors['name'] = "Name must be at least 2 characters"; }

if (empty($email)) { $errors['email'] = "Email address is required"; } 
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = "Please enter a valid email"; }

if (empty($phone)) { $errors['phone'] = "Phone number is required"; } 
elseif (!preg_match('/^[0-9]{10}$/', $phone)) { $errors['phone'] = "Phone number must be 10 digits"; }

if (empty($password)) { $errors['password'] = "Please create a password"; } 
elseif (strlen($password) < 6) { $errors['password'] = "Password must be at least 6 characters"; }

if (empty($address)) { 
    $errors['address'] = "Delivery address is required"; 
} elseif (strlen($address) < 4) { 
    $errors['address'] = "Please provide a more detailed delivery location"; 
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(["status" => "error", "message" => "Validation failed", "errors" => $errors]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Email already exists."]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Phone number already registered."]);
        exit();
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $api_token = bin2hex(random_bytes(32));
    $role = 'user';

    $sql = "INSERT INTO users (name, email, phone, address, password_hash, role, api_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $phone, $address, $password_hash, $role, $api_token]);

    echo json_encode(["status" => "success", "message" => "Account created successfully!"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>