<?php
/**
 * public/api_login.php
 * HotelOS Enterprise - Secure Login Endpoint
 * ENHANCED: Detailed Error Logging & Granular Responses
 */

// Error Reporting for Development (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to user, log them instead
ini_set('log_errors', 1);

session_start();

require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

// Log function for debugging
function logDebug($message)
{
    error_log("[LOGIN DEBUG] " . date('Y-m-d H:i:s') . " - " . $message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logDebug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

// Sanitize and collect inputs
$hotel_code = trim($_POST['hotel_code'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? ''; // Don't trim passwords

logDebug("Login attempt - Hotel: $hotel_code, Email: $email");

if (!$hotel_code || !$email || !$password) {
    logDebug("Missing fields - Hotel: " . ($hotel_code ? 'OK' : 'MISSING') . ", Email: " . ($email ? 'OK' : 'MISSING') . ", Password: " . ($password ? 'OK' : 'MISSING'));
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

try {
    // STEP 1: Verify Tenant (Hotel Code)
    $stmt = $pdo->prepare("SELECT id, business_name FROM tenants WHERE subdomain_slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$hotel_code]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        // Demo Fallback
        if (strtoupper($hotel_code) === 'DEMO') {
            logDebug("Using DEMO tenant bypass");
            $tenant = ['id' => 1, 'business_name' => 'Demo Hotel (Virtual)'];
        } else {
            logDebug("Hotel code not found or inactive: $hotel_code");
            echo json_encode(['status' => 'error', 'message' => 'Hotel Code not found. Please verify your Hotel Code.']);
            exit;
        }
    }

    $tenantId = $tenant['id'];
    $tenantName = $tenant['business_name'];
    logDebug("Tenant verified - ID: $tenantId, Name: $tenantName");

    // STEP 2: Debug Bypass (REMOVE IN PRODUCTION)
    if ($email === 'admin@hotelos.in' && $password === 'admin123') {
        logDebug("DEBUG BYPASS: Super Admin Login");
        $_SESSION['user_id'] = 999;
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['role'] = 'owner';
        $_SESSION['user_name'] = 'Super Admin';
        $_SESSION['hotel_name'] = $tenantName;
        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
        exit;
    }

    // STEP 3: Verify User in Database
    $stmt = $pdo->prepare("SELECT id, password_hash, role, name FROM users WHERE email = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$email, $tenantId]);
    $user = $stmt->fetch();

    if (!$user) {
        logDebug("User not found - Email: $email, Tenant: $tenantId");
        // Generic message to prevent email enumeration
        echo json_encode(['status' => 'error', 'message' => 'Invalid Email or Password.']);
        exit;
    }

    logDebug("User found - ID: {$user['id']}, Role: {$user['role']}, Name: {$user['name']}");

    // STEP 4: Verify Password
    if (password_verify($password, $user['password_hash'])) {
        logDebug("Password verified successfully for user: {$user['id']}");

        // Set Session Variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['hotel_name'] = $tenantName;

        logDebug("Login successful - Session created for user: {$user['id']}");
        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
    } else {
        logDebug("Password verification failed for user: {$user['id']}");
        // Generic message to prevent account enumeration
        echo json_encode(['status' => 'error', 'message' => 'Invalid Email or Password.']);
    }

} catch (PDOException $e) {
    logDebug("Database Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please try again later.']);
} catch (Exception $e) {
    logDebug("System Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'System error occurred. Please contact support.']);
}
?>