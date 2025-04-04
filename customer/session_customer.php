<?php
// Start the session if it isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Function to check if the customer is logged in
 * Returns true if logged in, false otherwise
 */
function isCustomerLoggedIn() {
    return isset($_SESSION['CustomerID']) && !empty($_SESSION['CustomerID']);
}

/**
 * Function to redirect to the customer login page if not logged in
 * Use this at the beginning of customer-restricted pages
 */
function requireCustomerLogin() {
    if (!isCustomerLoggedIn()) {
        // Store the requested URL for redirection after login
        $_SESSION['customer_redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to the customer login page
        header("Location: ../customer-login.php");
        exit;
    }
}

/**
 * Function to check if the customer's account is approved
 * Returns true if approved, false otherwise
 */
function isCustomerApproved() {
    if (!isCustomerLoggedIn()) {
        return false;
    }

    return isset($_SESSION['customer_status']) && $_SESSION['customer_status'] === 'approved';
}

/**
 * Function to require that a customer is both logged in and has an approved account
 * Use this at the beginning of customer-restricted pages
 */
function requireApprovedCustomerLogin() {
    requireCustomerLogin(); // First, check if the customer is logged in

    // Then, check if the account is approved
    if (!isCustomerApproved()) {
        // If not approved, log them out and redirect to login with error
        session_destroy();
        session_start();
        $_SESSION['login_error'] = "Your account is pending approval. Please contact customer support.";
        header("Location: ../customer-login.php?error=not_approved");
        exit;
    }
}

/**
 * Function to get the current customer's basic information from the session
 * Returns an associative array with customer details or null if not logged in
 */
function getCurrentCustomerInfo() {
    if (!isCustomerLoggedIn()) {
        return null;
    }

    return [
        'CustomerID' => $_SESSION['CustomerID'],
        'customer_name' => $_SESSION['customer_name'],
        'customer_email' => $_SESSION['customer_email'],
        'customer_status' => $_SESSION['customer_status']
    ];
}

/**
 * Function to get additional customer details from the database
 */
function getCustomerDetails() {
    if (!isCustomerLoggedIn()) {
        return null;
    }

    // Include database connection
    require_once('../server/database.php');

    // Check if the connection is established
    if (!isset($conn) || $conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $customerID = $_SESSION['CustomerID'];

    // Prepare the SQL query
    $stmt = $conn->prepare("
        SELECT CustomerID, FirstName, LastName, Email, Phone, Address, City, State, ZipCode, customer_status
        FROM customers 
        WHERE CustomerID = ?
    ");

    $stmt->bind_param("i", $customerID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }

    return null;
}

/**
 * Function to check if the customer session needs refreshing
 * This helps keep session data in sync with the database
 */
function refreshCustomerSessionIfNeeded() {
    if (!isCustomerLoggedIn()) {
        return;
    }

    // Include database connection
    require_once('../server/database.php');

    // Check if the connection is established
    if (!isset($conn) || $conn->connect_error) {
        return; // Don't fail if the database connection isn't available
    }

    $customerID = $_SESSION['CustomerID'];

    // Check if the customer still exists and get their current status
    $stmt = $conn->prepare("
        SELECT FirstName, LastName, Email, customer_status
        FROM customers 
        WHERE CustomerID = ?
    ");

    $stmt->bind_param("i", $customerID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $customer = $result->fetch_assoc();

        // Update the session if database values have changed
        if ($_SESSION['customer_status'] !== $customer['customer_status']) {
            $_SESSION['customer_name'] = $customer['FirstName'] . ' ' . $customer['LastName'];
            $_SESSION['customer_email'] = $customer['Email'];
            $_SESSION['customer_status'] = $customer['customer_status'];
        }
    } else {
        // Customer no longer exists in the database, log them out
        session_destroy();
        header("Location: ../customer-login.php?error=invalid_customer");
        exit;
    }
}

/**
 * Main function to check customer login and status
 * This is a wrapper function that combines several checks
 */
function checkCustomerLogin() {
    requireCustomerLogin();
    refreshCustomerSessionIfNeeded();
    requireApprovedCustomerLogin();
}