<?php
$current_page = basename($_SERVER['PHP_SELF']);

include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// For debugging database connection issues
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create new product
        if ($_POST['action'] === 'create') {
            $productName = $_POST['productName'];
            $category = $_POST['category'];
            $weight = $_POST['weight'];
            $productionCost = $_POST['productionCost'];
            $sellingPrice = $_POST['sellingPrice'];
            $locationID = $_POST['locationID'];
            $materialID = $_POST['materialID'];
            
            // Get material name for image naming
            $get_material_query = "SELECT MaterialName FROM rawmaterials WHERE MaterialID = ?";
            $stmt = $conn->prepare($get_material_query);
            $stmt->bind_param("i", $materialID);
            $stmt->execute();
            $material_result = $stmt->get_result();
            $material_name = ($material_result && $row = $material_result->fetch_assoc()) ? $row['MaterialName'] : 'unknown';
            $stmt->close();
            
            // Image handling
            $image_name = '';
            if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] === 0) {
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $file_name = $_FILES['product_img']['name'];
                $file_size = $_FILES['product_img']['size'];
                $file_tmp = $_FILES['product_img']['tmp_name'];
                $file_type = $_FILES['product_img']['type'];
                
                $file_ext_arr = explode('.', $file_name);
                $file_ext = strtolower(end($file_ext_arr));
                
                if (in_array($file_ext, $allowed_ext)) {
                    // Generate filename based on material name
                    $image_name = strtolower(str_replace(' ', '_', $material_name)) . '.' . $file_ext;
                    
                    // Make sure the directory exists
                    if (!file_exists("../assets/imgs/")) {
                        mkdir("../assets/imgs/", 0777, true);
                    }
                    
                    move_uploaded_file($file_tmp, "../assets/imgs/" . $image_name);
                } else {
                    echo "<script>alert('Invalid file extension. Only JPG, JPEG, PNG files are allowed.');</script>";
                    $image_name = '';
                }
            }
            
            $query = "INSERT INTO products (ProductName, Category, Weight, ProductionCost, SellingPrice, LocationID, MaterialID, product_img) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssddiiis", $productName, $category, $weight, $productionCost, $sellingPrice, $locationID, $materialID, $image_name);
            
            if ($stmt->execute()) {
                echo "<script>alert('Product added successfully!'); window.location.href='products.php';</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
        
        // Update product
        elseif ($_POST['action'] === 'update') {
            $productID = $_POST['productID'];
            $productName = $_POST['productName'];
            $category = $_POST['category'];
            $weight = $_POST['weight'];
            $productionCost = $_POST['productionCost'];
            $sellingPrice = $_POST['sellingPrice'];
            $locationID = $_POST['locationID'];
            $materialID = $_POST['materialID'];
            
            // Get material name for image naming
            $get_material_query = "SELECT MaterialName FROM rawmaterials WHERE MaterialID = ?";
            $stmt = $conn->prepare($get_material_query);
            $stmt->bind_param("i", $materialID);
            $stmt->execute();
            $material_result = $stmt->get_result();
            $material_name = ($material_result && $row = $material_result->fetch_assoc()) ? $row['MaterialName'] : 'unknown';
            $stmt->close();
            
            // Check if there's a new image uploaded
            if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] === 0) {
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $file_name = $_FILES['product_img']['name'];
                $file_size = $_FILES['product_img']['size'];
                $file_tmp = $_FILES['product_img']['tmp_name'];
                
                $file_ext_arr = explode('.', $file_name);
                $file_ext = strtolower(end($file_ext_arr));
                
                if (in_array($file_ext, $allowed_ext)) {
                    // Get current image to delete
                    $get_img_query = "SELECT product_img FROM products WHERE ProductID = ?";
                    $stmt = $conn->prepare($get_img_query);
                    $stmt->bind_param("i", $productID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        if (!empty($row['product_img'])) {
                            $old_image = "../assets/imgs/" . $row['product_img'];
                            if (file_exists($old_image)) {
                                unlink($old_image);
                            }
                        }
                    }
                    
                    // Make sure the directory exists
                    if (!file_exists("../assets/imgs/")) {
                        mkdir("../assets/imgs/", 0777, true);
                    }
                    
                    // Generate filename based on material name
                    $image_name = strtolower(str_replace(' ', '_', $material_name)) . '.' . $file_ext;
                    move_uploaded_file($file_tmp, "../assets/imgs/" . $image_name);
                    
                    // Update with new image
                    $query = "UPDATE products SET ProductName = ?, Category = ?, Weight = ?, 
                             ProductionCost = ?, SellingPrice = ?, LocationID = ?, MaterialID = ?, product_img = ?
                             WHERE ProductID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssddiiisi", $productName, $category, $weight, $productionCost, $sellingPrice, $locationID, $materialID, $image_name, $productID);
                } else {
                    echo "<script>alert('Invalid file extension. Only JPG, JPEG, PNG files are allowed.');</script>";
                    return;
                }
            } else {
                // Update without changing image
                $query = "UPDATE products SET ProductName = ?, Category = ?, Weight = ?, 
                         ProductionCost = ?, SellingPrice = ?, LocationID = ?, MaterialID = ? 
                         WHERE ProductID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssddiiii", $productName, $category, $weight, $productionCost, $sellingPrice, $locationID, $materialID, $productID);
            }
            
            if ($stmt->execute()) {
                echo "<script>alert('Product updated successfully!'); window.location.href='products.php';</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
        
        // Delete product
        elseif ($_POST['action'] === 'delete') {
            $productID = $_POST['productID'];
            
            // Get image filename to delete
            $get_img_query = "SELECT product_img FROM products WHERE ProductID = ?";
            $stmt = $conn->prepare($get_img_query);
            $stmt->bind_param("i", $productID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['product_img'])) {
                    $old_image = "../assets/imgs/" . $row['product_img'];
                    if (file_exists($old_image)) {
                        unlink($old_image);
                    }
                }
            }
            
            $query = "DELETE FROM products WHERE ProductID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $productID);
            
            if ($stmt->execute()) {
                echo "<script>alert('Product deleted successfully!'); window.location.href='products.php';</script>";
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
    }
}

// Make sure the tables exist
// First check if locations table exists
$check_locations_table = "SHOW TABLES LIKE 'locations'";
$locations_table_exists = $conn->query($check_locations_table);
if ($locations_table_exists->num_rows == 0) {
    // Table doesn't exist, create it
    $create_locations_table = "CREATE TABLE locations (
        LocationID INT PRIMARY KEY AUTO_INCREMENT,
        LocationName VARCHAR(255) NOT NULL
    )";
    $conn->query($create_locations_table);
    
    // Add some default locations
    $insert_locations = "INSERT INTO locations (LocationName) VALUES 
        ('Warehouse A'),
        ('Warehouse B'),
        ('Storage Room 1'),
        ('Distribution Center')";
    $conn->query($insert_locations);
}

// Check if rawmaterials table exists
$check_materials_table = "SHOW TABLES LIKE 'rawmaterials'";
$materials_table_exists = $conn->query($check_materials_table);
if ($materials_table_exists->num_rows == 0) {
    // Table doesn't exist, create it
    $create_materials_table = "CREATE TABLE rawmaterials (
        MaterialID INT PRIMARY KEY AUTO_INCREMENT,
        MaterialName VARCHAR(255) NOT NULL
    )";
    $conn->query($create_materials_table);
    
    // Add some default materials
    $insert_materials = "INSERT INTO rawmaterials (MaterialName) VALUES 
        ('Wood'),
        ('Metal'),
        ('Plastic'),
        ('Glass'),
        ('Fabric')";
    $conn->query($insert_materials);
}

// Fetch all locations for dropdowns
$locations = [];
$locations_query = "SELECT LocationID, LocationName FROM locations ORDER BY LocationName";
$locations_result = $conn->query($locations_query);
if ($locations_result && $locations_result->num_rows > 0) {
    while ($row = $locations_result->fetch_assoc()) {
        $locations[] = $row;
    }
} else {
    // If no locations found, provide a default option
    $locations[] = ['LocationID' => 1, 'LocationName' => 'Default Location'];
}

// Fetch all materials for dropdowns
$materials = [];
$materials_query = "SELECT MaterialID, MaterialName FROM rawmaterials ORDER BY MaterialName";
$materials_result = $conn->query($materials_query);
if ($materials_result && $materials_result->num_rows > 0) {
    while ($row = $materials_result->fetch_assoc()) {
        $materials[] = $row;
    }
} else {
    // If no materials found, provide a default option
    $materials[] = ['MaterialID' => 1, 'MaterialName' => 'Default Material'];
}

// Check if the products table exists
$check_products_table = "SHOW TABLES LIKE 'products'";
$products_table_exists = $conn->query($check_products_table);
if ($products_table_exists->num_rows == 0) {
    // Table doesn't exist, create it
    $create_products_table = "CREATE TABLE products (
        ProductID INT PRIMARY KEY AUTO_INCREMENT,
        ProductName VARCHAR(255) NOT NULL,
        Category VARCHAR(100) NOT NULL,
        Weight DOUBLE NOT NULL,
        ProductionCost DOUBLE NOT NULL,
        SellingPrice DOUBLE NOT NULL,
        LocationID INT,
        MaterialID INT,
        product_img VARCHAR(255),
        FOREIGN KEY (LocationID) REFERENCES locations(LocationID) ON DELETE SET NULL,
        FOREIGN KEY (MaterialID) REFERENCES rawmaterials(MaterialID) ON DELETE SET NULL
    )";
    $conn->query($create_products_table);
    
    // No need to add sample products
    $products_exist = false;
} else {
    $products_exist = true;
}

// Now fetch products data
try {
    $query = "SELECT p.*, 
              COALESCE(l.LocationName, 'Unknown Location') as LocationName, 
              COALESCE(r.MaterialName, 'Unknown Material') as MaterialName 
              FROM products p 
              LEFT JOIN locations l ON p.LocationID = l.LocationID 
              LEFT JOIN rawmaterials r ON p.MaterialID = r.MaterialID 
              ORDER BY p.ProductName";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo "<div class='error-message'>Error fetching products: " . $e->getMessage() . "</div>";
    // Try a simpler query as fallback
    $query = "SELECT * FROM products ORDER BY ProductName";
    $result = $conn->query($query);
    
    // If still failing, create an empty result
    if (!$result) {
        echo "<div class='error-message'>Database error: " . $conn->error . "</div>";
        $result = false;
    }
}

// Create default images directory if it doesn't exist
if (!file_exists("../assets/imgs/")) {
    mkdir("../assets/imgs/", 0777, true);
}

// Check if default-product.png exists, if not create a simple one
$default_image_path = "../assets/imgs/default-product.png";
if (!file_exists($default_image_path)) {
    // Create a simple default image
    $width = 200;
    $height = 200;
    $img = imagecreatetruecolor($width, $height);
    $bg = imagecolorallocate($img, 240, 240, 240);
    $text_color = imagecolorallocate($img, 100, 100, 100);
    
    // Fill background
    imagefilledrectangle($img, 0, 0, $width, $height, $bg);
    
    // Add text
    $text = "No Image";
    $font = 5; // Built-in font
    $text_width = imagefontwidth($font) * strlen($text);
    $text_height = imagefontheight($font);
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    imagestring($img, $font, $x, $y, $text, $text_color);
    
    // Save the image
    imagepng($img, $default_image_path);
    imagedestroy($img);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Add FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Additional styles for scrollable table */
        .products-table-container {
            height: calc(100vh - 250px); /* Fixed height for table container */
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f8f9fa;
        }
        
        .products-table th {
            padding: 12px 8px;
            border-bottom: 2px solid #ddd;
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: left;
        }
        
        .products-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .products-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .product-thumbnail {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
            border-radius: 4px;
            display: block; /* Ensure image displays correctly */
            background-color: #f8f9fa; /* Light background for transparent images */
            border: 1px solid #ddd; /* Border to help distinguish the image area */
        }
        
        .no-image {
            display: inline-block;
            width: 60px;
            height: 60px;
            line-height: 60px;
            text-align: center;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 10px;
            color: #888;
        }
        
        /* Make the main content take full height */
        .dashboard-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }
        
        .content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        /* Make modals more responsive */
        .modal-content {
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Image preview styling */
        .image-preview-container {
            margin-top: 10px;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        
        /* Additional helper text */
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* CSS to position the Add Button on the left */
        .action-button-container {
            display: flex;
            justify-content: flex-start; /* Aligns items to the left */
            margin-bottom: 20px;
        }

        .btn-add {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-add:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1><i class="fas fa-shipping-fast"></i> All Products</h1>
            </header>
            <div class="content">
                <!-- Add New Product Button -->
                <div class="action-button-container">
                    <button class="btn-add" onclick="openAddModal()">Add New Product</button>
                </div>
                
                <!-- Products Table -->
                <div class="products-table-container">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Weight (kg)</th>
                                <th>Production Cost</th>
                                <th>Selling Price</th>
                                <th>Profit</th>
                                <th>Location</th>
                                <th>Material</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php 
                                $counter = 1;
                                while ($row = $result->fetch_assoc()): 
                                    $profit = $row['SellingPrice'] - $row['ProductionCost'];
                                    $profitClass = ($profit > 0) ? 'profit-positive' : 'profit-negative';
                                    // Check if image exists
                                    $image_exists = false;
                                    if (!empty($row['product_img'])) {
                                        $image_path = "../assets/imgs/" . $row['product_img'];
                                        $image_exists = file_exists($image_path);
                                    }
                                ?>
                                    <tr>
                                        <td class="row-number"><?php echo $counter++; ?></td>
                                        <td>
                                            <?php if ($image_exists): ?>
                                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($row['ProductName']); ?>" class="product-thumbnail">
                                            <?php elseif (!empty($row['product_img'])): ?>
                                                <img src="../assets/imgs/default-product.png" alt="<?php echo htmlspecialchars($row['ProductName']); ?>" class="product-thumbnail">
                                            <?php else: ?>
                                                <div class="no-image">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Category']); ?></td>
                                        <td><?php echo number_format($row['Weight'], 2); ?></td>
                                        <td><?php echo number_format($row['ProductionCost'], 2); ?></td>
                                        <td><?php echo number_format($row['SellingPrice'], 2); ?></td>
                                        <td class="<?php echo $profitClass; ?>">
                                            <?php echo number_format($profit, 2); ?>
                                        </td>
                                        <td><?php echo isset($row['LocationName']) ? htmlspecialchars($row['LocationName']) : 'Unknown'; ?></td>
                                        <td><?php echo isset($row['MaterialName']) ? htmlspecialchars($row['MaterialName']) : 'Unknown'; ?></td>
                                        <td class="action-buttons">
                                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                            <button class="btn-delete" onclick="confirmDelete(<?php echo $row['ProductID']; ?>, '<?php echo htmlspecialchars($row['ProductName'], ENT_QUOTES); ?>')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="no-records">No products found in the database. Add some products to get started.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Product Modal -->
                <div id="addProductModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeAddModal()">&times;</span>
                        <h2>Add New Product</h2>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="productName">Product Name</label>
                                <input type="text" id="productName" name="productName" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="productionCost">Production Cost</label>
                                <input type="number" id="productionCost" name="productionCost" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="sellingPrice">Selling Price</label>
                                <input type="number" id="sellingPrice" name="sellingPrice" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="locationID">Warehouse Location</label>
                                <select id="locationID" name="locationID" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['LocationID']; ?>"><?php echo htmlspecialchars($location['LocationName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="materialID">Primary Material</label>
                                <select id="materialID" name="materialID" required>
                                    <option value="">Select Material</option>
                                    <?php foreach ($materials as $material): ?>
                                        <option value="<?php echo $material['MaterialID']; ?>"><?php echo htmlspecialchars($material['MaterialName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="product_img">Product Image</label>
                                <input type="file" id="product_img" name="product_img" accept=".jpg,.jpeg,.png" required>
                                <p class="help-text">Image will be named using material name (e.g., wood.jpg)</p>
                                <div class="image-preview-container">
                                    <img id="imagePreview" class="image-preview" src="" alt="Image Preview">
                                </div>
                            </div>
                            
                            <div class="btn-container">
                                <button type="submit" class="btn-save">Save Product</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Edit Product Modal -->
                <div id="editProductModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeEditModal()">&times;</span>
                        <h2>Edit Product</h2>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" id="edit_productID" name="productID">
                            
                            <div class="form-group">
                                <label for="edit_productName">Product Name</label>
                                <input type="text" id="edit_productName" name="productName" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_category">Category</label>
                                <input type="text" id="edit_category" name="category" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_weight">Weight (kg)</label>
                                <input type="number" id="edit_weight" name="weight" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_productionCost">Production Cost</label>
                                <input type="number" id="edit_productionCost" name="productionCost" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_sellingPrice">Selling Price</label>
                                <input type="number" id="edit_sellingPrice" name="sellingPrice" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_locationID">Warehouse Location</label>
                                <select id="edit_locationID" name="locationID" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['LocationID']; ?>"><?php echo htmlspecialchars($location['LocationName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_materialID">Primary Material</label>
                                <select id="edit_materialID" name="materialID" required>
                                    <option value="">Select Material</option>
                                    <?php foreach ($materials as $material): ?>
                                        <option value="<?php echo $material['MaterialID']; ?>"><?php echo htmlspecialchars($material['MaterialName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_product_img">Product Image</label>
                                <input type="file" id="edit_product_img" name="product_img" accept=".jpg,.jpeg,.png">
                                <p class="help-text">Leave blank to keep current image. New image will use material name (e.g., metal.jpg)</p>
                                <div class="image-preview-container">
                                    <img id="edit_imagePreview" class="image-preview" src="" alt="Image Preview">
                                </div>
                            </div>
                            
                            <div class="btn-container">
                                <button type="submit" class="btn-update">Update Product</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Delete Confirmation Form (Hidden) -->
                <form id="deleteForm" action="" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_productID" name="productID">
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add Product Modal
        function openAddModal() {
            document.getElementById('addProductModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addProductModal').style.display = 'none';
        }
        
        // Edit Product Modal
        function openEditModal(product) {
            document.getElementById('edit_productID').value = product.ProductID;
            document.getElementById('edit_productName').value = product.ProductName;
            document.getElementById('edit_category').value = product.Category;
            document.getElementById('edit_weight').value = product.Weight;
            document.getElementById('edit_productionCost').value = product.ProductionCost;
            document.getElementById('edit_sellingPrice').value = product.SellingPrice;
            document.getElementById('edit_locationID').value = product.LocationID;
            document.getElementById('edit_materialID').value = product.MaterialID;
            
            // Show current image if it exists
            if (product.product_img) {
                document.getElementById('edit_imagePreview').src = "../assets/imgs/" + product.product_img;
                document.getElementById('edit_imagePreview').style.display = 'block';
            } else {
                document.getElementById('edit_imagePreview').style.display = 'none';
            }
            
            document.getElementById('editProductModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editProductModal').style.display = 'none';
        }
        
        // Delete Product
        function confirmDelete(productID, productName) {
            if (confirm("Are you sure you want to delete the product '" + productName + "'?")) {
                document.getElementById('delete_productID').value = productID;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == document.getElementById('addProductModal')) {
                closeAddModal();
            } else if (event.target == document.getElementById('editProductModal')) {
                closeEditModal();
            }
        }
        
        // Image preview
        document.getElementById('product_img').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        document.getElementById('edit_product_img').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('edit_imagePreview').src = e.target.result;
                    document.getElementById('edit_imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html>