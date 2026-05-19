<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../config/database.php';

// Detect DB connection
if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

try {
    // 1. Fetch All Subscriptions
    $query = "
        SELECT 
            s.sub_id AS id, 
            s.plan_type AS plan, 
            s.location, 
            s.delivery_time AS time, 
            s.weekly_total_cost, 
            s.status, 
            s.created_at,
            u.name AS customer, 
            u.email,
            u.phone,
            'Paid' AS payment 
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
    ";
    
    $stmt = $db->query($query);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Dynamic Dispatch Routing & Metrics
    $activeSubs = 0;
    $todaysDispatches = 0;
    $locationCounts = [];
    $pendingPayments = 0;
    $pendingInvoices = 0;

    foreach ($subscriptions as $sub) {
        // Calculate Payments
        if ($sub['payment'] === 'Pending' || $sub['payment'] === 'Overdue') {
            $pendingPayments += (float)$sub['weekly_total_cost'];
            $pendingInvoices++;
        }

        // Calculate Active Dispatches
        if ($sub['status'] === 'Active') {
            $activeSubs++;
            $todaysDispatches++;
            
            $loc = !empty($sub['location']) ? $sub['location'] : 'Kathmandu Central';
            $time = !empty($sub['time']) ? $sub['time'] : 'Morning (7:00 AM)';
            
            if (!isset($locationCounts[$loc])) {
                $locationCounts[$loc] = [
                    'count' => 0,
                    'time' => $time
                ];
            }
            $locationCounts[$loc]['count']++;
        }
    }

    // 3. Build Dynamic Routes Array
    $dispatchRoutes = [];
    $hour = (int)date('H'); // Current hour to estimate progress
    
    foreach($locationCounts as $loc => $data) {
        $isMorning = stripos($data['time'], 'Morning') !== false;
        
        // Dynamic progress calculation based on time of day
        if ($isMorning) {
            $progress = ($hour >= 10) ? 100 : (($hour >= 6) ? 50 : 0);
        } else {
            $progress = ($hour >= 19) ? 100 : (($hour >= 16) ? 50 : 0);
        }
        
        $status = $progress === 100 ? 'Completed' : ($progress > 0 ? 'Out for Delivery' : 'Preparing');

        $dispatchRoutes[] = [
            "route" => "Route: " . $loc,
            "time" => $data['time'],
            "count" => $data['count'],
            "status" => $status,
            "progress" => $progress
        ];
    }

    // Fallback if no active routes
    if (empty($dispatchRoutes)) {
        $dispatchRoutes[] = [ "route" => "No Active Routes Today", "time" => "-", "count" => 0, "status" => "Standby", "progress" => 0 ];
    }

    // Proxy for tomorrow's demand (assuming 1.5 Liters average per active sub)
    $tomorrowsDemand = $activeSubs * 1.5;

    $metrics = [
        "activeSubs" => $activeSubs,
        "todaysDispatches" => $todaysDispatches,
        "pendingPayments" => $pendingPayments,
        "pendingInvoices" => $pendingInvoices,
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