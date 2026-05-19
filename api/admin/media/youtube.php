<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
require_once '../../../config/database.php';

if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

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