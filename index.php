<?php
/**
 * Root Entry Point
 * 
 * Simple proxy to load the public application.
 * This fixes 403 errors on shared hosting where .htaccess rewrites fail.
 */

// Load the actual frontend
require_once __DIR__ . '/public/index.php';
?>