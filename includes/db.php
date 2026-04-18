<?php
// includes/db.php

require_once 'env.php';

// Load environment variables
loadEnv(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'db_survei_kiosk';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Return arrays indexed by column name
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Emulate prepares for performance unless strict typing is forced
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    // If connection fails, halt execution and display a safe error message
    // Note: In production, do not echo the raw error message to the browser
    die("Penyelarasan Database Sedang Dikonfigurasi. Silakan hubungi admin.");
}
?>
