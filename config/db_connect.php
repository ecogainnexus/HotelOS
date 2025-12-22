<?php
/**
 * config/db_connect.php
 * Secure Database Connection for HotelOS Enterprise
 */

// Basic security check
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('No direct access allowed.');
}

$host = 'localhost';
$db = 'uplfveim_hotelos';
$user = 'uplfveim_admin';
$pass = 'jm@HS10$$'; // Updated with the password you provided
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In production, log this, don't show it.
    error_log($e->getMessage());
    die("Database Connection Error. Please contact support.");
}
?>