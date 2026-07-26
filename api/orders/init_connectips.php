<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. FORCE NEPAL TIMEZONE (Critical for Bank Validation)
date_default_timezone_set('Asia/Kathmandu');

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->purchase_id) || !isset($data->amount)) {
    echo json_encode(['success' => false, 'message' => 'Missing purchase_id or amount']);
    exit;
}

// 2. Credentials
$merchantId = trim("3231");
$appId = trim("MER-3231-APP-1");
$appName = trim("Sitaram Gokul Dairy");
$pfxPassword = trim("123");
$pfxPath = __DIR__ . "/../../certs/CREDITOR.pfx"; 

$orderId = trim($data->purchase_id);
$totalAmount = floatval($data->amount);

// 3. STRICT TRANSACTION DETAILS
$uniqueSuffix = substr(time(), -5); 
$txnId = "O-" . $orderId . "-" . $uniqueSuffix; 
$txnDate = date("d-m-Y"); 
$txnCrncy = "NPR";
$txnAmt = intval(round($totalAmount * 100)); // connectIPS requires amount in Paisa
$referenceId = "R-" . $orderId . "-" . $uniqueSuffix;
$remarks = "Order_" . $orderId;
$particulars = "Dairy_Products";

// ========================================================================
// CRITICAL FIX: The exact KEY=VALUE format connectIPS demands
// ========================================================================
$message = "MERCHANTID={$merchantId},APPID={$appId},APPNAME={$appName},TXNID={$txnId},TXNDATE={$txnDate},TXNCRNCY={$txnCrncy},TXNAMT={$txnAmt},REFERENCEID={$referenceId},REMARKS={$remarks},PARTICULARS={$particulars},TOKEN=TOKEN";

// 4. SIGNATURE GENERATION
if (!file_exists($pfxPath)) {
    echo json_encode(["success" => false, "message" => "PFX File NOT FOUND."]);
    exit;
}

$cert_store = file_get_contents($pfxPath);
$cert_info = array();
if (!openssl_pkcs12_read($cert_store, $cert_info, $pfxPassword)) {
    echo json_encode(["success" => false, "message" => "Invalid PFX password."]);
    exit;
}

$private_key = $cert_info['pkey'];

if (!openssl_sign($message, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
    echo json_encode(["success" => false, "message" => "Signature failed."]);
    exit;
}

// Strip all invisible whitespace and newlines from the generated Base64 token
$token = trim(preg_replace('/\s+/', '', base64_encode($signature)));

// 5. OUTPUT
echo json_encode([
    "success" => true,
    "gatewayUrl" => "https://uat.connectips.com/connectipswebgw/loginpage",
    "payload" => [
        "MERCHANTID" => $merchantId,
        "APPID" => $appId,
        "APPNAME" => $appName,
        "TXNID" => $txnId,
        "TXNDATE" => $txnDate,
        "TXNCRNCY" => $txnCrncy,
        "TXNAMT" => $txnAmt,
        "REFERENCEID" => $referenceId,
        "REMARKS" => $remarks,
        "PARTICULARS" => $particulars,
        "TOKEN" => $token
    ]
]);
?>