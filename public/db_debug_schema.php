<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/db_connect.php';

echo "<h1>Transactions Table Schema</h1><pre>";
try {
    $q = $pdo->query("DESCRIBE transactions");
    print_r($q->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Table 'transactions' not found or error: " . $e->getMessage();
}
echo "</pre>";
?>