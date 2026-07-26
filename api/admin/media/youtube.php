<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
// Added X-Admin-Token to allowed headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

// 1. INITIALIZE DATABASE FIRST
require_once '../../../config/database.php';

$db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
if (!$db_conn && class_exists('Database')) {
    $database = new Database();
    $db_conn = $database->getConnection();
}
$db = $db_conn;

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

$stmt = $db->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$adminToken]);

if (!$stmt->fetch()) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid or expired Admin Token."]);
    exit();
}
// --- END SECURITY CHECK ---

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM media_youtube ORDER BY created_at DESC");
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $videos]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        $title = $data->title ?? 'Untitled Video';
        $youtube_id = $data->youtube_id ?? '';

        if (!empty($data->id)) {
            $stmt = $db->prepare("UPDATE media_youtube SET title = ?, youtube_id = ? WHERE id = ?");
            $stmt->execute([$title, $youtube_id, $data->id]);
        } else {
            $stmt = $db->prepare("INSERT INTO media_youtube (title, youtube_id) VALUES (?, ?)");
            $stmt->execute([$title, $youtube_id]);
        }
        echo json_encode(["status" => "success", "message" => "Video saved"]);

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id)) {
            $stmt = $db->prepare("DELETE FROM media_youtube WHERE id = ?");
            $stmt->execute([$data->id]);
            echo json_encode(["status" => "success", "message" => "Video deleted"]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>