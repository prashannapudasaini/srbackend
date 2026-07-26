<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// 🔥 THE FIX: X-Admin-Token must be included here!
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Instantly resolve preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200);
    exit; 
}

// 1. INITIALIZE DATABASE FIRST
require_once '../../config/database.php';
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
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid Token."]);
    exit();
}

try {
    // 3. Fetch All Subscriptions
    // 🔥 CRITICAL FIX: Added s.route_status to the SQL query
    $query = "
        SELECT 
            s.id AS internal_id, 
            s.sub_id AS id, 
            s.plan_type AS plan, 
            s.location, 
            s.delivery_time AS time, 
            s.qualifies_free_ghee, 
            s.weekly_total_cost, 
            s.status, 
            s.payment_status AS payment, 
            s.route_status, 
            'ConnectIPS' AS payment_method, 
            'Website' AS source,            
            s.created_at,
            u.name AS customer, 
            u.email,
            u.phone
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
    ";
    
    $stmt = $db->query($query);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activeSubs = 0;
    $todaysDispatches = 0;
    $locationCounts = [];
    $pendingPayments = 0;
    $pendingInvoices = 0;
    $tomorrowsDemand = 0;
    $tomorrowDayName = date('l', strtotime('+1 day'));

    foreach ($subscriptions as &$sub) {
        
        if ($sub['payment'] === 'Pending' || $sub['payment'] === 'Overdue') {
            $pendingPayments += (float)$sub['weekly_total_cost'];
            $pendingInvoices++;
        }

        if ($sub['status'] === 'Active') {
            $activeSubs++;
            $todaysDispatches++;
            
            $loc = !empty($sub['location']) ? $sub['location'] : 'Kathmandu Central';
            $time = !empty($sub['time']) ? $sub['time'] : 'Morning (7:00 AM)';
            // Fallback to 'Preparing' if the DB column is empty
            $rStatus = !empty($sub['route_status']) ? $sub['route_status'] : 'Preparing'; 
            
            if (!isset($locationCounts[$loc])) {
                $locationCounts[$loc] = ['count' => 0, 'time' => $time, 'route_status' => $rStatus];
            }
            $locationCounts[$loc]['count']++;
        }

        $itemQuery = "
            SELECT 
                si.day_of_week, 
                si.qty, 
                si.price, 
                si.product_name,
                p.base_image 
            FROM subscription_items si 
            LEFT JOIN products p ON si.product_id = p.id 
            WHERE si.subscription_id = :id_string OR si.subscription_id = :id_int
        ";
        
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->execute([
            ':id_string' => $sub['id'], 
            ':id_int' => $sub['internal_id']
        ]); 
        
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        $sub['items'] = $items;
        $sub['days'] = array_unique(array_column($items, 'day_of_week'));

        if ($sub['status'] === 'Active') {
            foreach ($items as $item) {
                if (strcasecmp($item['day_of_week'], $tomorrowDayName) == 0) {
                    $tomorrowsDemand += (int)$item['qty'];
                }
            }
        }
    }

    // 4. Build Dynamic Routes Array using REAL database status
    $dispatchRoutes = [];
    
    foreach($locationCounts as $loc => $data) {
        $status = ucfirst(strtolower($data['route_status']));
        
        // Map string status to progress percentage for the UI
        $progress = 0;
        if ($status === 'Dispatched') $progress = 33;
        if ($status === 'Out for delivery') $progress = 66;
        if ($status === 'Delivered' || $status === 'Completed') $progress = 100;

        $dispatchRoutes[] = [
            "route" => "Route: " . $loc, 
            "time" => $data['time'], 
            "count" => $data['count'], 
            "status" => $status, 
            "progress" => $progress
        ];
    }
    
    if (empty($dispatchRoutes)) {
        $dispatchRoutes[] = [ "route" => "No Active Routes Today", "time" => "-", "count" => 0, "status" => "Standby", "progress" => 0 ];
    }

    $metrics = [
        "activeSubs" => $activeSubs, "todaysDispatches" => $todaysDispatches,
        "pendingPayments" => $pendingPayments, "pendingInvoices" => $pendingInvoices,
        "tomorrowsDemand" => $tomorrowsDemand
    ];

    echo json_encode([
        "status" => "success",
        "data" => [
            "subscriptions" => $subscriptions,
            "dispatchRoutes" => $dispatchRoutes,
            "metrics" => $metrics
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to load subscriptions: " . $e->getMessage()]);
}
?>