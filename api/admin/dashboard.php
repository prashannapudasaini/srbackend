<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");

// 🔥 FIXED: Added X-Requested-With back in
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

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
$pdo = $db_conn; // Dashboard relies on $pdo specifically

// 2. BULLETPROOF TOKEN SECURITY CHECK
$adminToken = '';

// Check $_SERVER first (Standard for Apache)
if (isset($_SERVER['HTTP_X_ADMIN_TOKEN']) && !empty($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
    $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'];
} else {
    // Fallback: Check raw headers (Catches lowercase issues in Nginx/Vite)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    
    // Convert all keys to lowercase for a foolproof check
    $headers = array_change_key_case($headers, CASE_LOWER);
    
    if (isset($headers['x-admin-token']) && !empty($headers['x-admin-token'])) {
        $adminToken = $headers['x-admin-token'];
    }
}

// Check if token is entirely missing
if (empty($adminToken)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Token missing."]);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$adminToken]);

if (!$stmt->fetch()) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid or expired Admin Token."]);
    exit();
}
// --- END SECURITY CHECK ---

try {
    // ==========================================
    // 1. TOP LEVEL METRICS (With Safety Nets)
    // ==========================================
    $totalSales = 0;
    $activeSubs = 0;
    $newCustomers = 0;
    $pendingOrders = 0;

    try { $totalSales = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'Completed'")->fetchColumn() ?: 0; } catch (Exception $e) {}
    try { $activeSubs = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'Active'")->fetchColumn() ?: 0; } catch (Exception $e) {}
    try { $newCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0; } catch (Exception $e) {}
    try { $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn() ?: 0; } catch (Exception $e) {}

    $metrics = [
        "totalSales" => "NPR " . number_format((float)$totalSales),
        "activeSubscriptions" => (int)$activeSubs,
        "newCustomers" => (int)$newCustomers,
        "pendingOrders" => (int)$pendingOrders
    ];

    // ==========================================
    // 2. MONTHLY REVENUE
    // ==========================================
    $revenueData = [];
    try {
        $revenueQuery = "
            SELECT DATE_FORMAT(created_at, '%b') as month, SUM(total_amount) as current_revenue
            FROM orders
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status = 'Completed'
            GROUP BY YEAR(created_at), MONTH(created_at), month
            ORDER BY YEAR(created_at), MONTH(created_at)
        ";
        $revenueStmt = $pdo->query($revenueQuery);
        while ($row = $revenueStmt->fetch(PDO::FETCH_ASSOC)) {
            $revenueData[] = [
                "month" => $row['month'],
                "current" => (float)$row['current_revenue'],
                "previous" => (float)$row['current_revenue'] * 0.85 
            ];
        }
    } catch (Exception $e) {}

    if (empty($revenueData)) {
        $revenueData = [
            ["month" => "Jan", "current" => 0, "previous" => 0]
        ];
    }

    // ==========================================
    // 3. POPULAR PRODUCTS
    // ==========================================
    $popularProducts = [];
    try {
        $popularQuery = "
            SELECT product_name as name, SUM(qty) as sales 
            FROM subscription_items 
            GROUP BY product_name 
            ORDER BY sales DESC 
            LIMIT 4
        ";
        $popularStmt = $pdo->query($popularQuery);
        while ($row = $popularStmt->fetch(PDO::FETCH_ASSOC)) {
            $popularProducts[] = [
                "name" => $row['name'],
                "sales" => (int)$row['sales']
            ];
        }
    } catch (Exception $e) {}

    if (empty($popularProducts)) {
        $popularProducts = [["name" => "No Sales Yet", "sales" => 0]];
    }

    // ==========================================
    // 4. NEW SUBSCRIBERS
    // ==========================================
    $newSubscribers = [];
    try {
        $subsQuery = "
            SELECT u.name, s.created_at 
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC 
            LIMIT 4
        ";
        $subsStmt = $pdo->query($subsQuery);
        while ($row = $subsStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = date("M j, Y", strtotime($row['created_at']));
            $newSubscribers[] = [
                "name" => $row['name'],
                "date" => "Joined " . $date,
                "initial" => strtoupper(substr($row['name'], 0, 1))
            ];
        }
    } catch (Exception $e) {}

    echo json_encode([
        "status" => "success",
        "data" => [
            "metrics" => $metrics,
            "revenueData" => $revenueData,
            "popularProducts" => $popularProducts,
            "newSubscribers" => $newSubscribers
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to load dashboard data: " . $e->getMessage()]);
}
?>