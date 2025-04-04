<?php
// Start session if one doesn't exist already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();


// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $stmt = $conn->prepare("UPDATE employees SET Status = ? WHERE EmployeeID = ?");
        $stmt->bind_param("si", $_POST['status'], $_POST['employee_id']);
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(Exception $e) {
        echo "Error updating status: " . $e->getMessage();
    }
}

// Handle employee deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM employees WHERE EmployeeID = ?");
        $stmt->bind_param("i", $_POST['employee_id']);
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(Exception $e) {
        echo "Error deleting employee: " . $e->getMessage();
    }
}

// Handle employee update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    try {
        $stmt = $conn->prepare("UPDATE employees SET 
                                FirstName = ?, 
                                LastName = ?, 
                                employeeEmail = ?, 
                                Phone = ?, 
                                Role = ?, 
                                HireDate = ?, 
                                Status = ? 
                                WHERE EmployeeID = ?");
        
        $stmt->bind_param("sssssssi", 
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['role'],
            $_POST['hire_date'],
            $_POST['status'],
            $_POST['employee_id']
        );
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(Exception $e) {
        echo "Error updating employee: " . $e->getMessage();
    }
}

// Handle new employee creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO employees (FirstName, LastName, employeeEmail, Phone, Role, HireDate, Status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssss", 
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['role'],
            $_POST['hire_date'],
            $_POST['status']
        );
        $stmt->execute();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(Exception $e) {
        echo "Error adding employee: " . $e->getMessage();
    }
}

// Fetch employee data for edit form
$edit_employee = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE EmployeeID = ?");
        $stmt->bind_param("i", $_GET['edit_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_employee = $result->fetch_assoc();
    } catch(Exception $e) {
        echo "Error fetching employee data: " . $e->getMessage();
    }
}

// Fetch employees
try {
    $result = $conn->query("SELECT * FROM employees ORDER BY EmployeeID");
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
    die();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Status - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1><i class="fas fa-user-tie"></i> Employee Status</h1>
            </header>
            <div class="content">
                <div class="add-employee-button">
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Staff
                    </button>
                </div>
                <div class="employee-status-table-container">
                    <table class="employee-status-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Hire Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['EmployeeID']); ?></td>
                                <td><?php echo htmlspecialchars($employee['FirstName'] . ' ' . $employee['LastName']); ?></td>
                                <td><?php echo htmlspecialchars($employee['employeeEmail']); ?></td>
                                <td><?php echo htmlspecialchars($employee['Phone']); ?></td>
                                <td><?php echo htmlspecialchars($employee['Role']); ?></td>
                                <td><?php echo htmlspecialchars($employee['HireDate']); ?></td>
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="employee_id" value="<?php echo $employee['EmployeeID']; ?>">
                                        <select name="status" class="status-select" 
                                                onchange="this.form.querySelector('.btn-save').style.display = 'inline-block';">
                                            <option value="Active" <?php echo ($employee['Status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>
                                                Active
                                            </option>
                                            <option value="Inactive" <?php echo ($employee['Status'] ?? 'Active') === 'Inactive' ? 'selected' : ''; ?>>
                                                Inactive
                                            </option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-save" style="display: none;">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                    </form>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($employee)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                        <input type="hidden" name="employee_id" value="<?php echo $employee['EmployeeID']; ?>">
                                        <button type="submit" name="delete_employee" class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Employee</h2>
            <form id="editForm" method="POST">
                <input type="hidden" name="employee_id" id="edit_employee_id">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="Admin">Admin</option>
                        <option value="Employee">Employee</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="hire_date">Hire Date</label>
                    <input type="date" id="hire_date" name="hire_date" required>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="btn-container">
                    <button type="submit" name="edit_employee" class="btn-update">Update Employee</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add New Employee</h2>
            <form id="addForm" method="POST">
                <div class="form-group">
                    <label for="add_first_name">First Name</label>
                    <input type="text" id="add_first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="add_last_name">Last Name</label>
                    <input type="text" id="add_last_name" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="add_email">Email</label>
                    <input type="email" id="add_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="add_phone">Phone</label>
                    <input type="text" id="add_phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="add_role">Role</label>
                    <select id="add_role" name="role" required>
                        <option value="Admin">Admin</option>
                        <option value="Employee">Employee</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_hire_date">Hire Date</label>
                    <input type="date" id="add_hire_date" name="hire_date" required>
                </div>
                <div class="form-group">
                    <label for="add_status">Status</label>
                    <select id="add_status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="btn-container">
                    <button type="submit" name="add_employee" class="btn-update">Add Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        const modal = document.getElementById('editModal');
        const form = document.getElementById('editForm');
        
        function openEditModal(employee) {
            // Populate form fields
            document.getElementById('edit_employee_id').value = employee.EmployeeID;
            document.getElementById('first_name').value = employee.FirstName;
            document.getElementById('last_name').value = employee.LastName;
            document.getElementById('email').value = employee.employeeEmail;
            document.getElementById('phone').value = employee.Phone;
            document.getElementById('role').value = employee.Role;
            document.getElementById('hire_date').value = employee.HireDate;
            document.getElementById('status').value = employee.Status || 'Active';
            
            // Show modal
            modal.style.display = 'block';
        }
        
        function closeEditModal() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeEditModal();
            }
        }

        // Add new modal functions
        const addModal = document.getElementById('addModal');
        
        function openAddModal() {
            // Set default hire date to today
            document.getElementById('add_hire_date').value = new Date().toISOString().split('T')[0];
            addModal.style.display = 'block';
        }
        
        function closeAddModal() {
            addModal.style.display = 'none';
        }
        
        // Update window click handler
        window.onclick = function(event) {
            if (event.target == modal) {
                closeEditModal();
            }
            if (event.target == addModal) {
                closeAddModal();
            }
        }
    </script>
</body>
</html>