<?php
// ALLOW CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/cors.php';

if(isset($_FILES['image'])) {
    $target_dir = "../../uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $file_name;

    if(move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Return the public URL to React
        echo json_encode(["status" => "success", "url" => "/uploads/" . $file_name]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Upload failed"]);
    }
}
?>