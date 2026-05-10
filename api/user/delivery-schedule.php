<?php
// ALLOW CORS (Must be at the very top!)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request from React
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php'; // Ensure this path matches your folder structure

$database = new Database();
$conn = $database->getConnection();

// Get the JSON payload from React
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->subscriptionDetails) && !empty($data->weeklySchedule)) {
    try {
        // Start Transaction
        $conn->beginTransaction();

        // 1. Get the user ID. 
        // In a fully live app, you extract this from the Authorization JWT header.
        // For now, we assume user_id = 1 (Update this with your actual user token logic later)
        $user_id = 1; 
        
        // Generate a unique Subscription ID like "SUB-849201"
        $sub_id = "SUB-" . rand(100000, 999999);
        
        // Fallback for delivery time in case the UI doesn't explicitly send it
        $delivery_time = isset($data->subscriptionDetails->deliveryTime) ? $data->subscriptionDetails->deliveryTime : 'morning';

        // 2. Insert into the main `subscriptions` table
        $query = "INSERT INTO subscriptions 
                  (sub_id, user_id, location, plan_type, delivery_time, qualifies_free_ghee, weekly_total_cost) 
                  VALUES (:sub_id, :user_id, :location, :plan_type, :delivery_time, :qualifies_free_ghee, :weekly_total_cost)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':sub_id' => $sub_id,
            ':user_id' => $user_id,
            ':location' => $data->subscriptionDetails->location,
            ':plan_type' => ucfirst($data->subscriptionDetails->type), // 'Weekly' or 'Monthly'
            ':delivery_time' => $delivery_time,
            ':qualifies_free_ghee' => $data->subscriptionDetails->qualifiesForFreeGhee ? 1 : 0,
            ':weekly_total_cost' => $data->subscriptionDetails->weeklyTotalCost
        ]);

        // Get the internal database ID of the subscription we just created
        $subscription_internal_id = $conn->lastInsertId();

        // 3. Loop through the weekly schedule and insert into `subscription_items`
        $item_query = "INSERT INTO subscription_items 
                       (subscription_id, day_of_week, product_id, product_name, size, qty, price) 
                       VALUES (:sub_id, :day, :product_id, :name, :size, :qty, :price)";
        
        $item_stmt = $conn->prepare($item_query);

        foreach ($data->weeklySchedule as $dayData) {
            $day = $dayData->day;
            
            foreach ($dayData->items as $item) {
                $item_stmt->execute([
                    ':sub_id' => $subscription_internal_id,
                    ':day' => $day,
                    ':product_id' => $item->productId,
                    ':name' => $item->name,
                    ':size' => $item->size,
                    ':qty' => $item->qty,
                    ':price' => $item->price
                ]);
            }
        }

        // 4. UPDATE VIP LOYALTY TRACKER
        // Add +1 to their subscription count
        $updateLoyalty = "UPDATE users SET subscription_count = COALESCE(subscription_count, 0) + 1 WHERE id = :user_id";
        $loyaltyStmt = $conn->prepare($updateLoyalty);
        $loyaltyStmt->execute([':user_id' => $user_id]);

        // 5. Fetch the new count to send back to React
        $getCount = "SELECT subscription_count FROM users WHERE id = :user_id";
        $countStmt = $conn->prepare($getCount);
        $countStmt->execute([':user_id' => $user_id]);
        $newCount = $countStmt->fetchColumn();

        // Commit Transaction (Save everything safely to DB)
        $conn->commit();
        
        http_response_code(201);
        echo json_encode([
            "status" => "success", 
            "sub_id" => $sub_id, 
            "loyalty_count" => $newCount ? (int)$newCount : 1, // Fallback to 1 if it fails
            "message" => "Subscription successfully created."
        ]);

    } catch (Exception $e) {
        // If anything fails, rollback the transaction so no partial data is saved
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload data."]);
}
?>