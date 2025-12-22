<?php
/**
 * public/api_login.php
 * Public accessible login endpoint.
 */
session_start();

// Include DB Connect from the config folder (one level up)
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

$hotel_code = $_POST['hotel_code'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$hotel_code || !$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

try {
    // 1. Verify Tenant (Hotel Code)
    $stmt = $pdo->prepare("SELECT id, hotel_name FROM tenants WHERE subdomain_slug = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$hotel_code]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        // Fallback for FIRST TIME LOGIN (If DB is empty/Demo)
        if ($hotel_code === 'DEMO') {
            // Fake tenant for testing
            $tenant = ['id' => 1, 'hotel_name' => 'Demo Hotel'];
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid Hotel Code.']);
            exit;
        }
    }

    // 2. Verify User
    // For now, allow the hardcoded admin login for testing
    if ($email === 'admin@hotelos.in' && $password === 'admin123') {
        $_SESSION['user_id'] = 999;
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['role'] = 'owner';
        $_SESSION['user_name'] = 'Super Admin';
        $_SESSION['hotel_name'] = $tenant['hotel_name'];
        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
        exit;
    }

    // Real DB Verification
    $stmt = $pdo->prepare("SELECT id, password_hash, role, full_name FROM users WHERE email = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$email, $tenant['id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['hotel_name'] = $tenant['hotel_name'];
        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Email or Password.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>