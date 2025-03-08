<?php
// Start session
session_start();

// Clear remember me cookies if they exist
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('remember_type', '', time() - 3600, '/');
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>