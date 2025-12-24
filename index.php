<?php
/**
 * Root Entry Point - HotelOS
 * 
 * Simple redirect to the public application folder.
 * This ensures all paths are correctly resolved for assets and APIs.
 */

// Redirect to public folder
header('Location: public/');
exit;
?>