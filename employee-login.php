<?php
session_start();
include('server/database.php');

// Redirect if already logged in
if (isset($_SESSION['employeeID']) && !empty($_SESSION['employeeID'])) {
    header('Location: employee/inventory.php');
    exit();
}

// Define regex for email validation
$emailRegex = "/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['logemail']) && isset($_POST['logpass'])) {
        $email = trim($_POST['logemail']);
        $password = $_POST['logpass'];

        // Validate email format
        if (!preg_match($emailRegex, $email)) {
            $error = "Invalid email format";
        } else {
            // Query the database
            $stmt = $conn->prepare("
                SELECT EmployeeID, FirstName, LastName, Role, employeePassword, Status
                FROM employees
                WHERE employeeEmail = ?
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $employee = $result->fetch_assoc();

                // Check if account is active
                if ($employee['Status'] === 'Inactive') {
                    $error = "Your account is currently inactive. Please contact your administrator.";
                }
                // Verify password
                elseif (password_verify($password, $employee['employeePassword'])) {
                    // Set session variables
                    $_SESSION['employeeID'] = $employee['EmployeeID'];
                    $_SESSION['employee_name'] = $employee['FirstName'] . ' ' . $employee['LastName'];
                    $_SESSION['employee_email'] = $email;
                    $_SESSION['employee_role'] = $employee['Role'];
                    $_SESSION['employee_status'] = $employee['Status'];

                    // Redirect to inventory page
                    header('Location: employee/overview.php');
                    exit();
                } else {
                    $error = "Incorrect password";
                }
            } else {
                $error = "No employee found with that email";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockport - Employee Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h2>Stockport Management</h2>
                <p>Employee Login</p>
            </div>

            <?php if (!empty($error)) { ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php } ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="logemail"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="logemail" id="logemail" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="logpass"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="logpass" id="logpass" placeholder="Enter your password" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="login-btn">Login</button>
                </div>

                <div class="form-links">
                    <a href="forgot-password.php">Forgot Password?</a>
                    <a href="index.php" class="go-back"><i class="fas fa-arrow-left"></i>Go back to home page.</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>