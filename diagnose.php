<?php
/**
 * diagnose.php - Server Diagnostic Tool
 * Place this in root to check what's wrong with the deployment
 */

echo "<h1>HotelOS Server Diagnostic Report</h1>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} .ok{color:green;} .error{color:red;} .warn{color:orange;} pre{background:#fff;padding:10px;border:1px solid #ddd;}</style>";

echo "<h2>1. Current Directory Structure</h2>";
echo "<pre>";
echo "Current File: " . __FILE__ . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "</pre>";

echo "<h2>2. File Existence Check</h2>";
$files = [
    'index.php' => __DIR__ . '/index.php',
    'public/index.php' => __DIR__ . '/public/index.php',
    'public/api_login.php' => __DIR__ . '/public/api_login.php',
    'public/dashboard.php' => __DIR__ . '/public/dashboard.php',
    'public/checkin.php' => __DIR__ . '/public/checkin.php',
    'public/checkout.php' => __DIR__ . '/public/checkout.php',
    'public/layout.php' => __DIR__ . '/public/layout.php',
    'config/db_connect.php' => __DIR__ . '/config/db_connect.php',
    '.htaccess' => __DIR__ . '/.htaccess',
];

foreach ($files as $label => $path) {
    $exists = file_exists($path);
    $class = $exists ? 'ok' : 'error';
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    $readable = $exists && is_readable($path) ? '(Readable)' : '(Not Readable)';
    echo "<div class='$class'>$label: $status $readable</div>";
}

echo "<h2>3. Database Connection Test</h2>";
try {
    require_once __DIR__ . '/config/db_connect.php';
    if (isset($pdo)) {
        echo "<div class='ok'>✓ Database connected successfully</div>";

        // Test tables
        $tables = ['tenants', 'users', 'rooms', 'bookings', 'guests', 'transactions'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<div class='ok'>✓ Table '$table': $count rows</div>";
            } catch (Exception $e) {
                echo "<div class='error'>✗ Table '$table': " . $e->getMessage() . "</div>";
            }
        }
    } else {
        echo "<div class='error'>✗ \$pdo not defined</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Database Error: " . $e->getMessage() . "</div>";
}

echo "<h2>4. Session Test</h2>";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='ok'>✓ Session working</div>";
    echo "<div>Session ID: " . session_id() . "</div>";
} else {
    echo "<div class='error'>✗ Session not working</div>";
}

echo "<h2>5. PHP Info</h2>";
echo "<div>PHP Version: " . phpversion() . "</div>";
echo "<div>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</div>";

echo "<h2>6. URL Testing</h2>";
echo "<div>Try these URLs:</div>";
echo "<ul>";
echo "<li><a href='/HotelOS/'>Root Entry (/HotelOS/)</a></li>";
echo "<li><a href='/HotelOS/index.php'>Direct Index</a></li>";
echo "<li><a href='/HotelOS/public/index.php'>Public Index</a></li>";
echo "<li><a href='/HotelOS/public/api_login.php'>API Login (will show error)</a></li>";
echo "</ul>";

echo "<h2>7. .htaccess Content</h2>";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/.htaccess')) . "</pre>";
} else {
    echo "<div class='error'>✗ .htaccess missing</div>";
}

echo "<hr><p><strong>Action:</strong> If all files exist and database is connected, the login should work at <a href='/HotelOS/public/index.php'>/HotelOS/public/index.php</a></p>";
echo "<p><strong>Test Credentials:</strong> Hotel: DEMO | Email: admin@hotelos.in | Password: admin123</p>";
?>