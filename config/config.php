<?php
/**
 * config/config.php
 * Global configuration for HotelOS
 */

// Application Base URL (no trailing slash)
define('APP_BASE_URL', '/HotelOS');

// Full base URL for absolute redirects
define('APP_URL', 'https://needkit.in' . APP_BASE_URL);

// Login page URL (for redirects)
define('LOGIN_URL', APP_BASE_URL . '/');

// Environment
define('APP_ENV', 'production'); // development | production

// Error reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>