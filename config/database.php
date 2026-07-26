<?php

declare(strict_types=1);

// Using __DIR__ forces PHP to look in the current 'config' folder
require_once __DIR__ . '/cors.php';

class Database
{
    private string $host = 'localhost'; // This usually remains 'localhost' on live servers
    
    // 🔥 LIVE CREDENTIALS APPLIED HERE
    private string $dbname = 'ramsita_db'; 
    private string $username = 'ramsita_admin';
    private string $password = 'adminPASSWORD@123';

    public function connect(): PDO
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";

            return new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                ]
            );

        }catch (PDOException $e) {
    die($e->getMessage());
}
    }
}

$database = new Database();
$pdo = $database->connect();

?>