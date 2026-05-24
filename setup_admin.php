<?php
// backend/setup_admin.php
require_once 'config/database.php';

// Detect DB connection
if (isset($pdo)) { $db = $pdo; }
elseif (class_exists('Database')) { $database = new Database(); $db = $database->getConnection(); }
elseif (isset($conn)) { $db = $conn; }

try {
    // 1. Delete all existing admins to start fresh
    $db->query("DELETE FROM users WHERE role = 'admin'");

    // 2. Hash the new secure password
    $email = "adminsitaram@gmail.com";
    $password = "adminPASSWORD@#";
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 3. Insert the new Super Admin
    $query = "INSERT INTO users (name, email, password, role, can_create_admins) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute(['Sita Ram Master', $email, $hashed_password, 'admin', 1]);

    echo "<h2 style='color: green;'>✅ Success!</h2>";
    echo "<p>Existing admins removed. New Super Admin created.</p>";
    echo "<b>Email:</b> " . $email . "<br>";
    echo "<b>Password:</b> " . $password . "<br><br>";
    echo "<i>Security Notice: Please delete this setup_admin.php file now.</i>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error</h2>";
    echo $e->getMessage();
}
?>