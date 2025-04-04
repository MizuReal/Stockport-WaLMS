<?php
session_start(); // Start the session

// Unset all session variables related to customer
unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_email']);

// Destroy the session
session_destroy();

// Clear any session cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Redirect to the login page
header('Location: ../customer-login.php');
exit();
?>
