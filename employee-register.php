<?php
session_start();
include('server/database.php');

// Define regex for validation
$emailRegex = "/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";
$passwordRegex = "/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/"; // At least 8 characters, 1 letter, and 1 number

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['firstname'], $_POST['lastname'], $_POST['phone'], $_POST['employeeEmail'], $_POST['employeePassword'])) {
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $role = 'Employee'; // Set default role as Employee
        $phone = $_POST['phone'];
        $email = $_POST['employeeEmail'];
        $password = $_POST['employeePassword'];
        $hireDate = date('Y-m-d'); // Current date as hire date

        // Validate input
        if (!preg_match($emailRegex, $email)) {
            $error = "Invalid email format";
        } elseif (!preg_match($passwordRegex, $password)) {
            $error = "Password must be at least 8 characters long and contain at least one letter and one number";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT * FROM employees WHERE employeeEmail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email is already registered. Please use a different email address.";
            } else {
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new employee
                $stmt = $conn->prepare("INSERT INTO employees (FirstName, LastName, Role, Phone, employeeEmail, employeePassword, HireDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $firstname, $lastname, $role, $phone, $email, $hashedPassword, $hireDate);
                
                if ($stmt->execute()) {
                    $success = "Registration successful! You can now <a href='employee-login.php'>login</a> with your credentials.";
                } else {
                    $error = "Error registering: " . $conn->error;
                }
            }
            $stmt->close();
        }
    } else {
        $error = "All fields are required";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockport - Employee Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <h2>Stockport Management</h2>
                <p>Employee Registration</p>
            </div>
            
            <?php if (!empty($error)) { ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php } ?>

            <?php if (!empty($success)) { ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php } ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstname"><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="firstname" id="firstname" placeholder="Enter your first name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastname"><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="lastname" id="lastname" placeholder="Enter your last name" required>
                    </div>
                </div>
                
                <div class="form-group" style="display: none;">
                    <label for="role"><i class="fas fa-id-badge"></i> Role</label>
                    <input type="hidden" name="role" id="role" value="Employee">
                </div>
                
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="text" name="phone" id="phone" placeholder="Enter your phone number" required>
                </div>
                
                <div class="form-group">
                    <label for="employeeEmail"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="employeeEmail" id="employeeEmail" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label for="employeePassword"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="employeePassword" id="employeePassword" placeholder="Create a password" required>
                    <small class="password-hint">Password must be at least 8 characters with at least one letter and one number</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="register-btn">Register</button>
                </div>

                <!-- Move login link below register button -->
                <div class="login-link" style="text-align: center; margin-top: 10px;">
                    <a href="employee-login.php">Already have an account? Login here</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

