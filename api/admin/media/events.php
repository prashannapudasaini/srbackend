<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }
require_once '../../../config/database.php';

// Safe DB Connection
if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM media_events ORDER BY created_at DESC");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse the JSON images array back into a standard PHP array for React
        foreach ($events as &$event) {
            $event['images'] = json_decode($event['images']) ?: [];
        }
        echo json_encode(["status" => "success", "data" => $events]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        $title = $data->title ?? 'Untitled Event';
        $cover = $data->cover ?? '';
        $images = json_encode($data->images ?? []); // Safely store as JSON string

        if (!empty($data->id)) {
            // Update existing event
            $stmt = $db->prepare("UPDATE media_events SET title = ?, cover = ?, images = ? WHERE id = ?");
            $stmt->execute([$title, $cover, $images, $data->id]);
        } else {
            // Create new event
            $stmt = $db->prepare("INSERT INTO media_events (title, cover, images) VALUES (?, ?, ?)");
            $stmt->execute([$title, $cover, $images]);
        }
        echo json_encode(["status" => "success", "message" => "Event saved"]);

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id)) {
            $stmt = $db->prepare("DELETE FROM media_events WHERE id = ?");
            $stmt->execute([$data->id]);
            echo json_encode(["status" => "success", "message" => "Event deleted"]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>