<?php
/**
 * auth/login_logic.php
 * Handles user authentication.
 */
session_start();
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
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
        echo json_encode(['status' => 'error', 'message' => 'Invalid Hotel Code or Account Inactive.']);
        exit;
    }

    // 2. Verify User
    // Note: In production v1 legacy, passwords might be MD5 or BCRYPT. 
    // For v2 fresh start, we assume BCRYPT. If legacy data, might need adjustment.
    // Let's assume the user already exists.
    $stmt = $pdo->prepare("SELECT id, password_hash, role, full_name FROM users WHERE email = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$email, $tenant['id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Success
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['hotel_name'] = $tenant['hotel_name'];

        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
    } else {
        // Fallback for demo/testing if DB is empty or hash mismatch
        // REMOVE THIS IN PRODUCTION
        if ($email === 'admin@hotelos.in' && $password === 'admin123') {
            $_SESSION['user_id'] = 999;
            $_SESSION['user_name'] = 'Super Admin';
            echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Invalid Email or Password.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>