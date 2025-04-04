<?php
// Only start session if one doesn't exist already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

$current_page = basename($_SERVER['PHP_SELF']);

// Handle status updates
if (isset($_POST['update_status'])) {
    $messageID = $_POST['messageID'];
    $newStatus = $_POST['status'];
    
    $updateQuery = "UPDATE messages SET status = ? WHERE messageID = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $newStatus, $messageID);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating status: " . $conn->error;
    }
    
    // Redirect to avoid form resubmission
    header("Location: support_requests.php");
    exit();
}

// Handle mark as read when viewing message (AJAX request)
if (isset($_POST['mark_as_read']) && isset($_POST['id'])) {
    $messageID = $_POST['id'];
    
    // Only update if currently unread
    $updateQuery = "UPDATE messages SET status = 'read' WHERE messageID = ? AND status = 'unread'";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $messageID);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    
    exit(); // End script execution for AJAX request
}

// Handle message deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $messageID = $_GET['id'];
    
    $deleteQuery = "DELETE FROM messages WHERE messageID = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $messageID);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Support request deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting request: " . $conn->error;
    }
    
    // Redirect to avoid resubmission
    header("Location: support_requests.php");
    exit();
}

// Get all support requests with employee information
// Get all support requests with employee information
$query = "SELECT m.*, e.FirstName, e.LastName 
          FROM messages m 
          JOIN employees e ON m.employee_id = e.EmployeeID 
          ORDER BY 
            CASE 
                WHEN m.status = 'unread' THEN 1
                WHEN m.status = 'read' THEN 2
                WHEN m.status = 'resolved' THEN 3
            END, 
            m.timestamp DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Requests - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .message-container {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            height: 180px;
            overflow: hidden;
            position: relative;
        }
        .message-container:hover {
            background-color: #f9f9f9;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .message-subject {
            font-weight: bold;
            font-size: 1.1em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }
        .message-meta {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        .message-content {
            line-height: 1.5;
            max-height: 80px;
            overflow: hidden;
            position: relative;
        }
        .message-content-truncated::after {
            content: "...";
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: white;
            padding: 4px;
        }
        .message-actions {
            display: none; /* Hide actions in list view, they'll be shown in modal */
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-unread {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-read {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-resolved {
            background-color: #d4edda;
            color: #155724;
        }
        .messages-controls {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .filter-options select {
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .status-select {
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 70%;
            max-width: 800px;
            animation: modalopen 0.3s;
        }
        @keyframes modalopen {
            from {opacity: 0; transform: scale(0.8);}
            to {opacity: 1; transform: scale(1);}
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }
        .close:hover {
            color: black;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .modal-title {
            font-size: 1.4em;
            font-weight: bold;
        }
        .modal-body {
            margin-bottom: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .modal-footer {
            border-top: 1px solid #eee;
            padding-top: 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Support Requests</h1>
            </header>

            <div class="content">
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="messages-controls">
                    <div class="filter-options">
                        <select id="status-filter">
                            <option value="all">All Requests</option>
                            <option value="unread">Unread</option>
                            <option value="read">In Progress</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    <div class="search-box">
                        <input type="text" id="search-input" placeholder="Search requests...">
                    </div>
                </div>

                <?php if ($result && $result->num_rows > 0): ?>
                    <div id="messages-list">
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="message-container" data-status="<?php echo $row['status']; ?>" data-id="<?php echo $row['messageID']; ?>">
                                <div class="message-header">
                                    <div class="message-subject"><?php echo htmlspecialchars($row['subject']); ?></div>
                                    <span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span>
                                </div>
                                <div class="message-meta">
                                    From: <?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?><br>
                                    Date: <?php echo date('F j, Y, g:i a', strtotime($row['timestamp'])); ?>
                                </div>
                                <div class="message-content message-content-truncated">
                                    <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 15px;"></i>
                        <h3>No support requests found</h3>
                        <p>When employees submit support requests, they will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modal-subject"></div>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modal-meta" class="message-meta"></div>
                <div id="modal-content" style="margin-top: 15px;"></div>
            </div>
            <div class="modal-footer">
                <form id="status-form" method="post" action="">
                    <input type="hidden" id="modal-message-id" name="messageID" value="">
                    <select name="status" class="status-select">
                        <option value="unread">Unread</option>
                        <option value="read">In Progress</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <input type="hidden" name="update_status" value="1">
                    <button type="submit" class="status-btn">Update Status</button>
                </form>
                <a id="delete-link" href="#" class="delete-btn">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>

    <script>
        // Get the modal
        const modal = document.getElementById("messageModal");
        const modalSubject = document.getElementById("modal-subject");
        const modalMeta = document.getElementById("modal-meta");
        const modalContent = document.getElementById("modal-content");
        const modalMessageId = document.getElementById("modal-message-id");
        const statusSelect = document.querySelector(".status-select");
        const deleteLink = document.getElementById("delete-link");
        const messages = document.querySelectorAll('.message-container');
        const close = document.getElementsByClassName("close")[0];
        
        // Add click event to all message containers
        messages.forEach(function(message) {
            message.addEventListener('click', function() {
                const messageId = this.getAttribute('data-id');
                const status = this.getAttribute('data-status');
                const subject = this.querySelector('.message-subject').textContent;
                const meta = this.querySelector('.message-meta').innerHTML;
                const content = this.querySelector('.message-content').innerHTML;
                
                // Populate modal
                modalSubject.textContent = subject;
                modalMeta.innerHTML = meta;
                modalContent.innerHTML = content;
                modalMessageId.value = messageId;
                statusSelect.value = status;
                deleteLink.href = "support_requests.php?delete=1&id=" + messageId;
                
                // Show modal
                modal.style.display = "block";
                
                // Mark as read if it's unread
                if (status === 'unread') {
                    markAsRead(messageId);
                }
            });
        });
        
        // Mark message as read function
        function markAsRead(messageId) {
            // Create form data
            const formData = new FormData();
            formData.append('mark_as_read', '1');
            formData.append('id', messageId);
            
            // Send AJAX request
            fetch('support_requests.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data === 'success') {
                    // Update UI to show message is read
                    const msgElement = document.querySelector(`.message-container[data-id="${messageId}"]`);
                    msgElement.setAttribute('data-status', 'read');
                    const statusBadge = msgElement.querySelector('.status-badge');
                    statusBadge.textContent = 'read';
                    statusBadge.className = 'status-badge status-read';
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Close modal when clicking X
        close.onclick = function() {
            modal.style.display = "none";
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Delete confirmation
        deleteLink.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this request?')) {
                e.preventDefault();
            }
        });

        // Status filter functionality
        document.getElementById('status-filter').addEventListener('change', function() {
            const selectedStatus = this.value;
            
            messages.forEach(function(message) {
                if (selectedStatus === 'all' || message.getAttribute('data-status') === selectedStatus) {
                    message.style.display = 'block';
                } else {
                    message.style.display = 'none';
                }
            });
        });

        // Search functionality
        document.getElementById('search-input').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            
            messages.forEach(function(message) {
                const subject = message.querySelector('.message-subject').textContent.toLowerCase();
                const content = message.querySelector('.message-content').textContent.toLowerCase();
                const meta = message.querySelector('.message-meta').textContent.toLowerCase();
                
                if (subject.includes(searchTerm) || content.includes(searchTerm) || meta.includes(searchTerm)) {
                    message.style.display = 'block';
                } else {
                    message.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>