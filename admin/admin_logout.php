<?php
// Start the session
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Clear "Remember Me" cookies if they exist
if (isset($_COOKIE['employee_email'])) {
    setcookie('employee_email', '', time() - 3600, "/");
}

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, "/");
}

// Redirect to the admin login page
header("Location: ../admin-login.php");
exit(); // Ensure no further code is executed after the redirect
?>