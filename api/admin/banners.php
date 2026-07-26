<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// 🔥 THE FIX: X-Admin-Token must be included here!
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Instantly resolve preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200);
    exit; 
}

require_once '../../config/database.php';
$db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
if (!$db_conn && class_exists('Database')) {
    $database = new Database();
    $db_conn = $database->getConnection();
}
$pdo = $db_conn; 

$method = $_SERVER['REQUEST_METHOD'];

// --- DYNAMIC TOKEN SECURITY CHECK (ONLY FOR ADMIN ACTIONS) ---
// We skip this check for 'GET' so your public homepage can see the banners!
if ($method !== 'GET') {
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
        echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid Token."]);
        exit();
    }
}
// --- END SECURITY CHECK ---

try {
    if ($method === 'GET') {
        // Fetch all banners for the frontend
        $stmt = $pdo->query("SELECT * FROM banners ORDER BY created_at DESC");
        $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $banners]);

    } elseif ($method === 'POST' || $method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"));
        
        $title = trim($data->title ?? '');
        $subtitle = trim($data->subtitle ?? '');
        $description = trim($data->description ?? '');
        $image = trim($data->image ?? '');
        $id = $data->id ?? null;

        // --- WORD COUNT VALIDATIONS ---
        // Title: Max 3 words
        if (str_word_count($title) > 3) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Heading cannot exceed 3 words."]);
            exit;
        }
        // Subtitle: Max 25 words
        if (str_word_count($subtitle) > 25) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Sub-heading cannot exceed 25 words."]);
            exit;
        }
        // Description: Max 25 words
        if (str_word_count($description) > 25) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Description cannot exceed 25 words."]);
            exit;
        }

        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, description, image) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $subtitle, $description, $image]);
            echo json_encode(["status" => "success", "message" => "Banner created successfully."]);
        } else {
            $stmt = $pdo->prepare("UPDATE banners SET title=?, subtitle=?, description=?, image=? WHERE id=?");
            $stmt->execute([$title, $subtitle, $description, $image, $id]);
            echo json_encode(["status" => "success", "message" => "Banner updated successfully."]);
        }

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id)) {
            $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
            $stmt->execute([$data->id]);
            echo json_encode(["status" => "success", "message" => "Banner deleted."]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>