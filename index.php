<?php
// Start session
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$userType = $_SESSION['user_type'];
$userId = $_SESSION['user_id'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Redirect to the appropriate dashboard based on user type
if ($userType === 'admin') {
    header("Location: admin/dashboard.php");
} elseif ($userType === 'teacher') {
    header("Location: teacher/dashboard.php");
} elseif ($userType === 'student') {
    header("Location: student/dashboard.php");
} else {
    // Invalid user type, logout and redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php");
}
exit();
?>