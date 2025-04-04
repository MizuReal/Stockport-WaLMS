<?php
// Start the session if it isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Function to check if the user is logged in
 * Returns true if logged in, false otherwise
 */
function isLoggedIn() {
    // Check if 'employeeID' is set in the session and is not empty
    return isset($_SESSION['employeeID']) && !empty($_SESSION['employeeID']);
}

/**
 * Function to redirect to the login page if not logged in
 * Use this at the beginning of restricted pages
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Store the requested URL for redirection after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to the login page
        header("Location: ../employee-login.php");
        exit;
    }
}

/**
 * Function to check if the user has a specific role
 * Valid roles include: 'Admin', 'Employee'
 * Returns true if the user has the role, false otherwise
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }

    // Check if the user's role matches the required role
    return isset($_SESSION['employee_role']) && $_SESSION['employee_role'] === $role;
}

/**
 * Function to check if the employee's account is active
 * Returns true if active, false otherwise
 */
function isActive() {
    if (!isLoggedIn()) {
        return false;
    }

    return isset($_SESSION['employee_status']) && $_SESSION['employee_status'] === 'Active';
}

/**
 * Function to require that a user is both logged in and has an active account
 * Use this at the beginning of restricted pages to ensure only active accounts can access
 */
function requireActiveLogin() {
    requireLogin(); // First, check if the user is logged in

    // Then, check if the account is active
    if (!isActive()) {
        // If not active, log them out and redirect to the login page with an error message
        session_destroy();
        session_start();
        $_SESSION['login_error'] = "Your account is currently inactive. Please contact your administrator.";
        header("Location: ../employee-login.php");
        exit;
    }
}

/**
 * Function to restrict access to admin-only pages
 */
function requireAdminAccess() {
    requireActiveLogin(); // First, ensure the user is logged in and active

    // Check if the user has the 'Admin' role
    if (!hasRole('Admin')) {
        header("Location: ../access-denied.php");
        exit;
    }
}

/**
 * Function to get the current employee's basic information from the session
 * Returns an associative array with employee details or null if not logged in
 */
function getCurrentEmployeeInfo() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'employeeID' => $_SESSION['employeeID'],
        'employee_name' => $_SESSION['employee_name'],
        'employee_email' => $_SESSION['employee_email'],
        'employee_role' => $_SESSION['employee_role'],
        'employee_status' => $_SESSION['employee_status']
    ];
}

/**
 * Function to get additional employee details from the database
 */
function getEmployeeDetails() {
    if (!isLoggedIn()) {
        return null;
    }

    // Include database connection
    include_once('../server/database.php');

    // Check if the connection is established
    if (!isset($conn) || $conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $employeeID = $_SESSION['employeeID'];

    // Prepare the SQL query
    $stmt = $conn->prepare("
        SELECT EmployeeID, FirstName, LastName, Role, Phone, employeeEmail, HireDate, Status
        FROM employees 
        WHERE EmployeeID = ?
    ");

    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }

    return null;
}

/**
 * Function to check if the session needs refreshing
 * This helps keep session data in sync with the database
 */
function refreshSessionIfNeeded() {
    if (!isLoggedIn()) {
        return;
    }

    // Include database connection
    include_once('../server/database.php');

    // Check if the connection is established
    if (!isset($conn) || $conn->connect_error) {
        return; // Don't fail if the database connection isn't available
    }

    $employeeID = $_SESSION['employeeID'];

    // Check if the employee still exists and get their current status/role
    $stmt = $conn->prepare("
        SELECT FirstName, LastName, Role, employeeEmail, Status
        FROM employees 
        WHERE EmployeeID = ?
    ");

    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $employee = $result->fetch_assoc();

        // Update the session if database values have changed
        if ($_SESSION['employee_role'] !== $employee['Role'] || 
            $_SESSION['employee_status'] !== $employee['Status']) {

            $_SESSION['employee_name'] = $employee['FirstName'] . ' ' . $employee['LastName'];
            $_SESSION['employee_email'] = $employee['employeeEmail'];
            $_SESSION['employee_role'] = $employee['Role'];
            $_SESSION['employee_status'] = $employee['Status'];
        }
    } else {
        // Employee no longer exists in the database, log them out
        session_destroy();
        header("Location: ../employee-login.php");
        exit;
    }
}