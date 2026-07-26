<?php
// 1. FORCE NEPAL TIMEZONE
date_default_timezone_set('Asia/Kathmandu');

// 2. CREDENTIALS
$merchantId = "3231";
$appId = "MER-3231-APP-1";
$appName = "Sitaram Gokul Dairy"; // If this fails, try changing this to: "Sitaram Gokul Milks Kathmandu Pvt.Ltd"
$pfxPassword = "123";
$pfxPath = __DIR__ . "/../../certs/CREDITOR.pfx"; 

// 3. GENERATE UNIQUE TRANSACTION
$txnId = "TEST-" . substr(time(), -5); 
$txnDate = date("d-m-Y"); 
$txnCrncy = "NPR";
$txnAmt = 100000; // 1000 NPR in Paisa
$referenceId = $txnId;
$remarks = "Test_Order";
$particulars = "Test_Payment";

$message = "{$merchantId},{$appId},{$appName},{$txnId},{$txnDate},{$txnCrncy},{$txnAmt},{$referenceId},{$remarks},{$particulars}";

// 4. SIGNATURE GENERATION
if (!file_exists($pfxPath)) {
    die("<h2>ERROR: PFX File NOT FOUND at $pfxPath</h2>");
}

$cert_store = file_get_contents($pfxPath);
$cert_info = array();
if (!openssl_pkcs12_read($cert_store, $cert_info, $pfxPassword)) {
    die("<h2>ERROR: Invalid PFX password or corrupt file.</h2>");
}

$private_key = $cert_info['pkey'];
if (!openssl_sign($message, $signature, $private_key, "sha256WithRSAEncryption")) {
    die("<h2>ERROR: Signature generation failed.</h2>");
}

$token = base64_encode($signature);

// 5. RENDER RAW HTML FORM (Bypasses React entirely)
?>
<!DOCTYPE html>
<html>
<head>
    <title>NCHL Diagnostic Tester</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f4f4f4; }
        .box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        code { background: #eee; padding: 5px; display: block; margin-top: 10px; word-wrap: break-word; }
        button { background: #00519E; color: white; border: none; padding: 15px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-top: 20px; width: 100%; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="color: green;">Signature Generated Successfully!</h2>
        <p>If clicking the button below results in a 401 error, <b>NCHL has misconfigured your Merchant Profile on their UAT server.</b></p>
        
        <p><strong>String being signed:</strong></p>
        <code><?php echo $message; ?></code>

        <!-- STANDALONE FORM -->
        <form action="https://uat.connectips.com/connectipswebgw/loginpage" method="POST">
            <input type="hidden" name="MERCHANTID" value="<?php echo $merchantId; ?>">
            <input type="hidden" name="APPID" value="<?php echo $appId; ?>">
            <input type="hidden" name="APPNAME" value="<?php echo $appName; ?>">
            <input type="hidden" name="TXNID" value="<?php echo $txnId; ?>">
            <input type="hidden" name="TXNDATE" value="<?php echo $txnDate; ?>">
            <input type="hidden" name="TXNCRNCY" value="<?php echo $txnCrncy; ?>">
            <input type="hidden" name="TXNAMT" value="<?php echo $txnAmt; ?>">
            <input type="hidden" name="REFERENCEID" value="<?php echo $referenceId; ?>">
            <input type="hidden" name="REMARKS" value="<?php echo $remarks; ?>">
            <input type="hidden" name="PARTICULARS" value="<?php echo $particulars; ?>">
            <input type="hidden" name="TOKEN" value="<?php echo $token; ?>">
            <button type="submit">Test Direct Payment to connectIPS</button>
        </form>
    </div>
</body>
</html>