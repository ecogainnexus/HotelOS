<?php
/**
 * auth/login_logic.php
 * 
 * Handles the server-side validation and session creation.
 */

session_start();
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

// Basic Input Sanitization
$hotel_code = filter_input(INPUT_POST, 'hotel_code', FILTER_SANITIZE_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$hotel_code || !$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

try {
    // 1. Verify Tenant (Hotel Code)
    // 2. Verify User (Email & Password)
    // For now, this is a placeholder response

    // Simulate Success
    // $_SESSION['user_id'] = 1;
    // $_SESSION['role'] = 'owner';

    echo json_encode(['status' => 'success', 'message' => 'Login logic not yet fully implemented', 'debug_info' => compact('hotel_code', 'email')]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error']);
}
