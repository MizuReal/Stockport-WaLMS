<?php
include 'server/database.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['CustomerName'];
    $phone = $_POST['Phone'];
    $email = $_POST['Email'];
    $address = $_POST['Address'];
    $password = $_POST['Password'];
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO customers (CustomerName, Phone, Email, Address, Password, customer_status) 
              VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $name, $phone, $email, $address, $hashed_password);
    
    if ($stmt->execute()) {
        $success_message = "Registration is on pending status. Please Kindly wait for approval.";
    } else {
        $error_message = "Registration failed";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://fonts.googleapis.com/css?family=Poppins:300,400,500,600' rel='stylesheet'>
</head>
<style>
body, html {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 2rem;
    box-sizing: border-box;
}

.form-container {
    width: 100%;
    max-width: 500px;
    padding: 2rem;
    background-color: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
}

h2 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    text-align: center;
    color: #2c3e50;
    font-size: 1.8rem;
}

.form-group {
    margin-bottom: 1.2rem;
}

label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #34495e;
    font-size: 0.9rem;
}

input[type="text"],
input[type="tel"],
input[type="email"],
input[type="password"],
textarea {
    padding: 0.8rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    width: 100%;
    box-sizing: border-box;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    color: #2c3e50;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-color: #fff;
    margin: 0;
    padding-left: 0.8rem !important;
    text-indent: 0;
}

/* Remove browser styles for autofill */
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
    -webkit-text-fill-color: #2c3e50;
    -webkit-box-shadow: 0 0 0px 1000px #fff inset;
    transition: background-color 5000s ease-in-out 0s;
}

/* Reset Chrome and Safari specific styles */
input[type="email"]::-webkit-textfield-decoration-container,
input[type="password"]::-webkit-textfield-decoration-container {
    content: none;
    display: none;
}

input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
textarea:-webkit-autofill,
textarea:-webkit-autofill:hover,
textarea:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0px 1000px white inset;
    box-shadow: 0 0 0px 1000px white inset;
    transition: background-color 5000s ease-in-out 0s;
}

input[type="text"]:focus,
input[type="tel"]:focus,
input[type="email"]:focus,
input[type="password"]:focus,
textarea:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

textarea {
    height: 80px;
    resize: none;
}

.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.button {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
    flex: 1;
    text-align: center;
}

.secondary {
    background-color: #e9ecef;
    color: #495057;
    margin-top: 0;
}

.secondary:hover {
    background-color: #dee2e6;
    transform: translateY(-1px);
}

button[type="submit"] {
    background-color: #2c3e50;
    color: white;
    flex: 1;
}

button[type="submit"]:hover {
    background-color: #34495e;
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
}

.alert-success {
    background-color: #d4f5e9;
    color: #0d6832;
    border: 1px solid #a3e4c9;
}

.alert-danger {
    background-color: #ffe3e3;
    color: #c92a2a;
    border: 1px solid #ffa8a8;
}
</style>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Customer Registration</h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="CustomerName">Name</label>
                    <input type="text" id="CustomerName" name="CustomerName" required>
                </div>
                
                <div class="form-group">
                    <label for="Phone">Phone</label>
                    <input type="tel" id="Phone" name="Phone" required>
                </div>
                
                <div class="form-group">
                    <label for="Email">Email</label>
                    <input type="email" id="Email" name="Email" required>
                </div>
                
                <div class="form-group">
                    <label for="Password">Password</label>
                    <input type="password" id="Password" name="Password" required>
                </div>
                
                <div class="form-group">
                    <label for="Address">Address</label>
                    <textarea id="Address" name="Address" required></textarea>
                </div>
                
                <div class="button-group">
                    <a href="index.php" class="button secondary">Back</a>
                    <button type="submit">Register</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>