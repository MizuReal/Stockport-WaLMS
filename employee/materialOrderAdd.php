<?php
require_once 'session_check.php'; // Adjust path as needed
requireActiveLogin(); // This ensures user is logged in AND has Active status
require_once '../layouts/employeeSidebar.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = 'localhost';
$dbname = 'stockport';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch warehouses for dropdown selection
$warehouseStmt = $pdo->query("
    SELECT 
        pw.productLocationID,
        pw.productWarehouse,
        pw.Section,
        pw.Capacity,
        pw.current_usage,
        pw.remaining_capacity,
        pw.warehouse_weight_unit
    FROM products_warehouse pw
");
$warehouses = $warehouseStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    // Create a new raw material order
    $productID = $_POST['ProductID'];
    $employeeID = $_SESSION['employeeID'];
    $startDate = date('Y-m-d');
    $endDate = $_POST['EndDate'];
    $status = 'In Progress';
    $sheetCount = max(1, (int)$_POST['SheetCount']);
    $warehouseID = $_POST['WarehouseID']; // Warehouse selection field
    
    // Get the product ratio, material information, and weight
    $productStmt = $pdo->prepare("SELECT p.minimum_quantity, p.MaterialID, p.Weight, p.weight_unit FROM products p WHERE p.ProductID = :ProductID");
    $productStmt->execute([':ProductID' => $productID]);
    $productInfo = $productStmt->fetch(PDO::FETCH_ASSOC);
    $ratio = isset($productInfo['minimum_quantity']) ? $productInfo['minimum_quantity'] : 0;
    $materialID = $productInfo['MaterialID'];
    $productWeight = floatval($productInfo['Weight']);
    $productWeightUnit = $productInfo['weight_unit'];
    
    // Check current material stock
    $stockStmt = $pdo->prepare("SELECT QuantityInStock FROM rawmaterials WHERE MaterialID = :MaterialID");
    $stockStmt->execute([':MaterialID' => $materialID]);
    $currentStock = $stockStmt->fetchColumn();
    
    // Check warehouse capacity by weight
    $warehouseStmt = $pdo->prepare("
        SELECT pw.Capacity, pw.warehouse_weight_unit
        FROM products_warehouse pw
        WHERE pw.productLocationID = :WarehouseID
    ");
    $warehouseStmt->execute([':WarehouseID' => $warehouseID]);
    $warehouseInfo = $warehouseStmt->fetch(PDO::FETCH_ASSOC);
    $warehouseCapacity = $warehouseInfo['Capacity'];
    $warehouseWeightUnit = $warehouseInfo['warehouse_weight_unit'];
    
    $quantityOrdered = $ratio * $sheetCount; // Add this line to fix the null QuantityOrdered

    // Get current warehouse usage (convert all weights to kg for consistency)
    $usageStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN p.weight_unit = 'g' THEN (p.Weight * (po.QuantityOrdered - po.QuantityProduced)) / 1000
                    WHEN p.weight_unit = 'kg' THEN (p.Weight * (po.QuantityOrdered - po.QuantityProduced))
                END
            ), 0) as TotalWeight
        FROM productionorders po
        JOIN products p ON po.ProductID = p.ProductID
        WHERE po.warehouseID = :WarehouseID AND po.Status IN ('Planned', 'In Progress')
    ");
    $usageStmt->execute([':WarehouseID' => $warehouseID]);
    $currentUsage = floatval($usageStmt->fetchColumn());

    // Convert all measurements to kg for comparison
    $warehouseCapacity = $warehouseWeightUnit == 'g' ? $warehouseCapacity / 1000 : $warehouseCapacity;
    $totalProductWeight = $productWeight * $quantityOrdered;
    $totalProductWeight = $productWeightUnit == 'g' ? $totalProductWeight / 1000 : $totalProductWeight;
    
    $remainingCapacity = $warehouseCapacity - $currentUsage;

    // Add debug output
    error_log("Debug - Warehouse Capacity (kg): $warehouseCapacity");
    error_log("Debug - Current Usage (kg): $currentUsage");
    error_log("Debug - Total Product Weight (kg): $totalProductWeight");
    error_log("Debug - Remaining Capacity (kg): $remainingCapacity");

    if ($ratio <= 0) {
        $_SESSION['order_message'] = [
            'type' => 'error',
            'text' => 'Error: No production ratio defined for this product. Please contact administrator.'
        ];
    } elseif ($currentStock < $sheetCount) {
        $_SESSION['order_message'] = [
            'type' => 'error',
            'text' => "Error: Insufficient material in stock. Available: $currentStock sheets. Required: $sheetCount sheets."
        ];
    } elseif ($totalProductWeight > $remainingCapacity) {
        $_SESSION['order_message'] = [
            'type' => 'error',
            'text' => "Error: Insufficient warehouse capacity. Available: " . number_format($remainingCapacity, 2) . " kg. Required: " . number_format($totalProductWeight, 2) . " kg."
        ];
    } else {
        $quantityProduced = 0;

        // Begin transaction to ensure database consistency
        $pdo->beginTransaction();
        
        try {
            // Insert the new order with warehouse ID
            $stmt = $pdo->prepare("INSERT INTO productionOrders (ProductID, EmployeeID, StartDate, EndDate, Status, QuantityOrdered, QuantityProduced, warehouseID)
                            VALUES (:ProductID, :EmployeeID, :StartDate, :EndDate, :Status, :QuantityOrdered, :QuantityProduced, :WarehouseID)");
            $stmt->execute([
                ':ProductID' => $productID,
                ':EmployeeID' => $employeeID,
                ':StartDate' => $startDate,
                ':EndDate' => $endDate,
                ':Status' => $status,
                ':QuantityOrdered' => $quantityOrdered,
                ':QuantityProduced' => $quantityProduced,
                ':WarehouseID' => $warehouseID
            ]);

            // Update or insert warehouse inventory record
            $warehouseInventoryStmt = $pdo->prepare("
                INSERT INTO warehouse_inventory (
                    warehouse_id, product_id, quantity, 
                    warehouse_weight_unit, current_usage, capacity
                ) VALUES (
                    :warehouseID, :productID, :quantity,
                    :weightUnit, :currentUsage, :capacity
                ) ON DUPLICATE KEY UPDATE
                    quantity = quantity + :quantity,
                    current_usage = current_usage + :currentUsage
            ");

            $warehouseInventoryStmt->execute([
                ':warehouseID' => $warehouseID,
                ':productID' => $productID,
                ':quantity' => $quantityOrdered,
                ':weightUnit' => $warehouseWeightUnit,
                ':currentUsage' => $totalProductWeight,
                ':capacity' => $warehouseCapacity
            ]);

            // Update warehouse current usage (store everything in kg)
            $updateWarehouseStmt = $pdo->prepare("
                UPDATE products_warehouse 
                SET current_usage = GREATEST(0, current_usage + :newUsage)
                WHERE productLocationID = :warehouseID");
            $updateWarehouseStmt->execute([
                ':newUsage' => $totalProductWeight, // Already in kg from earlier conversion
                ':warehouseID' => $warehouseID
            ]);
            
            // Update the material stock
            $updateStmt = $pdo->prepare("UPDATE rawmaterials SET 
                                        QuantityInStock = QuantityInStock - :SheetCount 
                                        WHERE MaterialID = :MaterialID");
            $updateStmt->execute([
                ':SheetCount' => $sheetCount,
                ':MaterialID' => $materialID
            ]);

            // Commit the transaction
            $pdo->commit();
            
            $_SESSION['order_message'] = [
                'type' => 'success',
                'text' => "<div class='success-message'>
                            <div class='success-header'>
                                <span class='checkmark'>✔</span>
                                Order Created Successfully!
                            </div>
                            <div class='success-details'>
                                <div class='detail-item'>
                                    <span class='detail-label'>Quantity:</span>
                                    <span class='detail-value'>{$quantityOrdered} units</span>
                                </div>
                                <div class='detail-item'>
                                    <span class='detail-label'>Material sheets:</span>
                                    <span class='detail-value'>{$sheetCount}</span>
                                </div>
                                <div class='detail-item'>
                                    <span class='detail-label'>Total weight:</span>
                                    <span class='detail-value'>" . number_format($totalProductWeight, 2) . " kg</span>
                                </div>
                            </div>
                          </div>"
            ];
        } catch (Exception $e) {
            // Roll back the transaction if something failed
            $pdo->rollBack();
            $_SESSION['order_message'] = [
                'type' => 'error',
                'text' => "<div class='error-message'>
                            <div class='error-header'>
                                <span class='error-icon'>✘</span>
                                Error
                            </div>
                            <div class='error-details'>" . $e->getMessage() . "</div>
                          </div>"
            ];
        }
    }
    
    // Redirect to the same page to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Check for flash messages
$orderCreationMessage = "";
if (isset($_SESSION['order_message'])) {
    // Remove the alert class wrapper and directly output the message
    $orderCreationMessage = $_SESSION['order_message']['text'];
    // Clear the message to prevent it from showing on subsequent page loads
    unset($_SESSION['order_message']);
}

// Fetch all products for the dropdown, including MaterialName, MaterialID, current stock, minimum_quantity (ratio), weight, and weight_unit
$productStmt = $pdo->query("
    SELECT p.ProductID, p.ProductName, p.minimum_quantity, p.Weight, p.weight_unit, rm.MaterialID, rm.MaterialName, rm.QuantityInStock 
    FROM products p
    JOIN rawmaterials rm ON p.MaterialID = rm.MaterialID
");
$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

// Create product data mappings for JavaScript
$productData = [];
foreach ($products as $product) {
    $productData[$product['ProductID']] = [
        'ratio' => $product['minimum_quantity'],
        'stock' => $product['QuantityInStock'],
        'weight' => $product['Weight'],
        'weightUnit' => $product['weight_unit']
    ];
}

// Fetch current warehouse capacities and usages with weight calculations
$warehouseUsageStmt = $pdo->query("
    SELECT 
        pw.productLocationID,
        pw.productWarehouse,
        pw.Section,
        pw.Capacity,
        pw.current_usage,
        pw.remaining_capacity,
        pw.warehouse_weight_unit,
        COALESCE(wi.quantity, 0) as stored_quantity
    FROM 
        products_warehouse pw
    LEFT JOIN 
        warehouse_inventory wi ON pw.productLocationID = wi.warehouse_id
    GROUP BY 
        pw.productLocationID
");
$warehouseUsages = $warehouseUsageStmt->fetchAll(PDO::FETCH_ASSOC);

// Warehouse data for JavaScript
$warehouseData = [];
foreach ($warehouseUsages as $warehouse) {
    $currentUsage = floatval($warehouse['current_usage']);
    $capacity = floatval($warehouse['Capacity']);
    $remainingCapacity = max(0, $capacity - $currentUsage);

    $warehouseData[$warehouse['productLocationID']] = [
        'capacity' => $capacity,
        'currentUsage' => $currentUsage,
        'remainingCapacity' => $remainingCapacity,
        'weightUnit' => $warehouse['warehouse_weight_unit']
    ];
}

// Fetch all orders with additional details including weights
$orderStmt = $pdo->query("
    SELECT 
        po.OrderID, 
        p.ProductID, 
        p.ProductName, 
        p.minimum_quantity, 
        p.Weight,
        p.weight_unit,
        rm.MaterialName, 
        rm.raw_material_img, 
        e.FirstName, 
        e.LastName, 
        po.StartDate, 
        po.EndDate, 
        po.Status, 
        po.QuantityOrdered, 
        po.QuantityProduced,
        pw.productWarehouse as Warehouse,
        pw.Section as WarehouseSection,
        pw.warehouse_weight_unit,
        wi.quantity as warehouse_quantity
    FROM 
        productionOrders po
    JOIN 
        products p ON po.ProductID = p.ProductID
    JOIN 
        rawmaterials rm ON p.MaterialID = rm.MaterialID
    JOIN 
        employees e ON po.EmployeeID = e.EmployeeID
    LEFT JOIN
        products_warehouse pw ON po.warehouseID = pw.productLocationID
    LEFT JOIN 
        warehouse_inventory wi ON po.warehouseID = wi.warehouse_id 
        AND po.ProductID = wi.product_id
    ORDER BY 
        po.StartDate DESC
");
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

// JSON encode data for JavaScript
$jsProductData = json_encode($productData);
$jsWarehouseData = json_encode($warehouseData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Material Order Processing</title>
    <link rel="stylesheet" href="../assets/css/eminventory.css">
</head>
<body class="sidebar-expanded">
    <?php renderSidebar('rawMaterialOrder'); ?>
    
    <div class="content-wrapper">
        <!-- Main content -->
        <div class="main-content">
            <h1 class="page-title">Raw Material Order Processing</h1>
            
            <!-- Tab Navigation -->
            <div class="tabs">
                <button class="tab-button active" data-tab="create-order">Create Order</button>
                <button class="tab-button" data-tab="product-ratios">Product Ratios</button>
                <button class="tab-button" data-tab="material-inventory">Material Inventory</button>
                <button class="tab-button" data-tab="warehouse-status">Warehouse Status</button>
                <button class="tab-button" data-tab="current-orders">Current Orders</button>
            </div>

            <!-- Display order creation message if any -->
            <?php if (!empty($orderCreationMessage)): ?>
                <?= $orderCreationMessage ?>
            <?php endif; ?>
            
            <!-- Create Order Tab (default active) -->
            <div class="tab-content active" id="create-order">
                <!-- Form to create a new order section goes here -->
                <section class="card">
                    <h2 class="card-header">Create New Order</h2>
                    <form method="POST" action="">
                        <label for="ProductID">Select Product:</label>
                        <select id="ProductID" name="ProductID" required class="search-bar" onchange="updateOrderInfo()">
                            <option value="">-- Select a Product --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= htmlspecialchars($product['ProductID']) ?>">
                                    <?= htmlspecialchars($product['ProductName']) ?> (Material: <?= htmlspecialchars($product['MaterialName']) ?>, 
                                    Weight: <?= htmlspecialchars($product['Weight']) ?> <?= htmlspecialchars($product['weight_unit']) ?>/unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="stock-info" style="margin-top: 5px;"></p>
                        
                        <label for="SheetCount">Number of Material Sheets:</label>
                        <input type="number" id="SheetCount" name="SheetCount" min="1" value="1" required class="search-bar" onchange="updateOrderInfo()">
                        
                        <label for="QuantityDisplay">Quantity to be Ordered:</label>
                        <input type="text" id="QuantityDisplay" readonly class="search-bar">
                        <input type="hidden" id="QuantityOrdered" name="QuantityOrdered">
                        <p id="material-info" style="font-style: italic; margin-top: 5px;"></p>
                        
                        <!-- Warehouse selection dropdown with weight capacity information -->
                        <label for="WarehouseID">Storage Warehouse:</label>
                        <select id="WarehouseID" name="WarehouseID" required class="search-bar" onchange="updateOrderInfo()">
                            <option value="">-- Select Destination Warehouse --</option>
                            <?php foreach ($warehouseUsages as $warehouse): 
                                $currentUsage = floatval($warehouse['current_usage']);
                                $availableCapacity = max(0, floatval($warehouse['Capacity']) - $currentUsage);
                            ?>
                                <option value="<?= htmlspecialchars($warehouse['productLocationID']) ?>">
                                    <?= htmlspecialchars($warehouse['productWarehouse']) ?> - 
                                    <?= htmlspecialchars($warehouse['Section']) ?> 
                                    (Available: <?= number_format($availableCapacity, 2) ?> <?= htmlspecialchars($warehouse['warehouse_weight_unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="weight-info" style="font-style: italic; margin-top: 5px;"></p>
                        
                        <label for="EndDate">Expected End Date:</label>
                        <input type="date" id="EndDate" name="EndDate" required class="search-bar" min="">
                        
                        <button type="submit" name="create_order" class="btn">Create Order</button>
                    </form>
                </section>
            </div>

            <!-- Product Ratios Tab -->
            <div class="tab-content" id="product-ratios">
                <section class="card">
                    <h2 class="card-header">Material to Product Ratios</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Product</th>
                                <th>Units per Sheet</th>
                                <th>Weight per Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($products as $product):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($product['MaterialName']) ?></td>
                                <td><?= htmlspecialchars($product['ProductName']) ?></td>
                                <td><?= htmlspecialchars($product['minimum_quantity']) ?></td>
                                <td><?= htmlspecialchars($product['Weight']) ?> <?= htmlspecialchars($product['weight_unit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>

            <!-- Material Inventory Tab -->
            <div class="tab-content" id="material-inventory">
                <section class="card">
                    <h2 class="card-header">Current Raw Material Inventory</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Quantity in Stock (Sheets)</th>
                                <th>Minimum Stock Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $materialStmt = $pdo->query("SELECT MaterialName, QuantityInStock, MinimumStock FROM rawmaterials");
                            $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($materials as $material):
                                $stockStatus = $material['QuantityInStock'] <= $material['MinimumStock'] ? 'Low' : 'Good';
                                $statusColor = $stockStatus == 'Low' ? '#FF0000' : '#008800';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($material['MaterialName']) ?></td>
                                <td><?= htmlspecialchars($material['QuantityInStock']) ?></td>
                                <td><?= htmlspecialchars($material['MinimumStock']) ?></td>
                                <td style="color: <?= $statusColor ?>;"><?= $stockStatus ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>

            <!-- Warehouse Status Tab -->
            <div class="tab-content" id="warehouse-status">
                <section class="card">
                    <h2 class="card-header">Warehouse Capacity Status</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th>Section</th>
                                <th>Total Capacity</th>
                                <th>Current Usage</th>
                                <th>Available Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouseUsages as $warehouse): 
                                $currentUsage = floatval($warehouse['current_usage']);
                                $capacity = floatval($warehouse['Capacity']);
                                $availableCapacity = max(0, $capacity - $currentUsage);
                                $capacityPercent = $capacity > 0 ? min(100, ($currentUsage / $capacity) * 100) : 0;
                                
                                // Determine status color
                                if ($capacityPercent > 90) {
                                    $statusText = 'Critical';
                                    $statusColor = '#FF0000';
                                } elseif ($capacityPercent > 75) {
                                    $statusText = 'High';
                                    $statusColor = '#FFA500';
                                } else {
                                    $statusText = 'Good';
                                    $statusColor = '#008800';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($warehouse['productWarehouse']) ?></td>
                                <td><?= htmlspecialchars($warehouse['Section']) ?></td>
                                <td><?= number_format($capacity, 2) ?> <?= htmlspecialchars($warehouse['warehouse_weight_unit']) ?></td>
                                <td><?= number_format($currentUsage, 2) ?> <?= htmlspecialchars($warehouse['warehouse_weight_unit']) ?></td>
                                <td><?= number_format($availableCapacity, 2) ?> <?= htmlspecialchars($warehouse['warehouse_weight_unit']) ?></td>
                                <td style="color: <?= $statusColor ?>;"><?= $statusText ?> (<?= round($capacityPercent) ?>%)</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>

            <!-- Current Orders Tab -->
            <div class="tab-content" id="current-orders">
                <!-- Search Bar -->
                <input type="text" id="search-bar" class="search-bar" placeholder="Search orders..." onkeyup="filterTable()">
                
                <section class="card">
                    <h2 class="card-header">Current Orders</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product to be Produced</th>
                                <th>Ordered Material</th>
                                <th>Ordered By</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Warehouse</th>
                                <th>Status</th>
                                <th>Quantity Ordered</th>
                                <th>Quantity Produced</th>
                                <th>Total Weight</th>
                            </tr>
                        </thead>
                        <tbody id="order-table-body">
                            <?php foreach ($orders as $order): 
                                // Calculate total weight for this order
                                $totalWeight = $order['Weight'] * $order['QuantityOrdered'];
                                $warehouseUnit = $order['warehouse_weight_unit'] ?? $order['weight_unit'];
                                
                                // Convert weight if necessary
                                if ($order['weight_unit'] != $warehouseUnit) {
                                    if ($order['weight_unit'] == 'g' && $warehouseUnit == 'kg') {
                                        $totalWeight = $totalWeight / 1000;
                                    } else if ($order['weight_unit'] == 'kg' && $warehouseUnit == 'g') {
                                        $totalWeight = $totalWeight * 1000;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['OrderID']) ?></td>
                                    <td><?= htmlspecialchars($order['ProductName']) ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <img src="../assets/imgs/<?= htmlspecialchars($order['raw_material_img']) ?>" alt="Material Image" style="width: 50px; height: 50px; margin-right: 10px;">
                                            <span><?= htmlspecialchars($order['MaterialName']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($order['FirstName'] . ' ' . $order['LastName']) ?></td>
                                    <td><?= htmlspecialchars($order['StartDate']) ?></td>
                                    <td><?= htmlspecialchars($order['EndDate']) ?></td>
                                    <td>
                                        <?= $order['Warehouse'] ? htmlspecialchars($order['Warehouse'] . ' - ' . $order['WarehouseSection']) : 'Not specified' ?>
                                    </td>
                                    <td><?= htmlspecialchars($order['Status']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($order['QuantityOrdered']) ?>
                                        <?php
                                        // Calculate how many sheets this represents using minimum_quantity
                                        $ratio = $order['minimum_quantity'];
                                        if ($ratio > 0) {
                                            $sheets = $order['QuantityOrdered'] / $ratio;
                                            echo " <span style='color:#666;'>($sheets sheets)</span>";
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($order['QuantityProduced']) ?></td>
                                    <td><?= number_format($totalWeight, 2) ?> <?= htmlspecialchars($warehouseUnit) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>

    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }

        .tab-button {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            color: #666;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .tab-button:hover {
            background-color: #f0f0f0;
        }

        .tab-button.active {
            color: #fff;
            background-color: #4CAF50;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Add these styles to work better with the sidebar */
        .content-wrapper {
            padding: 20px;
            transition: margin-left 0.3s ease;
            margin-left: 250px; /* Add this to account for sidebar width */
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 20px; /* Add some top padding since header is removed */
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 10px;
            }
        }

        /* Add this new style for the page title */
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 2rem;
            font-weight: bold;
            padding-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
        }

        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .success-header {
            color: #155724;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .checkmark {
            color: #28a745;
            margin-right: 10px;
            font-size: 1.4em;
        }

        .success-details {
            padding-left: 28px;
        }

        .detail-item {
            margin: 5px 0;
            color: #1e7e34;
        }

        .detail-label {
            font-weight: 500;
            margin-right: 8px;
        }

        .detail-value {
            font-weight: 600;
        }

        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .error-header {
            color: #721c24;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .error-icon {
            color: #dc3545;
            margin-right: 10px;
            font-size: 1.4em;
        }

        .error-details {
            padding-left: 28px;
            color: #721c24;
        }
    </style>

    <script>
        // Add this at the beginning of your script section
        // Set minimum date to today
        window.addEventListener('load', function() {
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const yyyy = today.getFullYear();
            
            const todayString = yyyy + '-' + mm + '-' + dd;
            document.getElementById('EndDate').min = todayString;
            
            // If there's no date set, default to today
            const endDateInput = document.getElementById('EndDate');
            if (!endDateInput.value) {
                endDateInput.value = todayString;
            }
        });

        // Prevent manual entry of past dates
        document.getElementById('EndDate').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time portion for accurate date comparison
            
            if (selectedDate < today) {
                alert('Please select a future date. The expected end date cannot be in the past.');
                this.value = today.toISOString().split('T')[0];
            }
        });

        // Your existing script code continues here...
        // Store product and warehouse data for JavaScript use
        const productData = <?= $jsProductData ?>;
        const warehouseData = <?= $jsWarehouseData ?>;
        
        // JavaScript function to filter table rows based on search input
        function filterTable() {
            const input = document.getElementById('search-bar').value.toLowerCase();
            const tableBody = document.getElementById('order-table-body');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let matchFound = false;

                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(input)) {
                        matchFound = true;
                        break;
                    }
                }

                rows[i].style.display = matchFound ? '' : 'none';
            }
        }
        
        // Convert weight units
        function convertWeight(weight, fromUnit, toUnit) {
            weight = parseFloat(weight);
            if (isNaN(weight) || weight < 0) return 0;
            // Always convert to kg for storage and comparison
            if (fromUnit === toUnit) return weight;
            if (fromUnit === 'g' && toUnit === 'kg') return Number((weight / 1000).toFixed(3));
            if (fromUnit === 'kg' && toUnit === 'g') return Number((weight * 1000).toFixed(1));
            return weight;
        }
        
        // Update quantity and warehouse capacity information
        function updateOrderInfo() {
            const productSelect = document.getElementById('ProductID');
            const warehouseSelect = document.getElementById('WarehouseID');
            const productID = productSelect.value;
            const warehouseID = warehouseSelect.value;
            
            if (!productID) {
                document.getElementById('stock-info').textContent = '';
                document.getElementById('material-info').textContent = '';
                document.getElementById('weight-info').textContent = '';
                document.getElementById('QuantityDisplay').value = '';
                return;
            }
            
            const productName = productSelect.options[productSelect.selectedIndex].text.split(' (')[0].trim();
            const sheetCount = parseInt(document.getElementById('SheetCount').value) || 1;
            
            // Get product data
            const product = productData[productID];
            
            if (!product) {
                console.error('Product data not found for ID:', productID);
                return;
            }
            
            // Calculate total quantity
            const totalQuantity = product.ratio * sheetCount;
            
            // Update the readonly quantity display
            document.getElementById('QuantityDisplay').value = totalQuantity;
            document.getElementById('QuantityOrdered').value = totalQuantity;
            
            // Update the material info display
            if (product.ratio > 0) {
                document.getElementById('material-info').textContent = 
                    `Each sheet produces ${product.ratio} units of ${productName}. Total: ${totalQuantity} units.`;
                document.getElementById('material-info').style.color = '#008800';
            } else {
                document.getElementById('material-info').textContent = 
                    `Unknown product ratio. Please contact administrator.`;
                document.getElementById('material-info').style.color = '#FF0000';
            }
            
            // Display current stock information
            const stockInfo = document.getElementById('stock-info');
            
            if (productID) {
                const stockAmount = product.stock;
                stockInfo.textContent = `Available stock: ${stockAmount} sheets`;
                
                // Change color based on if there's enough stock
                if (stockAmount < sheetCount) {
                    stockInfo.style.color = '#FF0000';
                } else {
                    stockInfo.style.color = '#008800';
                }
            } else {
                stockInfo.textContent = '';
            }
            
            // Calculate and display weight information
            const weightInfo = document.getElementById('weight-info');
            if (productID) {
                const totalWeight = product.weight * totalQuantity;
                weightInfo.textContent = `Total product weight: ${totalWeight} ${product.weightUnit}`;
                
                // If warehouse is selected, calculate remaining capacity
                if (warehouseID && warehouseData[warehouseID]) {
                    const warehouse = warehouseData[warehouseID];
                    const convertedWeight = convertWeight(totalWeight, product.weightUnit, warehouse.weightUnit);
                    const remainingCapacity = Math.max(0, warehouse.capacity - warehouse.currentUsage);
                    
                    weightInfo.textContent += ` (${convertedWeight} ${warehouse.weightUnit})`;
                    weightInfo.textContent += `\nWarehouse remaining capacity: ${remainingCapacity.toFixed(2)} ${warehouse.weightUnit}`;
                    
                    // Color based on if the warehouse has enough capacity
                    if (convertedWeight > remainingCapacity) {
                        weightInfo.style.color = '#FF0000';
                    } else {
                        weightInfo.style.color = '#008800';
                    }
                }
            } else {
                weightInfo.textContent = '';
            }
        }
        
        // Initialize when page loads
        window.onload = function() {
            // Only run update if a product is selected (this prevents calculations when page first loads)
            const productSelect = document.getElementById('ProductID');
            if (productSelect.value) {
                updateOrderInfo();
            }
        };

        // Add tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    button.classList.add('active');
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>