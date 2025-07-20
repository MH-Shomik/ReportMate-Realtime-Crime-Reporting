<?php
// 1. Include the configuration file which should start the session.
require_once 'includes/config.php';

// 2. Unset all of the session variables.
//    This is a safer way to clear the session data.
$_SESSION = array();

// 3. Destroy the session cookie.
//    This will make sure the session is completely gone on the user's browser.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finally, destroy the session on the server.
session_destroy();

// 5. Redirect the user to the homepage.
//    The 'logged_out' parameter can be used to show a success message on the homepage.
header("Location: index.php?logged_out=true");
exit();
?>
