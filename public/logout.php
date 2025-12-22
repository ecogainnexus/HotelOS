<?php
/**
 * public/logout.php
 * Destroys session and redirects to login
 */
session_start();
session_destroy();
header('Location: index.php');
exit;
?>