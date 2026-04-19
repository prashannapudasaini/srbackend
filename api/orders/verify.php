<?php
require_once '../../config/database.php';
$data = json_decode(file_get_contents('php://input'));

// Basic stub for order creation
if(isset($data->total_amount)) {
    $stmt = $pdo->prepare("INSERT INTO orders (customer_name, phone_number, delivery_address, total_amount, payment_method) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$data->customer_name, $data->phone, $data->address, $data->total_amount, $data->payment_method]);
    echo json_encode(["status" => "success", "order_id" => $pdo->lastInsertId()]);
}
?>