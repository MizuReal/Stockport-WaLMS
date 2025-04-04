<?php
$current_page = basename($_SERVER['PHP_SELF']);
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create new material
        if ($_POST['action'] === 'create') {
            $materialName = $_POST['materialName'];
            
            // Handle supplier - could be an ID or a new name
            if (is_numeric($_POST['supplierID']) && $_POST['supplierID'] > 0) {
                // Existing supplier selected
                $supplierID = $_POST['supplierID'];
            } else if (!empty($_POST['supplierName'])) {
                // New supplier name entered, create a new supplier
                $supplierName = $_POST['supplierName'];
                $insert_supplier = "INSERT INTO suppliers (SupplierName) VALUES (?)";
                $stmt = $conn->prepare($insert_supplier);
                $stmt->bind_param("s", $supplierName);
                $stmt->execute();
                $supplierID = $conn->insert_id;
                $stmt->close();
            } else {
                echo "<script>alert('Please select or enter a supplier.');</script>";
                exit;
            }
            
            $quantityInStock = $_POST['quantityInStock'];
            $unitCost = $_POST['unitCost'];
            $minimumStock = $_POST['minimumStock'];
            $raw_warehouse = $_POST['raw_warehouse'];
            
            // Image handling
            $image_name = '';
            if (isset($_FILES['raw_material_img']) && $_FILES['raw_material_img']['error'] === 0) {
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $file_name = $_FILES['raw_material_img']['name'];
                $file_size = $_FILES['raw_material_img']['size'];
                $file_tmp = $_FILES['raw_material_img']['tmp_name'];
                $file_type = $_FILES['raw_material_img']['type'];
                
                $file_ext_arr = explode('.', $file_name);
                $file_ext = strtolower(end($file_ext_arr));
                
                if (in_array($file_ext, $allowed_ext)) {
                    // Generate unique filename
                    $image_name = 'material_' . time() . '.' . $file_ext;
                    move_uploaded_file($file_tmp, "../assets/imgs/" . $image_name);
                } else {
                    echo "<script>alert('Invalid file extension. Only JPG, JPEG, PNG files are allowed.');</script>";
                    $image_name = '';
                }
            }
            
            $query = "INSERT INTO rawmaterials (MaterialName, SupplierID, QuantityInStock, UnitCost, LastRestockedDate, MinimumStock, raw_warehouse, raw_material_img) 
                     VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("siidiss", $materialName, $supplierID, $quantityInStock, $unitCost, $minimumStock, $raw_warehouse, $image_name);
            
            if ($stmt->execute()) {
                echo "<script>alert('Material added successfully!'); window.location.href='raw_materials.php';</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
        
        // Update material
        elseif ($_POST['action'] === 'update') {
            $materialID = $_POST['materialID'];
            $materialName = $_POST['materialName'];
            
            // Handle supplier - could be an ID or a new name
            if (is_numeric($_POST['supplierID']) && $_POST['supplierID'] > 0) {
                // Existing supplier selected
                $supplierID = $_POST['supplierID'];
            } else if (!empty($_POST['supplierName'])) {
                // New supplier name entered, create a new supplier
                $supplierName = $_POST['supplierName'];
                $insert_supplier = "INSERT INTO suppliers (SupplierName) VALUES (?)";
                $stmt = $conn->prepare($insert_supplier);
                $stmt->bind_param("s", $supplierName);
                $stmt->execute();
                $supplierID = $conn->insert_id;
                $stmt->close();
            } else {
                echo "<script>alert('Please select or enter a supplier.');</script>";
                exit;
            }
            
            $quantityInStock = $_POST['quantityInStock'];
            $unitCost = $_POST['unitCost'];
            $minimumStock = $_POST['minimumStock'];
            $raw_warehouse = $_POST['raw_warehouse'];
            
            // Check if there's a new image uploaded
            if (isset($_FILES['raw_material_img']) && $_FILES['raw_material_img']['error'] === 0) {
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $file_name = $_FILES['raw_material_img']['name'];
                $file_size = $_FILES['raw_material_img']['size'];
                $file_tmp = $_FILES['raw_material_img']['tmp_name'];
                
                $file_ext_arr = explode('.', $file_name);
                $file_ext = strtolower(end($file_ext_arr));
                
                if (in_array($file_ext, $allowed_ext)) {
                    // Get current image to delete
                    $get_img_query = "SELECT raw_material_img FROM rawmaterials WHERE MaterialID = ?";
                    $stmt = $conn->prepare($get_img_query);
                    $stmt->bind_param("i", $materialID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        if (!empty($row['raw_material_img'])) {
                            $old_image = "../assets/imgs/" . $row['raw_material_img'];
                            if (file_exists($old_image)) {
                                unlink($old_image);
                            }
                        }
                    }
                    
                    // Generate unique filename
                    $image_name = 'material_' . time() . '.' . $file_ext;
                    move_uploaded_file($file_tmp, "../assets/imgs/" . $image_name);
                    
                    // Update with new image
                    $query = "UPDATE rawmaterials SET MaterialName = ?, SupplierID = ?, QuantityInStock = ?, 
                             UnitCost = ?, MinimumStock = ?, raw_warehouse = ?, raw_material_img = ?, LastRestockedDate = NOW() 
                             WHERE MaterialID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("siidissi", $materialName, $supplierID, $quantityInStock, $unitCost, $minimumStock, $raw_warehouse, $image_name, $materialID);
                } else {
                    echo "<script>alert('Invalid file extension. Only JPG, JPEG, PNG files are allowed.');</script>";
                    return;
                }
            } else {
                // Update without changing image
                $query = "UPDATE rawmaterials SET MaterialName = ?, SupplierID = ?, QuantityInStock = ?, 
                         UnitCost = ?, MinimumStock = ?, raw_warehouse = ?, LastRestockedDate = NOW() 
                         WHERE MaterialID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("siidisi", $materialName, $supplierID, $quantityInStock, $unitCost, $minimumStock, $raw_warehouse, $materialID);
            }
            
            if ($stmt->execute()) {
                echo "<script>alert('Material updated successfully!'); window.location.href='raw_materials.php';</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
        
        // Delete material - now combines regular and force delete functionality
        elseif ($_POST['action'] === 'delete') {
            $materialID = $_POST['materialID'];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // First check if this material is referenced by any products
                $check_query = "SELECT COUNT(*) as product_count FROM products WHERE MaterialID = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("i", $materialID);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_row = $check_result->fetch_assoc();
                
                // Set MaterialID to NULL for all affected products if there are references
                if ($check_row['product_count'] > 0) {
                    $update_query = "UPDATE products SET MaterialID = NULL WHERE MaterialID = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("i", $materialID);
                    $update_stmt->execute();
                }
                
                // Get image filename to delete
                $get_img_query = "SELECT raw_material_img FROM rawmaterials WHERE MaterialID = ?";
                $img_stmt = $conn->prepare($get_img_query);
                $img_stmt->bind_param("i", $materialID);
                $img_stmt->execute();
                $img_result = $img_stmt->get_result();
                if ($img_row = $img_result->fetch_assoc()) {
                    if (!empty($img_row['raw_material_img'])) {
                        $old_image = "../assets/imgs/" . $img_row['raw_material_img'];
                        if (file_exists($old_image)) {
                            unlink($old_image);
                        }
                    }
                }
                
                // Now delete the material
                $delete_query = "DELETE FROM rawmaterials WHERE MaterialID = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("i", $materialID);
                $delete_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                if ($check_row['product_count'] > 0) {
                    echo "<script>alert('Material deleted successfully. The references have been removed from " . $check_row['product_count'] . " product(s).'); window.location.href='raw_materials.php';</script>";
                } else {
                    echo "<script>alert('Material deleted successfully!'); window.location.href='raw_materials.php';</script>";
                }
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='raw_materials.php';</script>";
            }
        }
    }
}

// Fetch all suppliers for dropdowns
$suppliers_query = "SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName";
$suppliers_result = $conn->query($suppliers_query);
$suppliers = [];
if ($suppliers_result && $suppliers_result->num_rows > 0) {
    while ($row = $suppliers_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Fetch all raw materials
$query = "SELECT r.*, s.SupplierName FROM rawmaterials r 
          LEFT JOIN suppliers s ON r.SupplierID = s.SupplierID 
          ORDER BY r.MaterialID";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Materials - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Add FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
       
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1><i class="fas fa-boxes"></i> Raw Materials</h1>
            </header>
            <div class="content">
                <!-- Add New Material Button -->
                <div class="action-button-container">
                    <button class="btn-add" onclick="openAddModal()">Add New Material</button>
                </div>
                
                <!-- Materials Table -->
                <div class="materials-table-container">
                    <table class="materials-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Material Name</th>
                                <th>Supplier</th>
                                <th>Warehouse</th>
                                <th>Quantity</th>
                                <th>Min Stock</th>
                                <th>Unit Cost</th>
                                <th>Last Restocked</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php $counter = 1; // Initialize row counter ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="row-number"><?php echo $counter++; ?></td>
                                        <td>
                                            <?php if (!empty($row['raw_material_img'])): ?>
                                                <img src="../assets/imgs/<?php echo $row['raw_material_img']; ?>" alt="<?php echo $row['MaterialName']; ?>" class="material-thumbnail">
                                            <?php else: ?>
                                                <span class="no-image">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['MaterialName']; ?></td>
                                        <td><?php echo $row['SupplierName']; ?></td>
                                        <td><?php echo $row['raw_warehouse']; ?></td>
                                        <td class="<?php echo ($row['QuantityInStock'] <= $row['MinimumStock']) ? 'low-stock' : ''; ?>">
                                            <?php echo $row['QuantityInStock']; ?>
                                        </td>
                                        <td><?php echo $row['MinimumStock']; ?></td>
                                        <td><?php echo number_format($row['UnitCost'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['LastRestockedDate'])); ?></td>
                                        <td class="action-buttons">
                                            <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                                            <button class="btn-delete" onclick="confirmDelete(<?php echo $row['MaterialID']; ?>, '<?php echo $row['MaterialName']; ?>')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-records">No materials found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Material Modal -->
                <div id="addMaterialModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeAddModal()">&times;</span>
                        <h2>Add New Material</h2>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="materialName">Material Name</label>
                                <input type="text" id="materialName" name="materialName" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Supplier</label>
                                <div class="supplier-container">
                                    <div class="supplier-selection">
                                        <select id="supplierID" name="supplierID">
                                            <option value="">Select Existing Supplier</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?php echo $supplier['SupplierID']; ?>"><?php echo $supplier['SupplierName']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span>OR</span>
                                    </div>
                                    <input type="text" id="supplierName" name="supplierName" class="supplier-input" placeholder="Enter New Supplier Name">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="raw_warehouse">Warehouse Location</label>
                                <input type="text" id="raw_warehouse" name="raw_warehouse" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantityInStock">Quantity In Stock</label>
                                <input type="number" id="quantityInStock" name="quantityInStock" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="minimumStock">Minimum Stock Level</label>
                                <input type="number" id="minimumStock" name="minimumStock" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="unitCost">Unit Cost</label>
                                <input type="number" id="unitCost" name="unitCost" min="0" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="raw_material_img">Material Image</label>
                                <input type="file" id="raw_material_img" name="raw_material_img" accept=".jpg,.jpeg,.png">
                                <div class="image-preview-container">
                                    <img id="imagePreview" class="image-preview" src="" alt="Image Preview">
                                </div>
                            </div>
                            
                            <div class="btn-container">
                                <button type="submit" class="btn-save">Save Material</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Edit Material Modal -->
                <div id="editMaterialModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeEditModal()">&times;</span>
                        <h2>Edit Material</h2>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" id="edit_materialID" name="materialID">
                            
                            <div class="form-group">
                                <label for="edit_materialName">Material Name</label>
                                <input type="text" id="edit_materialName" name="materialName" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Supplier</label>
                                <div class="supplier-container">
                                    <div class="supplier-selection">
                                        <select id="edit_supplierID" name="supplierID">
                                            <option value="">Select Existing Supplier</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?php echo $supplier['SupplierID']; ?>"><?php echo $supplier['SupplierName']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span>OR</span>
                                    </div>
                                    <input type="text" id="edit_supplierName" name="supplierName" class="supplier-input" placeholder="Enter New Supplier Name">
                                </div>
                            </div>
                            
                            <div class="form-group"><div class="form-group">
    <label for="edit_raw_warehouse">Warehouse Location</label>
    <input type="text" id="edit_raw_warehouse" name="raw_warehouse" required>
</div>

<div class="form-group">
    <label for="edit_quantityInStock">Quantity In Stock</label>
    <input type="number" id="edit_quantityInStock" name="quantityInStock" min="0" required>
</div>

<div class="form-group">
    <label for="edit_minimumStock">Minimum Stock Level</label>
    <input type="number" id="edit_minimumStock" name="minimumStock" min="0" required>
</div>

<div class="form-group">
    <label for="edit_unitCost">Unit Cost</label>
    <input type="number" id="edit_unitCost" name="unitCost" min="0" step="0.01" required>
</div>

<div class="form-group">
    <label for="edit_raw_material_img">Material Image</label>
    <input type="file" id="edit_raw_material_img" name="raw_material_img" accept=".jpg,.jpeg,.png">
    <div class="help-text">Leave empty to keep current image</div>
    <div class="image-preview-container">
        <img id="edit_imagePreview" class="image-preview" src="" alt="Image Preview">
    </div>
</div>

<div class="btn-container">
    <button type="submit" class="btn-update">Update Material</button>
</div>
</form>
</div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <h2>Confirm Delete</h2>
        <p>Are you sure you want to delete <span id="materialToDelete"></span>?</p>
        <form action="" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete_materialID" name="materialID">
            <div class="btn-container">
                <button type="button" class="btn-add" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn-delete">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal Functions
    function openAddModal() {
        document.getElementById("addMaterialModal").style.display = "block";
    }

    function closeAddModal() {
        document.getElementById("addMaterialModal").style.display = "none";
    }

    function openEditModal(material) {
        document.getElementById("edit_materialID").value = material.MaterialID;
        document.getElementById("edit_materialName").value = material.MaterialName;
        document.getElementById("edit_supplierID").value = material.SupplierID;
        document.getElementById("edit_supplierName").value = "";
        document.getElementById("edit_raw_warehouse").value = material.raw_warehouse;
        document.getElementById("edit_quantityInStock").value = material.QuantityInStock;
        document.getElementById("edit_minimumStock").value = material.MinimumStock;
        document.getElementById("edit_unitCost").value = material.UnitCost;
        
        if (material.raw_material_img) {
            document.getElementById("edit_imagePreview").src = "../assets/imgs/" + material.raw_material_img;
            document.getElementById("edit_imagePreview").classList.add("show");
        } else {
            document.getElementById("edit_imagePreview").classList.remove("show");
        }
        
        document.getElementById("editMaterialModal").style.display = "block";
    }

    function closeEditModal() {
        document.getElementById("editMaterialModal").style.display = "none";
    }

    function confirmDelete(materialID, materialName) {
        document.getElementById("delete_materialID").value = materialID;
        document.getElementById("materialToDelete").textContent = materialName;
        document.getElementById("deleteConfirmModal").style.display = "block";
    }

    function closeDeleteModal() {
        document.getElementById("deleteConfirmModal").style.display = "none";
    }

    // Image Preview
    document.getElementById('raw_material_img').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreview').classList.add('show');
            }
            reader.readAsDataURL(file);
        } else {
            document.getElementById('imagePreview').classList.remove('show');
        }
    });

    document.getElementById('edit_raw_material_img').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('edit_imagePreview').src = e.target.result;
                document.getElementById('edit_imagePreview').classList.add('show');
            }
            reader.readAsDataURL(file);
        }
    });

    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('addMaterialModal')) {
            closeAddModal();
        }
        if (event.target == document.getElementById('editMaterialModal')) {
            closeEditModal();
        }
        if (event.target == document.getElementById('deleteConfirmModal')) {
            closeDeleteModal();
        }
    }
</script>
</div>
</div>
</body>
</html>