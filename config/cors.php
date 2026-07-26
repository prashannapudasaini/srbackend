<?php
/**
 * CORS Configuration
 * Sitaram Gokul Milks API
 */

declare(strict_types=1);

// 1. Define allowed frontend domains (React web, Expo mobile, Production)
$allowedOrigins = [
    'http://localhost:3000',     // React Web App (Create React App)
    'http://localhost:5173',     // React Web App (Vite)
    'http://localhost:8081',     // Expo Mobile App (Web view)
    'https://sitaramdudh.com',   // Your Live Domain
    'https://www.sitaramdudh.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// 2. Safely match the origin
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    // Fallback for mobile API requests (React Native doesn't always send an origin)
    header("Access-Control-Allow-Origin: *"); 
}

// 3. Essential Security & Format Headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// 🔥 CRITICAL: Added X-Admin-Token to ensure your Admin Panel doesn't get blocked
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token");

// Cache preflight requests for 24 hours to speed up the React App
header("Access-Control-Max-Age: 86400"); 

// 4. Handle preflight OPTIONS request instantly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>