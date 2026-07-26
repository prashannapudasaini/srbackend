<?php
// backend/setup_admin.php
require_once "config/database.php";

$email = "adminsitaram@gmail.com";
$password = "adminPASSWORD@"; // The password you want to use
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if the admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user to ensure they are an admin with the right password
        $sql = "UPDATE users SET password_hash = ?, role = 'admin', failed_attempts = 0, locked_until = NULL WHERE email = ?";
        $pdo->prepare($sql)->execute([$hashed_password, $email]);
        echo "<h1>Success!</h1><p>Admin password updated.</p>";
    } else {
        // Insert brand new admin
        $sql = "INSERT INTO users (name, email, phone, address, password_hash, role, can_create_admins, created_at) 
                VALUES ('Super Admin', ?, '9800000000', 'Kathmandu', ?, 'admin', 1, NOW())";
        $pdo->prepare($sql)->execute([$email, $hashed_password]);
        echo "<h1>Success!</h1><p>New Admin account created.</p>";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>