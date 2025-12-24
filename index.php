<?php
/**
 * Root Entry Point - HotelOS
 * 
 * This file should NOT be accessed directly.
 * The .htaccess rewrites all requests to public/ folder internally.
 * 
 * If you see this file executing, .htaccess is not working.
 */

// Define application base URL for use across the application
define('BASE_URL', '/HotelOS');

// Fallback redirect if .htaccess fails
header('Location: ' . BASE_URL . '/public/index.php');
exit;
?>