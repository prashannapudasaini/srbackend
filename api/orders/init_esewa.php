<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }

require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

// 1. Validate Input
if (empty($data->amount) || empty($data->purchase_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Amount and Purchase ID are required."]);
    exit();
}

// 2. eSewa Sandbox Credentials
$esewa_secret_key = "8gBm/:&EnhH.1/q"; 
$merchant_code = "EPAYTEST";

// 3. Define Transaction Details
$amount = (float)$data->amount;
$tax_amount = 0;
$total_amount = $amount + $tax_amount;

// FIX: Append a unique ID so eSewa Sandbox never throws a duplicate error
$transaction_uuid = $data->purchase_id . "-" . uniqid(); 

$product_code = $merchant_code;

// 4. Set Success and Failure URLs
$success_url = "http://localhost:5173/payment-success";
$failure_url = "http://localhost:5173/payment-failure";

try {
    // 6. Generate the Secure HMAC-SHA256 Signature
    $signed_field_names = "total_amount,transaction_uuid,product_code";
    $message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
    
    $signature = hash_hmac('sha256', $message, $esewa_secret_key, true);
    $encoded_signature = base64_encode($signature);

    // 7. Return the Payload to React
    echo json_encode([
        "status" => "success",
        "message" => "eSewa initialized",
        "esewa_payload" => [
            "amount" => $amount,
            "failure_url" => $failure_url,
            "product_delivery_charge" => "0",
            "product_service_charge" => "0",
            "product_code" => $product_code,
            "signature" => $encoded_signature,
            "signed_field_names" => $signed_field_names,
            "success_url" => $success_url,
            "tax_amount" => $tax_amount,
            "total_amount" => $total_amount,
            "transaction_uuid" => $transaction_uuid
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>