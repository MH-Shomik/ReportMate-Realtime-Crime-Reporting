<?php
// includes/functions.php

// --- PHPMailer Imports ---
// Add these lines at the top of your functions.php file.
// This assumes you installed PHPMailer via Composer.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to another page
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login for protected pages
 */
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message system
 */
function flash($name = '', $message = '', $class = 'alert alert-success') {
    if (!empty($name)) {
        if (!empty($message) && empty($_SESSION[$name])) {
            $_SESSION[$name] = $message;
            $_SESSION[$name . '_class'] = $class;
        } elseif (empty($message) && !empty($_SESSION[$name])) {
            $class = !empty($_SESSION[$name . '_class']) ? $_SESSION[$name . '_class'] : '';
            echo '<div class="' . $class . '" id="msg-flash">' . $_SESSION[$name] . '</div>';
            unset($_SESSION[$name]);
            unset($_SESSION[$name . '_class']);
        }
    }
}



/**
 * Fetches users who have an alert zone covering a specific location.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param float $lat The latitude of the new crime report.
 * @param float $lng The longitude of the new crime report.
 * @param int $exclude_user_id The ID of the user who reported the crime.
 * @return array A list of users to be notified.
 */
function get_users_in_alert_zones($pdo, $lat, $lng, $exclude_user_id) {
    $stmt = $pdo->prepare("
        SELECT u.email, u.username
        FROM alert_zones az
        JOIN users u ON az.user_id = u.id
        WHERE u.id != ? AND (
            6371 * acos(
                cos(radians(?)) * cos(radians(az.latitude)) *
                cos(radians(az.longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(az.latitude))
            )
        ) < az.radius_km
    ");
    $stmt->execute([$exclude_user_id, $lat, $lng, $lat]);
    return $stmt->fetchAll();
}


/**
 * Fetches users from the database within a given radius of a location.
 */
function get_nearby_users($pdo, $lat, $lng, $radius_km, $exclude_user_id) {
    $stmt = $pdo->prepare("
        SELECT email, username FROM users WHERE id != ? AND (
            6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )
        ) < ?
    ");
    $stmt->execute([$exclude_user_id, $lat, $lng, $lat, $radius_km]);
    return $stmt->fetchAll();
}

/**
 * Sends a crime notification email to a user using PHPMailer.
 *
 * @param string $to_email The recipient's email address.
 * @param string $user_name The recipient's name or username.
 * @param array $crime_data An associative array with crime details.
 * @return bool True on success, false on failure.
 */
function send_crime_notification_email($to_email, $user_name, $crime_data) {
    $mail = new PHPMailer(true);

    try {
        // --- Server settings ---
        // $mail->SMTPDebug = 2; // Enable verbose debug output for testing
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // --- Recipients ---
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $user_name);

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = "CrimeAlert: A new incident has been reported near you";
        
        $greeting_name = !empty($user_name) ? htmlspecialchars($user_name) : 'there';

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <p>Hello {$greeting_name},</p>
                <p>A new crime has been reported in your vicinity. Please stay alert and be aware of your surroundings.</p>
                <h3 style='color: #c0392b;'>Incident Details:</h3>
                <ul style='list-style-type: none; padding-left: 0;'>
                    <li><strong>Title:</strong> " . htmlspecialchars($crime_data['title']) . "</li>
                    <li><strong>Type:</strong> " . htmlspecialchars($crime_data['crime_type']) . "</li>
                    <li><strong>Description:</strong><br>" . nl2br(htmlspecialchars($crime_data['description'])) . "</li>
                </ul>
                <p>Thank you for being a part of the CrimeAlert community.</p>
                <p><strong>- The CrimeAlert Team</strong></p>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hello {$greeting_name},\nA new crime has been reported near you. Title: " . $crime_data['title'] . ". Description: " . $crime_data['description'];

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for debugging. Don't show detailed errors to the end-user.
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    }
    
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute'
    ];

    foreach ($intervals as $secs => $str) {
        $d = $diff / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Password strength checker
 */
function is_password_strong($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match("#[0-9]+#", $password)) {
        $errors[] = "Password must include at least one number";
    }
    if (!preg_match("#[a-zA-Z]+#", $password)) {
        $errors[] = "Password must include at least one letter";
    }
    return $errors;
}
?>
