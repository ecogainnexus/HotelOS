<?php
// public/test_db.php
// Temporary Debug File
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$db = 'uplfveim_hotelos';
$user = 'uplfveim_admin';
$pass = 'jm@HS10$$'; // Testing the current credential
$charset = 'utf8mb4';

echo "Attempting connection...<br>";
echo "User: $user<br>";
echo "DB: $db<br>";

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    echo "<h1>SUCCESS: Database Connected!</h1>";
} catch (\PDOException $e) {
    echo "<h1>ERROR:</h1>";
    echo $e->getMessage();
}
?>