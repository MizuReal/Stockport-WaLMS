<?php
$current_page = basename($_SERVER['PHP_SELF']);
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// Fetch warehouse information
$query = "SELECT 
    productLocationID,
    productWarehouse,
    Section,
    Capacity
    FROM products_warehouse
    ORDER BY productLocationID";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create new warehouse
        if ($_POST['action'] === 'create') {
            $warehouseName = mysqli_real_escape_string($conn, $_POST['productWarehouse']);
            $section = mysqli_real_escape_string($conn, $_POST['Section']);
            $capacity = (int)$_POST['Capacity'];
            
            $query = "INSERT INTO products_warehouse (productWarehouse, Section, Capacity, warehouse_weight_unit) 
                      VALUES ('$warehouseName', '$section', $capacity, 'kg')";
            
            if (mysqli_query($conn, $query)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=added");
                exit();
            } else {
                $error = "Error adding warehouse: " . mysqli_error($conn);
            }
        }
        
        // Update existing warehouse
        if ($_POST['action'] === 'update') {
            $warehouseId = (int)$_POST['productLocationID'];
            $warehouseName = mysqli_real_escape_string($conn, $_POST['productWarehouse']);
            $section = mysqli_real_escape_string($conn, $_POST['Section']);
            $capacity = (int)$_POST['Capacity'];
            
            $query = "UPDATE products_warehouse 
                      SET productWarehouse = '$warehouseName', 
                          Section = '$section', 
                          Capacity = $capacity 
                      WHERE productLocationID = $warehouseId";
            
            if (mysqli_query($conn, $query)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=updated");
                exit();
            } else {
                $error = "Error updating warehouse: " . mysqli_error($conn);
            }
        }
        
        // Delete warehouse
        if ($_POST['action'] === 'delete') {
            $warehouseId = (int)$_POST['productLocationID'];
            
            $query = "DELETE FROM products_warehouse WHERE productLocationID = $warehouseId";
            
            if (mysqli_query($conn, $query)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=deleted");
                exit();
            } else {
                $error = "Error deleting warehouse: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Warehouse Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Main container styles */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background-color: #f0f2f5;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        /* Sidebar styles */
        .sidebar {
            width: 250px;
            background-color: #1a1c2e;
            color: #fff;
            position: fixed;
            height: 100%;
            left: 0;
            top: 0;
            padding-top: 20px;
        }

        .sidebar a {
            padding: 15px 20px;
            text-decoration: none;
            color: #fff;
            display: block;
            transition: all 0.3s;
        }

        .sidebar a:hover {
            background-color: #2f3042;
        }

        .sidebar a.active {
            background-color: #ff7f50;
        }

        .sidebar i {
            margin-right: 10px;
        }

        /* Header styles */
        .warehouse-header {
            margin-bottom: 20px;
            background-color: #ff7f50;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
        }

        .warehouse-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        /* Adjusted action button container */
        .action-button-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-add {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }

        .btn-add:hover {
            background-color: #45a049;
        }

        /* Table styles */
        .warehouse-table-container {
            overflow-x: auto;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        }

        .warehouse-table {
            width: 100%;
            border-collapse: collapse;
        }

        .warehouse-table th {
            background-color: #f8a030;
            color: white;
            text-align: left;
            padding: 15px;
            font-weight: 500;
        }

        .warehouse-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .warehouse-table tr:hover {
            background-color: #f5f5f5;
        }

        .warehouse-table tr:last-child td {
            border-bottom: none;
        }

        /* Action buttons styles */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-edit, .btn-delete {
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            color: white;
        }

        .btn-edit {
            background-color: #2196F3;
        }

        .btn-edit:hover {
            background-color: #0b7dda;
        }

        .btn-delete {
            background-color: #f44336;
        }

        .btn-delete:hover {
            background-color: #da190b;
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
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            width: 50%;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: modalFade 0.3s;
        }

        @keyframes modalFade {
            from {transform: translateY(-30px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }

        .modal-header {
            padding: 15px 20px;
            background-color: #ff7f50;
            color: white;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #f0f0f0;
        }

        .form-group {
            margin: 15px 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel, .btn-save {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-cancel {
            background-color: #f1f1f1;
            color: #333;
        }

        .btn-save {
            background-color: #4CAF50;
            color: white;
        }

        .btn-save:hover {
            background-color: #45a049;
        }

        /* Alert messages */
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

        /* No records message */
        .no-records {
            text-align: center;
            padding: 20px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="warehouse-header">
                <h1><i class="fas fa-warehouse"></i> Product Warehouse</h1>
            </div>
            
            <div class="action-button-container">
                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Warehouse
                </button>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                        switch($_GET['success']) {
                            case 'added':
                                echo 'Warehouse added successfully.';
                                break;
                            case 'updated':
                                echo 'Warehouse updated successfully.';
                                break;
                            case 'deleted':
                                echo 'Warehouse deleted successfully.';
                                break;
                        }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="content">
                <div class="warehouse-table-container">
                    <table class="warehouse-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Warehouse Location</th>
                                <th>Section</th>
                                <th>Capacity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($row['productWarehouse']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Section']); ?></td>
                                        <td><?php echo number_format($row['Capacity']); ?></td>
                                        <td class="action-buttons">
                                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete" onclick="confirmDelete(<?php echo $row['productLocationID']; ?>, '<?php echo htmlspecialchars($row['productWarehouse']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-records">No warehouses found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Warehouse Modal -->
            <div id="addWarehouseModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Add New Warehouse</h2>
                        <span class="close" onclick="closeAddModal()">&times;</span>
                    </div>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label for="productWarehouse">Warehouse Location</label>
                            <input type="text" id="productWarehouse" name="productWarehouse" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="Section">Section</label>
                            <input type="text" id="Section" name="Section" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="Capacity">Capacity</label>
                            <input type="number" id="Capacity" name="Capacity" min="0" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                            <button type="submit" class="btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Warehouse Modal -->
            <div id="editWarehouseModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Edit Warehouse</h2>
                        <span class="close" onclick="closeEditModal()">&times;</span>
                    </div>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" id="edit_productLocationID" name="productLocationID">
                        
                        <div class="form-group">
                            <label for="edit_productWarehouse">Warehouse Location</label>
                            <input type="text" id="edit_productWarehouse" name="productWarehouse" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_Section">Section</label>
                            <input type="text" id="edit_Section" name="Section" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_Capacity">Capacity</label>
                            <input type="number" id="edit_Capacity" name="Capacity" min="0" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                            <button type="submit" class="btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Form -->
            <form id="deleteForm" action="" method="POST" style="display:none;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_productLocationID" name="productLocationID">
            </form>
        </div>
    </div>

    <script>
        // Functions for modal operation
        function openAddModal() {
            document.getElementById('addWarehouseModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addWarehouseModal').style.display = 'none';
        }
        
        function openEditModal(warehouse) {
            // Populate form fields
            document.getElementById('edit_productLocationID').value = warehouse.productLocationID;
            document.getElementById('edit_productWarehouse').value = warehouse.productWarehouse;
            document.getElementById('edit_Section').value = warehouse.Section;
            document.getElementById('edit_Capacity').value = warehouse.Capacity;
            
            document.getElementById('editWarehouseModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editWarehouseModal').style.display = 'none';
        }
        
        function confirmDelete(warehouseID, warehouseName) {
            if (confirm('Are you sure you want to delete the warehouse: ' + warehouseName + '?')) {
                document.getElementById('delete_productLocationID').value = warehouseID;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == document.getElementById('addWarehouseModal')) {
                closeAddModal();
            }
            if (event.target == document.getElementById('editWarehouseModal')) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>