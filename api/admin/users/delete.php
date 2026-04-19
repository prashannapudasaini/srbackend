<?php
require_once '../../../config/database.php';
$data = json_decode(file_get_contents('php://input'));
if(isset($data->id)) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$data->id]);
    echo json_encode(["status" => "success"]);
}
?>