<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('Asia/Kathmandu');
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->txnId) || empty($data->txnId)) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID missing.']);
    exit;
}

// EXACT ID FROM URL (e.g., O-105-72901 or O-SUB_8-72440)
$txnId = trim($data->txnId); 

// 1. DETERMINE TYPE AND EXTRACT DATABASE ID
$isSubscription = strpos($txnId, 'SUB') !== false;

if ($isSubscription) {
    // Extracts '8' from 'O-SUB_8-72440'
    preg_match('/SUB_(\d+)-/', $txnId, $matches);
    $orderId = isset($matches[1]) ? $matches[1] : 0;
} else {
    // Extracts '105' from 'O-105-72901'
    preg_match('/O-(\d+)-/', $txnId, $matches);
    $orderId = isset($matches[1]) ? $matches[1] : 0;
}

// Fallback extraction
if ($orderId == 0) {
    preg_match('/\d+/', $txnId, $matches);
    $orderId = isset($matches[0]) ? $matches[0] : 0;
}

if ($orderId == 0) {
    echo json_encode(['success' => false, 'message' => "Invalid TXNID format"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();

    // 2. QUERY THE CORRECT TABLE
    if ($isSubscription) {
        $stmt = $db->prepare("SELECT weekly_total_cost as total_amount, payment_status FROM subscriptions WHERE id = :id");
    } else {
        $stmt = $db->prepare("SELECT total_amount, payment_status FROM orders WHERE id = :id");
    }
    
    $stmt->bindParam(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => "Order not found."]);
        exit;
    }

    if (strtolower($order['payment_status']) === 'completed' || strtolower($order['payment_status']) === 'paid') {
        echo json_encode(['success' => true, 'message' => 'Already verified.']);
        exit;
    }

    $merchantId = trim("3231");
    $appId = trim("MER-3231-APP-1");
    $txnAmt = intval(round($order['total_amount'] * 100)); 
    
    // 🔥 CRITICAL REVERT: Keep Reference ID EXACTLY as the txnId like your old working code!
    $nchlReferenceId = $txnId; 
    
    $pfxPath = __DIR__ . "/../../certs/CREDITOR.pfx"; 
    $pfxPassword = trim("123"); 
    
    // Hash string EXACTLY as documented by NCHL for Validation
    $message = "MERCHANTID={$merchantId},APPID={$appId},REFERENCEID={$nchlReferenceId},TXNAMT={$txnAmt}";
    
    $token = "";
    if (file_exists($pfxPath)) {
        $cert_store = file_get_contents($pfxPath);
        $cert_info = array();
        
        if (openssl_pkcs12_read($cert_store, $cert_info, $pfxPassword)) {
            $private_key = $cert_info['pkey'];
            openssl_sign($message, $signature, $private_key, OPENSSL_ALGO_SHA256);
            $token = trim(preg_replace('/\s+/', '', base64_encode($signature)));
        } else {
            echo json_encode(["success" => false, "message" => "Invalid PFX password"]);
            exit;
        }
    } else {
        echo json_encode(["success" => false, "message" => "CREDITOR.pfx missing"]);
        exit;
    }

    $basicAuthUser = "MER-3231-APP-1";
    $basicAuthPass = "Abcd@123";
    $authHeader = base64_encode("$basicAuthUser:$basicAuthPass");

    $payload = json_encode([
        "merchantId" => $merchantId,
        "appId" => $appId,
        "referenceId" => $nchlReferenceId,
        "txnAmt" => $txnAmt,
        "token" => $token 
    ]);

    $ch = curl_init("https://uat.connectips.com/connectipswebws/api/creditor/validatetxn");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $authHeader
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
        
        // 3. UPDATE THE CORRECT TABLE
        if ($isSubscription) {
            $updateStmt = $db->prepare("UPDATE subscriptions SET payment_status = 'completed' WHERE id = :id");
            $updateStmt->bindParam(':id', $orderId);
        } else {
            $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'completed', esewa_ref = :ref WHERE id = :id");
            $updateStmt->bindParam(':ref', $txnId); 
            $updateStmt->bindParam(':id', $orderId);
        }
        $updateStmt->execute();

        echo json_encode(['success' => true, 'message' => 'Payment verified successfully!']);
        
    } else {
        // DIAGNOSTIC DUMP
        $debugStr = "API Status: " . (isset($responseData['status']) ? $responseData['status'] : 'N/A') . " | ";
        $debugStr .= "Message: " . (isset($responseData['statusMessage']) ? $responseData['statusMessage'] : 'N/A') . " | ";
        $debugStr .= "Sent REF: " . $nchlReferenceId . " | ";
        $debugStr .= "Hashed: " . $message;

        echo json_encode([
            'success' => false, 
            'message' => $debugStr
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>