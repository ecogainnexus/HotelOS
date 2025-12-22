<?php
/**
 * public/api_login.php
 * Public accessible login endpoint.
 */
session_start();

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
    // 1. Verify Tenant
    // SCHEMA UPDATE: 
    // - status column is 'is_active' (tinyint 1)
    // - name column is 'business_name'
    $stmt = $pdo->prepare("SELECT id, business_name FROM tenants WHERE subdomain_slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$hotel_code]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        if ($hotel_code === 'DEMO') {
            $tenant = ['id' => 1, 'business_name' => 'Demo Hotel (Virtual)'];
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid Hotel Code or Account Inactive.']);
            exit;
        }
    }

    $tenantName = $tenant['business_name']; // Correct column now

    // 2. Verify User
    // DEBUG BYPASS: Remove in Production
    if ($email === 'admin@hotelos.in' && $password === 'admin123') {
        $_SESSION['user_id'] = 999;
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['role'] = 'owner';
        $_SESSION['user_name'] = 'Super Admin';
        $_SESSION['hotel_name'] = $tenantName;
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
        $_SESSION['hotel_name'] = $tenantName;
        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Email or Password.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>