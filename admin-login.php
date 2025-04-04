<?php
session_start();
include('server/database.php');

// Redirect if already logged in
if (isset($_SESSION['employeeID']) && !empty($_SESSION['employeeID']) && $_SESSION['employee_role'] === 'Admin') {
    header('Location: admin/dashboard.php');
    exit();
}

// Define regex for email validation
$emailRegex = "/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Validate email format
        if (!preg_match($emailRegex, $email)) {
            $error = "Invalid email format";
        } else {
            // Query the database for admin employees
            $stmt = $conn->prepare("
                SELECT EmployeeID, FirstName, LastName, Role, employeePassword, Status, employeeEmail
                FROM employees
                WHERE employeeEmail = ? AND Role = 'Admin'
            ");
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                
                // Check if account is active
                if ($admin['Status'] === 'Inactive') {
                    $error = "Your account is currently inactive. Please contact your administrator.";
                }
                // Verify password
                elseif (password_verify($password, $admin['employeePassword'])) {
                    // Set session variables
                    $_SESSION['employeeID'] = $admin['EmployeeID'];
                    $_SESSION['employee_name'] = $admin['FirstName'] . ' ' . $admin['LastName'];
                    $_SESSION['employee_email'] = $admin['employeeEmail'];
                    $_SESSION['employee_role'] = $admin['Role'];
                    $_SESSION['employee_status'] = $admin['Status'];
                    
                    // Redirect to dashboard or stored URL
                    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'admin/dashboard.php';
                    unset($_SESSION['redirect_after_login']); // Clear the stored URL
                    
                    header("Location: $redirect");
                    exit();
                } else {
                    $error = "Incorrect password";
                }
            } else {
                $error = "No admin account found with that email";
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
    <title>Stockport - Admin Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #2c3338;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .split-form {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: flex;
        }

        .image-side {
            background-color: #f8f9fa;
            padding: 40px;
        }

        .form-side {
            background-color: #ffffff;
            padding: 40px;
        }

        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .form-side input[type="email"],
        .form-side input[type="password"] {
            color: #333;
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            width: 100%;
            border-radius: 4px;
        }

        .form-side input::placeholder {
            color: #999;
        }
    </style>
</head>
<body>
    <div class="split-form">
        <div class="image-side">
            <h2>Admin Login Page</h2>
            <p>Please enter your credentials.</p>
        </div>
        <div class="form-side">
            <h2>Sign In</h2>
            <?php if(!empty($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn mt-3">Login</button>
            </form>
            <div class="form-links">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>
        </div>
    </div>
</body>
</html>