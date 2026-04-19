<?php
require_once '../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'));

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
    } 
    elseif ($method === 'POST') {
        if (!empty($data->name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$data->name]);
            echo json_encode(["status" => "success"]);
        }
    } 
    elseif ($method === 'DELETE') {
        if (!empty($data->id)) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$data->id]);
            echo json_encode(["status" => "success"]);
        }
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>