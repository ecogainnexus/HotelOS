<?php
/**
 * public/logout.php
 * Destroys session and redirects to login
 */
require_once __DIR__ . '/../config/config.php';
session_start();
session_destroy();
header('Location: ' . LOGIN_URL);
exit;
?>