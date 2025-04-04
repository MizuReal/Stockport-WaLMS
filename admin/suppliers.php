<?php
// Only start session if one doesn't exist already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

$current_page = basename($_SERVER['PHP_SELF']);

// Error and success message handling
$errorMsg = "";
$successMsg = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                addSupplier($conn);
                break;
            case 'edit':
                editSupplier($conn);
                break;
            case 'delete':
                deleteSupplier($conn);
                break;
        }
    }
}

// Function to add a new supplier
function addSupplier($conn) {
    global $errorMsg, $successMsg;
    
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate inputs
    if (empty($name) || empty($contact) || empty($phone) || empty($email) || empty($address)) {
        $errorMsg = "All fields are required for adding a supplier.";
        return;
    }

    $sql = "INSERT INTO suppliers (SupplierName, ContactPerson, Phone, Email, Address) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("Error preparing statement (add): " . $conn->error);
        $errorMsg = "Database error: " . htmlspecialchars($conn->error);
        return;
    }

    $stmt->bind_param("sssss", $name, $contact, $phone, $email, $address);

    if ($stmt->execute()) {
        $successMsg = "Supplier added successfully!";
    } else {
        error_log("Error executing statement (add): " . $stmt->error);
        $errorMsg = "Error adding supplier: " . htmlspecialchars($stmt->error);
    }

    $stmt->close();
}

// Function to edit a supplier
function editSupplier($conn) {
    global $errorMsg, $successMsg;
    
    $id = $_POST['supplierid'];
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate inputs
    if (empty($id) || empty($name) || empty($contact) || empty($phone) || empty($email) || empty($address)) {
        $errorMsg = "All fields are required for editing a supplier.";
        return;
    }

    $sql = "UPDATE suppliers SET SupplierName=?, ContactPerson=?, Phone=?, Email=?, Address=? WHERE SupplierID=?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("Error preparing statement (edit): " . $conn->error);
        $errorMsg = "Database error: " . htmlspecialchars($conn->error);
        return;
    }

    $stmt->bind_param("sssssi", $name, $contact, $phone, $email, $address, $id);

    if ($stmt->execute()) {
        $successMsg = "Supplier updated successfully!";
    } else {
        error_log("Error executing statement (edit): " . $stmt->error);
        $errorMsg = "Error updating supplier: " . htmlspecialchars($stmt->error);
    }

    $stmt->close();
}

// Function to delete a supplier
function deleteSupplier($conn) {
    global $errorMsg, $successMsg;
    
    if (isset($_POST['supplierid']) && !empty($_POST['supplierid'])) {
        $id = $_POST['supplierid'];

        $sql = "DELETE FROM suppliers WHERE SupplierID=?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log("Error preparing statement (delete): " . $conn->error);
            $errorMsg = "Database error: " . htmlspecialchars($conn->error);
            return;
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $successMsg = "Supplier deleted successfully!";
        } else {
            error_log("Error executing statement (delete): " . $stmt->error);
            $errorMsg = "Error deleting supplier: " . htmlspecialchars($stmt->error);
        }

        $stmt->close();
    } else {
        $errorMsg = "Supplier ID not provided for deletion.";
    }
}

// Retrieve all suppliers
$sql = "SELECT * FROM suppliers ORDER BY SupplierName ASC";
$result = $conn->query($sql);
$suppliers = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
       
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Supplier Management</h1>
            </header>

            <div class="content">
                <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $errorMsg; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $successMsg; ?>
                </div>
                <?php endif; ?>

                <button id="btnAddSupplier" class="btn-add-supplier">
                    <i class="fas fa-plus"></i> Add New Supplier
                </button>

                <div class="supplier-table-container">
                    <?php if (empty($suppliers)): ?>
                        <div style="padding: 20px; text-align: center; color: #666;">
                            <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <p>No suppliers found. Click "Add New Supplier" to create one.</p>
                        </div>
                    <?php else: ?>
                        <table class="supplier-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($supplier['SupplierName']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['ContactPerson']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['Phone']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['Address']); ?></td>
                                    <td>
                                        <button class="btn-edit" data-supplierid="<?php echo htmlspecialchars($supplier['SupplierID']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-delete" data-supplierid="<?php echo htmlspecialchars($supplier['SupplierID']); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Supplier Modal -->
    <div id="supplierModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add New Supplier</h2>
            <form id="supplierForm" class="supplier-form" method="post" action="suppliers.php">
                <input type="hidden" id="supplierId" name="supplierid" value="">
                <input type="hidden" id="formAction" name="action" value="add">

                <div class="form-group">
                    <label for="name">Supplier Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="contact">Contact Person</label>
                    <input type="text" id="contact" name="contact" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Submit</button>
                    <button type="button" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this supplier? This action cannot be undone.</p>
            <form method="post" action="suppliers.php" id="deleteForm">
                <input type="hidden" id="deleteId" name="supplierid" value="">
                <input type="hidden" name="action" value="delete">
                <div class="form-actions">
                    <button type="submit" class="btn-delete-confirm">Delete</button>
                    <button type="button" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals and buttons
            const deleteModal = document.getElementById('deleteModal');
            const supplierModal = document.getElementById('supplierModal');
            const deleteButtons = document.querySelectorAll('.btn-delete');
            const editButtons = document.querySelectorAll('.btn-edit');
            const supplierForm = document.getElementById('supplierForm');
            const addSupplierBtn = document.getElementById('btnAddSupplier');
            
            // Show success/error messages for 5 seconds then fade out
            const alertElements = document.querySelectorAll('.alert');
            if (alertElements.length > 0) {
                setTimeout(() => {
                    alertElements.forEach(alert => {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.style.display = 'none', 500);
                    });
                }, 5000);
            }

            // DELETE functionality
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const supplierId = this.dataset.supplierid;
                    document.getElementById('deleteId').value = supplierId;
                    deleteModal.style.display = 'block';
                });
            });

            // EDIT functionality
            editButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    document.getElementById('modalTitle').textContent = 'Edit Supplier';
                    document.getElementById('formAction').value = 'edit';

                    const supplierId = this.dataset.supplierid;
                    document.getElementById('supplierId').value = supplierId;

                    // Fetch supplier data based on ID from our data array
                    const suppliers = <?php echo json_encode($suppliers); ?>;
                    const supplier = suppliers.find(s => s.SupplierID == supplierId);

                    if (supplier) {
                        // Populate the modal's fields with the supplier data
                        document.getElementById('name').value = supplier.SupplierName;
                        document.getElementById('contact').value = supplier.ContactPerson;
                        document.getElementById('phone').value = supplier.Phone;
                        document.getElementById('email').value = supplier.Email;
                        document.getElementById('address').value = supplier.Address;
                        
                        supplierModal.style.display = 'block';
                    }
                });
            });

            // Add new supplier button
            addSupplierBtn.addEventListener('click', function() {
                document.getElementById('modalTitle').textContent = 'Add New Supplier';
                document.getElementById('formAction').value = 'add';
                document.getElementById('supplierId').value = '';
                supplierForm.reset();
                supplierModal.style.display = 'block';
            });

            // Input validation for phone number
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
            });

            // Close modals
            document.querySelectorAll('.close, .btn-cancel').forEach(closeBtn => {
                closeBtn.addEventListener('click', () => {
                    deleteModal.style.display = 'none';
                    supplierModal.style.display = 'none';
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
                if (event.target === supplierModal) {
                    supplierModal.style.display = 'none';
                }
            });

            // Form validation
            supplierForm.addEventListener('submit', function(event) {
                const email = document.getElementById('email').value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!emailRegex.test(email)) {
                    event.preventDefault();
                    alert('Please enter a valid email address');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>