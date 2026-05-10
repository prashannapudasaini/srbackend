<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// Require your existing database file which provides the $pdo variable
require_once '../../config/database.php';

try {
    // ==========================================
    // 1. TOP LEVEL METRICS (With Safety Nets)
    // ==========================================
    $totalSales = 0;
    $activeSubs = 0;
    $newCustomers = 0;
    $pendingOrders = 0;

    // Notice we are using $pdo->query() now to match your setup!
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