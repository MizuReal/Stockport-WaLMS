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
 * Function to check if the user is an admin
 * Returns true if admin, false otherwise
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Check if the user has the 'Admin' role
    return isset($_SESSION['employee_role']) && $_SESSION['employee_role'] === 'Admin';
}

/**
 * Function to check if the admin's account is active
 * Returns true if active, false otherwise
 */
function isActive() {
    if (!isLoggedIn()) {
        return false;
    }

    return isset($_SESSION['employee_status']) && $_SESSION['employee_status'] === 'Active';
}

/**
 * Function to redirect to the admin login page if not logged in
 * Use this at the beginning of admin pages
 */
function requireAdminLogin() {
    if (!isLoggedIn()) {
        // Store the requested URL for redirection after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to the admin login page
        header("Location: ../admin-login.php");
        exit;
    }
}

/**
 * Function to require admin access - checks login, admin role and active status
 * This is the function you're trying to call in your admin pages
 */
function requireAdminAccess() {
    requireAdminLogin(); // First, check if the user is logged in
    
    // Check if the user has the 'Admin' role
    if (!isAdmin()) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt to admin area by employee ID: " . $_SESSION['employeeID']);
        
        // Redirect to access denied page
        header("Location: ../access-denied.php");
        exit;
    }

    // Then, check if the account is active
    if (!isActive()) {
        // If not active, log them out and redirect to the login page with an error message
        session_destroy();
        session_start();
        $_SESSION['login_error'] = "Your account is currently inactive. Please contact the system administrator.";
        header("Location: ../admin-login.php");
        exit;
    }
}

/**
 * Function to require that a user is logged in, has an admin role, and has an active account
 * This is an alternative function name that does the same as requireAdminAccess
 */
function requireActiveAdminLogin() {
    requireAdminLogin(); // First, check if the user is logged in
    
    // Check if the user has the 'Admin' role
    if (!isAdmin()) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt to admin area by employee ID: " . $_SESSION['employeeID']);
        
        // Redirect to access denied page
        header("Location: ../access-denied.php");
        exit;
    }

    // Then, check if the account is active
    if (!isActive()) {
        // If not active, log them out and redirect to the login page with an error message
        session_destroy();
        session_start();
        $_SESSION['login_error'] = "Your account is currently inactive. Please contact the system administrator.";
        header("Location: ../admin-login.php");
        exit;
    }
}

/**
 * Function to get the current admin's information from the session
 * Returns an associative array with admin details or null if not admin
 */
function getCurrentAdminInfo() {
    if (!isLoggedIn() || !isAdmin()) {
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
 * Function to get detailed admin information from the database
 */
function getAdminDetails() {
    if (!isLoggedIn() || !isAdmin()) {
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
        WHERE EmployeeID = ? AND Role = 'Admin'
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
 * Function to refresh the admin session data from the database
 * Ensures session information stays updated with database changes
 */
function refreshAdminSessionIfNeeded() {
    if (!isLoggedIn() || !isAdmin()) {
        return;
    }

    // Include database connection
    include_once('../server/database.php');

    // Check if the connection is established
    if (!isset($conn) || $conn->connect_error) {
        return; // Don't fail if the database connection isn't available
    }

    $employeeID = $_SESSION['employeeID'];

    // Check if the admin still exists and get their current status
    $stmt = $conn->prepare("
        SELECT FirstName, LastName, Role, employeeEmail, Status
        FROM employees 
        WHERE EmployeeID = ? AND Role = 'Admin'
    ");

    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // Update the session if database values have changed
        if ($_SESSION['employee_role'] !== $admin['Role'] || 
            $_SESSION['employee_status'] !== $admin['Status']) {

            $_SESSION['employee_name'] = $admin['FirstName'] . ' ' . $admin['LastName'];
            $_SESSION['employee_email'] = $admin['employeeEmail'];
            $_SESSION['employee_role'] = $admin['Role'];
            $_SESSION['employee_status'] = $admin['Status'];
        }
        
        // If role is no longer 'Admin', redirect to access denied
        if ($admin['Role'] !== 'Admin') {
            header("Location: ../access-denied.php");
            exit;
        }
    } else {
        // Admin no longer exists in the database or role changed, log them out
        session_destroy();
        header("Location: ../admin-login.php");
        exit;
    }
}

/**
 * Function to log admin activities
 * Records important admin actions for security and auditing
 */
function logAdminActivity($action, $details = '') {
    if (!isLoggedIn() || !isAdmin()) {
        return false;
    }
    
    // Include database connection
    include_once('../server/database.php');
    
    // Check if the connection is established
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $employeeID = $_SESSION['employeeID'];
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    // Prepare the SQL query (assuming you have an admin_logs table)
    $stmt = $conn->prepare("
        INSERT INTO admin_logs (employeeID, action, details, timestamp, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("issss", $employeeID, $action, $details, $timestamp, $ipAddress);
    return $stmt->execute();
}