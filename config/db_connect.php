<?php
/**
 * config/db_connect.php
 * 
 * Secure Database Connection.
 * This file should NOT be accessible via URL.
 */

// Simple security check
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('No direct access allowed.');
}

// @TODO: In a real CI/CD pipeline with GitHub Actions, 
// we would often use a 'sed' command to replace these placeholders 
// during the build process, OR you can rely on the file already existing 
// on the server with real credentials and exclude it from the upload.
// FOR NOW: We will use placeholders.
// IMPORTANT: Update these on the SERVER or locally before pushing if repository is private.

$host = 'localhost';
$db = 'uplfveim_hotelos';
$user = 'REPLACE_WITH_DB_USER';
$pass = 'REPLACE_WITH_DB_PASS';
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
    // Log error, don't show to user
    error_log($e->getMessage());
    die("Database Connection Error. Please check logs.");
}
