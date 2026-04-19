<?php
require_once 'cors.php';

$host = "localhost";
$username = "root";
$password = ""; 
$dbname = "sitaram_dairy";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode(["error" => "Database Connection Failed: " . $e->getMessage()]));
}
?>