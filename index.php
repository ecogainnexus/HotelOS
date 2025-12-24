<?php
/**
 * Root Entry Point - HotelOS
 * 
 * Redirects to login page in public/ folder.
 * Uses absolute path to ensure correct subdirectory resolution.
 */

// Get the current directory path from the script location
$base = dirname($_SERVER['PHP_SELF']);

// Handle root directory edge case
if ($base === '/' || $base === '\\') {
    $base = '';
}

// Redirect to public/ within this application directory  
// Example: /HotelOS/index.php → /HotelOS/public/
header('Location: ' . $base . '/public/');
exit;
?>