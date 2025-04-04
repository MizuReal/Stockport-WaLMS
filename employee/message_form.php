<?php
session_start();
include '../server/database.php';
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_id = $_SESSION['employeeID'];
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $status = 'unread';
    
    $sql = "INSERT INTO messages (employee_id, subject, message, status, timestamp) 
            VALUES ('$employee_id', '$subject', '$message', '$status', NOW())";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success");
        exit();
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=error&message=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

// Get status messages from URL parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$error_message = isset($_GET['message']) ? $_GET['message'] : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <title>Admin Support</title>
    <style>
        /* Content wrapper that adapts to sidebar state */
        .main-content {
            transition: margin-left 0.3s ease;
            padding: 20px;
            margin-left: 55px; /* Default for collapsed sidebar */
        }
        
        body.sidebar-expanded .main-content {
            margin-left: 220px; /* For expanded sidebar */
        }
        
        body.sidebar-hidden .main-content {
            margin-left: 20px; /* For fully hidden sidebar */
        }
        
        .message-form-container {
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .btn-submit {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-submit:hover {
            background-color: #45a049;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        
        .alert-danger {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }

        /* Table styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th, 
        .data-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .data-table tr:hover {
            background-color: #f1f1f1;
        }
        
        .text-warning {
            color: #ff9800;
        }
        
        .text-info {
            color: #2196F3;
        }
        
        .text-success {
            color: #4CAF50;
        }
        
        /* Custom header that adapts to sidebar state */
        .page-header {
            padding: 15px 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
    </style>
</head>
<body>
    <?php renderSidebar('message_form'); ?>

    <div class="main-content">
            <?php renderHeader('Admin Support'); ?>

        
        <div class="message-form-container">
            <h2>Submit Support Request</h2>
            
            <?php if ($status === 'success'): ?>
                <div class="alert alert-success">
                    Your message has been sent successfully!
                </div>
            <?php endif; ?>
            
            <?php if ($status === 'error'): ?>
                <div class="alert alert-danger">
                    Error: <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea class="form-control" id="message" name="message" required></textarea>
                </div>
                
                <button type="submit" class="btn-submit">Send Message</button>
            </form>
        </div>
        
        <div class="message-form-container">
            <h2>Your Previous Messages</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch user's previous messages
                    $employee_id = $_SESSION['employeeID']; // Corrected to match your session variable
                    $sql = "SELECT * FROM messages WHERE employee_id = '$employee_id' ORDER BY timestamp DESC";
                    $result = mysqli_query($conn, $sql);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
                            echo "<td>" . htmlspecialchars(substr($row['message'], 0, 50)) . (strlen($row['message']) > 50 ? "..." : "") . "</td>";
                            
                            // Format status with color
                            $status_class = '';
                            switch($row['status']) {
                                case 'unread':
                                    $status_class = 'text-warning';
                                    break;
                                case 'read':
                                    $status_class = 'text-info';
                                    break;
                                case 'resolved':
                                    $status_class = 'text-success';
                                    break;
                            }
                            
                            echo "<td class='{$status_class}'>" . ucfirst(htmlspecialchars($row['status'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['timestamp']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No messages found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>