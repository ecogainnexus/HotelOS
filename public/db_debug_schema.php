<?php
// public/db_debug_schema.php
// INSPECT DATABASE SCHEMA
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db_connect.php';

function showTable($pdo, $table)
{
    try {
        echo "<h1>Table: $table</h1>";
        $q = $pdo->query("DESCRIBE $table");
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($rows);
        echo "</pre>";
    } catch (Exception $e) {
        echo "<h2>Error inspecting $table: " . $e->getMessage() . "</h2>";
    }
}

showTable($pdo, 'rooms');
showTable($pdo, 'bookings');
?>