<?php
// 1. ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Instantly resolve preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200);
    exit; 
}

// 🔥 Start Output Buffering to prevent stray PHP warnings from breaking the JSON
ob_start();

try {
    require_once '../../config/database.php';

    // 2. SAFELY INITIALIZE DATABASE (Matching your dashboard.php logic perfectly)
    $db_conn = isset($pdo) ? $pdo : (isset($db) ? $db : (isset($conn) ? $conn : null));
    if (!$db_conn && class_exists('Database')) {
        $database = new Database();
        $db_conn = method_exists($database, 'getConnection') ? $database->getConnection() : $database->connect();
    }
    $pdo = $db_conn;
    
    if (!$pdo) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Database Connection Failed. Check config."]);
        exit;
    }

    // 3. BULLETPROOF ADMIN TOKEN CHECK
    $adminToken = '';
    if (isset($_SERVER['HTTP_X_ADMIN_TOKEN']) && !empty($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
        $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'];
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = array_change_key_case($headers, CASE_LOWER);
        if (isset($headers['x-admin-token']) && !empty($headers['x-admin-token'])) {
            $adminToken = $headers['x-admin-token'];
        }
    }

    if (empty($adminToken)) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized. Token missing."]);
        exit();
    }

    $tokenStmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'admin' LIMIT 1");
    $tokenStmt->execute([$adminToken]);
    if (!$tokenStmt->fetch()) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid Admin Token."]);
        exit();
    }

    // 4. PROCESS THE DISPATCH UPDATE
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput);

    if(isset($data->route) && isset($data->status)) {
        
        $pdo->beginTransaction();

        // Strip out "Route: " so "Route: Kathmandu" becomes just "Kathmandu"
        $cleanLocation = trim(str_replace("Route:", "", $data->route));
        $time = isset($data->time) ? $data->time : '';

        // UPDATE ROUTE STATUS
        $stmt = $pdo->prepare("UPDATE subscriptions SET route_status = ? WHERE location = ? AND delivery_time = ? AND status = 'Active'");
        $stmt->execute([$data->status, $cleanLocation, $time]);

        // FETCH USERS ON THIS ROUTE
        $userStmt = $pdo->prepare("SELECT user_id FROM subscriptions WHERE location = ? AND delivery_time = ? AND status = 'Active'");
        $userStmt->execute([$cleanLocation, $time]);
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

        // SEND NOTIFICATIONS
        if(count($users) > 0) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $title = "Delivery Update: " . $data->status;
            
            $messages = [
                'Preparing' => "Your farm-fresh order is currently being packaged at our facility.",
                'Dispatched' => "Your order has left the facility and is on its way to your delivery zone.",
                'Out for Delivery' => "Our driver is in your area! Expect your delivery shortly.",
                'Delivered' => "Your order has been successfully delivered. Enjoy!"
            ];
            $msg = isset($messages[$data->status]) ? $messages[$data->status] : "Your delivery status has been updated to: " . $data->status;

            foreach($users as $u) {
                if($u['user_id']) {
                    $notifStmt->execute([$u['user_id'], $title, $msg]);
                }
            }
        }

        $pdo->commit();
        ob_clean(); // Ensure pure JSON output
        echo json_encode(["status" => "success", "message" => "Route updated and users notified!"]);

    } else {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Missing required data (route or status)."]);
    }

} catch (Throwable $e) { // 🔥 Catch Throwable catches Fatal Errors as well as Exceptions
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>