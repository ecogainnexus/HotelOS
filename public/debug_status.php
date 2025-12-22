<?php
/**
 * public/debug_status.php
 * HotelOS Enterprise - System Status Diagnostic Tool
 * SECURITY: Delete this file in production or add proper authentication
 */

// Basic Security: Uncomment and set your IP to restrict access
// $allowed_ips = ['127.0.0.1', 'YOUR_IP_HERE'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
//     die('Access Denied');
// }

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HotelOS - System Diagnostic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #0a0e27;
            color: #0f0;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        h1 {
            color: #0ff;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid #0ff;
            padding-bottom: 10px;
        }

        h2 {
            color: #ff0;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        .status {
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid;
        }

        .ok {
            background: rgba(0, 255, 0, 0.1);
            border-color: #0f0;
        }

        .error {
            background: rgba(255, 0, 0, 0.1);
            border-color: #f00;
            color: #f66;
        }

        .warning {
            background: rgba(255, 255, 0, 0.1);
            border-color: #ff0;
            color: #ff0;
        }

        .info {
            background: rgba(0, 255, 255, 0.1);
            border-color: #0ff;
            color: #0ff;
        }

        pre {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            overflow-x: auto;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        th {
            color: #0ff;
            background: rgba(0, 255, 255, 0.1);
        }

        .timestamp {
            text-align: center;
            color: #888;
            margin-top: 30px;
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🛡️ HOTELOS SYSTEM DIAGNOSTIC</h1>

        <?php
        $results = [];
        $overall_status = 'OK';

        // ========================================
        // 1. SERVER INFORMATION
        // ========================================
        ?>
        <h2>📡 Server Information</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Server Time</td>
                <td><?= date('Y-m-d H:i:s T') ?></td>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?= PHP_VERSION ?></td>
            </tr>
            <tr>
                <td>Server Software</td>
                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
            </tr>
            <tr>
                <td>Document Root</td>
                <td><?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></td>
            </tr>
        </table>

        <?php
        // ========================================
        // 2. DATABASE CONNECTION
        // ========================================
        ?>
        <h2>🗄️ Database Connection</h2>
        <?php
        try {
            require_once __DIR__ . '/../config/db_connect.php';
            echo '<div class="status ok">✓ Database Connection: SUCCESS</div>';
            $results['db_connection'] = 'OK';

            // Get database name
            $dbname = $pdo->query("SELECT DATABASE()")->fetchColumn();
            echo '<div class="status info">Database Name: ' . htmlspecialchars($dbname) . '</div>';

        } catch (Exception $e) {
            echo '<div class="status error">✗ Database Connection: FAILED</div>';
            echo '<div class="status error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $overall_status = 'ERROR';
            $results['db_connection'] = 'FAILED';
        }
        ?>

        <?php
        // ========================================
        // 3. REQUIRED TABLES CHECK
        // ========================================
        if (isset($pdo)) {
            ?>
            <h2>📋 Database Tables</h2>
            <?php
            $required_tables = ['tenants', 'users', 'rooms', 'bookings', 'guests', 'transactions'];
            $table_status = [];

            foreach ($required_tables as $table) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        // Get row count
                        $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                        $count = $count_stmt->fetchColumn();
                        echo '<div class="status ok">✓ Table `' . $table . '` exists (' . $count . ' rows)</div>';
                        $table_status[$table] = 'OK';
                    } else {
                        echo '<div class="status error">✗ Table `' . $table . '` NOT FOUND</div>';
                        $table_status[$table] = 'MISSING';
                        $overall_status = 'ERROR';
                    }
                } catch (Exception $e) {
                    echo '<div class="status error">✗ Error checking `' . $table . '`: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $table_status[$table] = 'ERROR';
                    $overall_status = 'ERROR';
                }
            }
        }
        ?>

        <?php
        // ========================================
        // 4. SESSION CHECK
        // ========================================
        ?>
        <h2>🔐 Session Status</h2>
        <?php
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo '<div class="status ok">✓ Session: ACTIVE</div>';
            echo '<div class="status info">Session ID: ' . session_id() . '</div>';

            if (!empty($_SESSION)) {
                echo '<div class="status info">Session Variables:</div>';
                echo '<pre>' . print_r($_SESSION, true) . '</pre>';
            } else {
                echo '<div class="status warning">⚠ Session is active but empty (no user logged in)</div>';
            }
        } else {
            session_start();
            if (session_status() === PHP_SESSION_ACTIVE) {
                echo '<div class="status ok">✓ Session: Started Successfully</div>';
            } else {
                echo '<div class="status error">✗ Session: FAILED TO START</div>';
                $overall_status = 'ERROR';
            }
        }
        ?>

        <?php
        // ========================================
        // 5. FILE PERMISSIONS
        // ========================================
        ?>
        <h2>📁 File Permissions</h2>
        <?php
        $files_to_check = [
            __DIR__ . '/../config/db_connect.php',
            __DIR__ . '/api_login.php',
            __DIR__ . '/index.php',
        ];

        foreach ($files_to_check as $file) {
            $basename = basename($file);
            if (file_exists($file)) {
                $perms = substr(sprintf('%o', fileperms($file)), -4);
                $readable = is_readable($file) ? 'Readable' : 'NOT Readable';
                echo '<div class="status ok">✓ ' . $basename . ' - Permissions: ' . $perms . ' (' . $readable . ')</div>';
            } else {
                echo '<div class="status error">✗ ' . $basename . ' - FILE NOT FOUND</div>';
                $overall_status = 'WARNING';
            }
        }
        ?>

        <?php
        // ========================================
        // 6. PHP EXTENSIONS
        // ========================================
        ?>
        <h2>🔧 PHP Extensions</h2>
        <?php
        $required_extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                echo '<div class="status ok">✓ ' . $ext . ': Loaded</div>';
            } else {
                echo '<div class="status error">✗ ' . $ext . ': NOT LOADED</div>';
                $overall_status = 'ERROR';
            }
        }
        ?>

        <?php
        // ========================================
        // OVERALL STATUS
        // ========================================
        ?>
        <h2>🎯 Overall System Status</h2>
        <?php
        if ($overall_status === 'OK') {
            echo '<div class="status ok" style="font-size: 1.2em; font-weight: bold;">✓ ALL SYSTEMS OPERATIONAL</div>';
        } elseif ($overall_status === 'WARNING') {
            echo '<div class="status warning" style="font-size: 1.2em; font-weight: bold;">⚠ SYSTEM OPERATIONAL WITH WARNINGS</div>';
        } else {
            echo '<div class="status error" style="font-size: 1.2em; font-weight: bold;">✗ CRITICAL ERRORS DETECTED</div>';
        }
        ?>

        <div class="timestamp">
            Generated at: <?= date('Y-m-d H:i:s T') ?><br>
            <strong>⚠️ SECURITY WARNING: Delete this file in production!</strong>
        </div>
    </div>
</body>

</html>