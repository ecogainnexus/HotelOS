<?php
// public/db_debug_schema.php
// INSPECT DATABASE SCHEMA
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db_connect.php';

try {
    echo "<h1>Table: tenants</h1>";
    $q = $pdo->query("DESCRIBE tenants");
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($rows);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>