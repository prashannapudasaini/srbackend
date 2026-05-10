<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once '../../config/database.php';

// --- FOOLPROOF DATABASE CONNECTION DETECTOR ---
if (isset($pdo)) {
    $db = $pdo;
} elseif (class_exists('Database')) {
    $database = new Database();
    $db = $database->getConnection();
} elseif (isset($conn)) {
    $db = $conn;
} else {
    echo json_encode(["status" => "error", "message" => "Database connection not found."]);
    exit;
}

try {
    // 1. Get Current Milk Stock
    $milkStockQuery = "
        SELECT SUM(v.stock_quantity) 
        FROM product_variants v
        JOIN products p ON v.product_id = p.id
        WHERE p.name LIKE '%Milk%' OR p.category LIKE '%Milk%'
    ";
    $currentMilkStock = $db->query($milkStockQuery)->fetchColumn() ?: 0;

    // 2. Get Current Ghee Stock
    $gheeStockQuery = "
        SELECT SUM(v.stock_quantity) 
        FROM product_variants v
        JOIN products p ON v.product_id = p.id
        WHERE p.name LIKE '%Ghee%' OR p.category LIKE '%Ghee%'
    ";
    $currentGheeStock = $db->query($gheeStockQuery)->fetchColumn() ?: 0;

    // 3. Calculate "Today's Demand" from Subscriptions
    $todayName = date('l'); 
    $demandQuery = "
        SELECT SUM(si.qty) 
        FROM subscription_items si
        JOIN subscriptions s ON si.subscription_id = s.id
        WHERE si.day_of_week = :today AND s.status = 'Active' AND (si.product_name LIKE '%Milk%')
    ";
    $demandStmt = $db->prepare($demandQuery);
    $demandStmt->execute(['today' => $todayName]);
    $todayDemand = $demandStmt->fetchColumn() ?: 0;

    // 4. Calculate "Morning Stock" 
    $morningMilkStock = $currentMilkStock + $todayDemand;

    $metrics = [
        "morningMilkStock" => (int)$morningMilkStock,
        "currentMilkStock" => (int)$currentMilkStock,
        "todayDemand" => (int)$todayDemand,
        "gheeStock" => (int)$currentGheeStock
    ];

    // 5. Fetch Full Inventory List (WITH Images!)
    $inventoryQuery = "
        SELECT 
            p.name, 
            p.category, 
            p.image,
            v.size, 
            v.stock_quantity as stock
        FROM product_variants v
        JOIN products p ON v.product_id = p.id
        ORDER BY p.category ASC, p.name ASC
    ";
    $inventoryItems = $db->query($inventoryQuery)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => [
            "metrics" => $metrics,
            "items" => $inventoryItems
        ]
    ]);

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]);
}
?>