<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';
$data = json_decode(file_get_contents('php://input'));

if(isset($data->total_amount) && $data->total_amount > 0) {
    try {
        $pdo->beginTransaction();

        // 🔥 CRITICAL FIX 1: Safely grab the user_id sent from CheckoutPage.jsx
        $user_id = isset($data->user_id) ? $data->user_id : null;

        // 🔥 CRITICAL FIX 2: Insert Main Order WITH user_id attached
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, phone_number, delivery_address, total_amount, payment_method, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'processing')");
        $stmt->execute([
            $user_id, // Links this order to the logged-in user
            $data->customer_name, 
            $data->phone, 
            $data->address, 
            $data->total_amount, 
            $data->payment_method
        ]);
        
        $order_id = $pdo->lastInsertId();

        // 2. Insert Order Items (Keeping your excellent variant_id logic)
        if (isset($data->items) && is_array($data->items) && count($data->items) > 0) {
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($data->items as $item) {
                $productId = isset($item->id) ? $item->id : (isset($item->product_id) ? $item->product_id : 0);
                
                // CRITICAL FIX: If variant_id is missing or 0, set it to explicitly be NULL
                $variantId = (!empty($item->variant_id) && $item->variant_id != 0) ? $item->variant_id : null; 
                
                $price = isset($item->price) ? $item->price : (isset($item->price_npr) ? $item->price_npr : 0);
                $qty = isset($item->quantity) ? $item->quantity : 1;

                if ($productId > 0) {
                    $itemStmt->execute([$order_id, $productId, $variantId, $qty, $price]);
                }
            }
        }

        $pdo->commit();

        echo json_encode(["status" => "success", "order_id" => $order_id]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        http_response_code(400); 
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Missing total amount. The cart might be empty.",
        "received_payload" => $data
    ]);
}
?>