<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
$status_message = '';
$show_modal = false;

// Check if there's a login error from session
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear the error after displaying
}

// Check for error parameter in URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_approved':
            $error_message = 'Your account is pending approval. Please contact customer support.';
            break;
        case 'invalid_customer':
            $error_message = 'Invalid customer account. Please contact support.';
            break;
    }
}

// Process login form if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection
    require_once('server/database.php');

    // Get user input
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate input
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required';
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("
            SELECT CustomerID, CustomerName, Email, Password, customer_status 
            FROM customers 
            WHERE Email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $row['Password'])) {
                // Store user data in session - using the consistent naming from session_check.php
                $_SESSION['CustomerID'] = $row['CustomerID'];
                $_SESSION['customer_name'] = $row['CustomerName'];
                $_SESSION['customer_email'] = $row['Email'];
                $_SESSION['customer_status'] = $row['customer_status'];
                
                // Check customer status
                if ($row['customer_status'] === 'approved') {
                    // Redirect to the intended page if set, otherwise to home
                    $redirect = isset($_SESSION['customer_redirect_after_login']) 
                        ? $_SESSION['customer_redirect_after_login'] 
                        : 'customer/home.php';
                    unset($_SESSION['customer_redirect_after_login']);
                    
                    header("Location: $redirect");
                    exit();
                } else if ($row['customer_status'] === 'pending') {
                    $show_modal = true;
                    $status_message = 'Your account approval is still pending. Do you want to continue logging in?';
                } else if ($row['customer_status'] === 'rejected') {
                    // Clear the session for rejected accounts
                    session_destroy();
                    session_start();
                    $error_message = 'Your account has been rejected. Please contact support for assistance.';
                }
            } else {
                $error_message = 'Invalid email or password';
            }
        } else {
            $error_message = 'Invalid email or password';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
        }
        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        button {
            background-color: #4a6fa5;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.75rem;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
        }
        button:hover {
            background-color: #3a5a8a;
        }
        .error-message {
            color: #d9534f;
            margin-top: 1rem;
            text-align: center;
        }
        .links {
            text-align: center;
            margin-top: 1rem;
        }
        .links a {
            color: #4a6fa5;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .modal {
            display: <?php echo $show_modal ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 400px;
            text-align: center;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .modal-buttons button {
            width: auto;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Customer Login</h1>
        <form id="loginForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <div class="links">
                <a href="customer-apply.php">Create an account</a> | 
                <a href="forgot-password.php">Forgot password?</a>
            </div>
        </form>
    </div>

    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h2>Account Status</h2>
            <p><?php echo htmlspecialchars($status_message); ?></p>
            <div class="modal-buttons">
                <form method="post" action="customer/home.php">
                    <button type="submit" id="continueBtn">Continue</button>
                </form>
                <button id="cancelBtn" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
            // Clear the session when user cancels
            fetch('customer/clear-session.php')
                .then(response => {
                    if (response.ok) {
                        window.location.href = 'customer-login.php';
                    }
                });
        }
    </script>
</body>
</html>