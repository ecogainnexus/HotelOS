<?php
/**
 * db_connect.php
 * 
 * Secure Database Connection using PDO.
 * Designed for HotelOS v2.0 (Multi-Tenant Architecture).
 */

// Strict Error Reporting for Debugging (Disable in Production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Credentials
// @TODO: Replace these placeholders with your actual cPanel Database credentials
$host = 'localhost';
$db   = 'uplfveim_hotelos';
$user = 'REPLACE_WITH_DB_USER'; // e.g., uplfveim_admin
$pass = 'REPLACE_WITH_DB_PASS'; // e.g., YourStrongPassword#2025
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return arrays indexed by column name
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Connection Successful
} catch (\PDOException $e) {
    // In production, log this error instead of showing it
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
