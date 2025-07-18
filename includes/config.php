<?php
// includes/config.php

// Application settings
define('APP_NAME', 'CrimeAlert');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/crimealert');
define('APP_TIMEZONE', 'UTC');

// --- SMTP Mailer Configuration ---
define('SMTP_HOST', 'smtp.gmail.com');          // Your SMTP server (e.g., for Gmail)
define('SMTP_USERNAME', 'mshomik69@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'vird mguc gims apwd'); // The App Password you generated
define('SMTP_PORT', 587);                        // Port for TLS (587) or SSL (465)
define('SMTP_SECURE', 'tls');                    // Use 'tls' or 'ssl'

// Email address from which notifications will be sent
define('MAIL_FROM_ADDRESS', 'no-reply@yourdomain.com');
define('MAIL_FROM_NAME', 'CrimeAlert Notifications');


// Security settings
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_TIMEOUT', 900); // 15 minutes in seconds

// Set default timezone
date_default_timezone_set(APP_TIMEZONE);

// Start session with secure settings
session_set_cookie_params([
    'lifetime' => 86400, // 1 day
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
?>