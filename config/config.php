<?php
// Application Configuration

// Define base URL for the project - update this if your folder name is different
define('BASE_URL', 'http://localhost/freelance-flow/');

// Define absolute path to the root directory
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Error reporting - disable in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setting
date_default_timezone_set('Asia/Karachi');

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'izhansajid847@gmail.com');
define('SMTP_PASS', 'ysiitahttkjvqepy');
define('SMTP_PORT', 587);
define('MAIL_FROM', 'izhansajid847@gmail.com');
define('MAIL_FROM_NAME', 'FreelanceFlow');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
