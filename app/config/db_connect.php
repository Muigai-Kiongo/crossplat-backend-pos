<?php
// app/config/db_connect.php
$db_host = getenv('DB_HOST') ?: 'db';
$db_port = getenv('DB_PORT') ?: '5432';
$db_name = getenv('DB_DATABASE') ?: 'mkon_pos';
$db_user = getenv('DB_USERNAME') ?: 'pos_admin';
$db_pass = getenv('DB_PASSWORD') ?: 'secretpassword';

try {
    $pdo = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>