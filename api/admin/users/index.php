<?php
require_once '../../../config/database.php';
try {
    $stmt = $pdo->query("SELECT id, name, email, role, phone, DATE(created_at) as joined FROM users ORDER BY created_at DESC");
    echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>