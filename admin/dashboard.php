<?php
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// Get total number of customers
$customerQuery = "SELECT COUNT(*) as totalCustomers FROM customers WHERE customer_status = 'approved'";
$customerResult = $conn->query($customerQuery);
$totalCustomers = $customerResult->fetch_assoc()['totalCustomers'];

// Get recent orders
$recentOrdersQuery = "SELECT co.CustomerOrderID, c.CustomerName, co.OrderDate, co.TotalAmount, co.Status 
                      FROM customerorders co
                      JOIN customers c ON co.CustomerID = c.CustomerID
                      ORDER BY co.OrderDate DESC LIMIT 5";
$recentOrdersResult = $conn->query($recentOrdersQuery);

// Get recent shipments/deliveries
$recentShipmentsQuery = "SELECT dt.delivery_id, c.CustomerName, dt.delivery_date, dt.delivery_note, p.ProductName, od.Quantity 
                         FROM delivery_tracking dt
                         JOIN orderdetails od ON dt.order_detail_id = od.OrderDetailID
                         JOIN customerorders co ON od.CustomerOrderID = co.CustomerOrderID
                         JOIN customers c ON co.CustomerID = c.CustomerID
                         JOIN products p ON od.ProductID = p.ProductID
                         ORDER BY dt.delivery_date DESC LIMIT 5";
$recentShipmentsResult = $conn->query($recentShipmentsQuery);

// Get current products (changed from low inventory products)
$productsQuery = "SELECT p.ProductID, p.ProductName, p.Category, p.Weight, p.weight_unit, p.SellingPrice, pw.productWarehouse 
                  FROM products p
                  LEFT JOIN products_warehouse pw ON p.LocationID = pw.productLocationID
                  ORDER BY p.Category, p.ProductName ASC";
$productsResult = $conn->query($productsQuery);

// Get current raw materials (changed from low raw materials)
$materialsQuery = "SELECT MaterialID, MaterialName, SupplierID, QuantityInStock, UnitCost, raw_warehouse 
                   FROM rawmaterials 
                   ORDER BY MaterialName ASC";
$materialsResult = $conn->query($materialsQuery);

// Get staff overview
$staffQuery = "SELECT EmployeeID, CONCAT(FirstName, ' ', LastName) as EmployeeName, Role, Status 
               FROM employees
               ORDER BY Status ASC, Role ASC";
$staffResult = $conn->query($staffQuery);

// Get recent production orders with material info
$recentProductionQuery = "SELECT po.OrderID, p.ProductName, e.FirstName, e.LastName, po.StartDate, po.Status, 
                           po.QuantityOrdered, po.QuantityProduced, pw.productWarehouse, rm.MaterialName
                          FROM productionorders po
                          JOIN products p ON po.ProductID = p.ProductID
                          JOIN employees e ON po.EmployeeID = e.EmployeeID
                          JOIN products_warehouse pw ON po.warehouseID = pw.productLocationID
                          LEFT JOIN rawmaterials rm ON p.MaterialID = rm.MaterialID
                          ORDER BY po.StartDate DESC LIMIT 5";
$recentProductionResult = $conn->query($recentProductionQuery);

// Get warehouse capacity overview
$warehouseCapacityQuery = "SELECT productWarehouse, Section, Capacity, current_usage, remaining_capacity
                           FROM products_warehouse
                           ORDER BY remaining_capacity ASC";
$warehouseCapacityResult = $conn->query($warehouseCapacityQuery);

// Get low raw materials count for stats card
$lowMaterialsCountQuery = "SELECT COUNT(*) as count FROM rawmaterials 
                          WHERE QuantityInStock <= MinimumStock";
$lowMaterialsCountResult = $conn->query($lowMaterialsCountQuery);
$lowMaterialsCount = $lowMaterialsCountResult->fetch_assoc()['count'];
?>

<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dashboard-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            position: relative;
            overflow: visible;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            width: calc(100% - 250px);
            box-sizing: border-box;
            overflow-y: auto;
            max-height: 100vh; /* Set a maximum height */
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin-top: 0;
            color: #555;
            font-size: 16px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #3a86ff;
            margin: 10px 0;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .dashboard-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .dashboard-section h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .status-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending { background-color: #ffd166; color: #7d6200; }
        .status-processing { background-color: #8ecae6; color: #034e7b; }
        .status-shipped { background-color: #a0c4ff; color: #023e8a; }
        .status-delivered { background-color: #80b918; color: #fff; }
        .status-cancelled { background-color: #d62828; color: #fff; }
        .status-completed { background-color: #80b918; color: #fff; }
        .status-inprogress { background-color: #8ecae6; color: #034e7b; }
        .status-planned { background-color: #ffd166; color: #7d6200; }
        
        .inventory-low { color: #d62828; font-weight: bold; }
        .inventory-warning { color: #f77f00; font-weight: bold; }
        .inventory-good { color: #80b918; }
        
        .view-all {
            display: block;
            text-align: right;
            margin-top: 10px;
            color: #3a86ff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>System Overview</h1>
            </header>
            <div class="content">
                <!-- Stats Overview -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Total Customers</h3>
                        <div class="number"><?php echo $totalCustomers; ?></div>
                    </div>
                    
                    <?php 
                    // Get pending orders count
                    $pendingOrdersQuery = "SELECT COUNT(*) as count FROM customerorders WHERE Status = 'Pending'";
                    $pendingOrdersResult = $conn->query($pendingOrdersQuery);
                    $pendingOrders = $pendingOrdersResult->fetch_assoc()['count'];
                    ?>
                    <div class="stat-card">
                        <h3>Pending Orders</h3>
                        <div class="number"><?php echo $pendingOrders; ?></div>
                    </div>
                    
                    <?php 
                    // Get active production orders count
                    $activeProductionQuery = "SELECT COUNT(*) as count FROM productionorders WHERE Status = 'In Progress'";
                    $activeProductionResult = $conn->query($activeProductionQuery);
                    $activeProduction = $activeProductionResult->fetch_assoc()['count'];
                    ?>
                    <div class="stat-card">
                        <h3>Active Production</h3>
                        <div class="number"><?php echo $activeProduction; ?></div>
                    </div>
                    
                    <!-- Changed from low inventory to low raw materials -->
                    <div class="stat-card">
                        <h3>Low Raw Materials</h3>
                        <div class="number"><?php echo $lowMaterialsCount; ?></div>
                    </div>
                </div>
                
                <!-- Two Column Layout for Recent Orders and Shipments -->
                <div class="dashboard-sections">
                    <!-- Recent Orders -->
                    <div class="dashboard-section">
                        <h2>Recent Orders</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentOrdersResult->num_rows > 0): ?>
                                    <?php while($order = $recentOrdersResult->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $order['CustomerOrderID']; ?></td>
                                            <td><?php echo $order['CustomerName']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                                            <td>₱<?php echo number_format($order['TotalAmount'], 2); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo strtolower($order['Status']); ?>">
                                                    <?php echo $order['Status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No recent orders found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Recent Shipments -->
                    <div class="dashboard-section">
                        <h2>Recent Shipments</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Delivery Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentShipmentsResult && $recentShipmentsResult->num_rows > 0): ?>
                                    <?php while($shipment = $recentShipmentsResult->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $shipment['delivery_id']; ?></td>
                                            <td><?php echo $shipment['CustomerName']; ?></td>
                                            <td><?php echo $shipment['ProductName']; ?></td>
                                            <td><?php echo $shipment['Quantity']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($shipment['delivery_date'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No recent shipments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <a href="outgoing_shipments.php" class="view-all">View All Shipments →</a>
                    </div>
                </div>
                
                <!-- Recent Production Orders and Warehouse Capacity -->
                <div class="dashboard-sections">
                    <!-- Recent Production Orders (Added material column) -->
                    <div class="dashboard-section">
                        <h2>Recent Production Orders</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product</th>
                                    <th>Material</th>
                                    <th>Assigned To</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentProductionResult->num_rows > 0): ?>
                                    <?php while($production = $recentProductionResult->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $production['OrderID']; ?></td>
                                            <td><?php echo $production['ProductName']; ?></td>
                                            <td><?php echo $production['MaterialName'] ?: 'Not specified'; ?></td>
                                            <td><?php echo $production['FirstName'] . ' ' . $production['LastName']; ?></td>
                                            <td><?php echo $production['QuantityProduced'] . '/' . $production['QuantityOrdered']; ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo strtolower(str_replace(' ', '', $production['Status'])); ?>">
                                                    <?php echo $production['Status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No recent production orders found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <a href="incoming_materials.php" class="view-all">View All Production Orders →</a>
                    </div>
                    
                    <!-- Warehouse Capacity -->
                    <div class="dashboard-section">
                        <h2>Warehouse Capacity Overview</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Section</th>
                                    <th>Capacity</th>
                                    <th>Usage</th>
                                    <th>Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($warehouseCapacityResult->num_rows > 0): ?>
                                    <?php while($warehouse = $warehouseCapacityResult->fetch_assoc()): 
                                        $usagePercent = ($warehouse['current_usage'] / $warehouse['Capacity']) * 100;
                                        $statusClass = 'inventory-good';
                                        
                                        if ($usagePercent > 90) {
                                            $statusClass = 'inventory-low';
                                        } else if ($usagePercent > 70) {
                                            $statusClass = 'inventory-warning';
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $warehouse['productWarehouse']; ?></td>
                                            <td><?php echo $warehouse['Section']; ?></td>
                                            <td><?php echo number_format($warehouse['Capacity']) . ' kg'; ?></td>
                                            <td class="<?php echo $statusClass; ?>">
                                                <?php echo number_format($warehouse['current_usage']) . ' kg'; ?>
                                                (<?php echo round($usagePercent, 1); ?>%)
                                            </td>
                                            <td><?php echo number_format($warehouse['remaining_capacity']) . ' kg'; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No warehouse data found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <a href="product_warehouse.php" class="view-all">View All Warehouses →</a>
                    </div>
                </div>
                
                <!-- Current Products and Raw Materials List (Changed from Inventory Alerts) -->
                <div class="dashboard-sections">
                    <!-- Current Products List -->
                    <div class="dashboard-section">
                        <h2>Current Products</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Weight</th>
                                    <th>Price</th>
                                    <th>Warehouse</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($productsResult && $productsResult->num_rows > 0): ?>
                                    <?php while($product = $productsResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $product['ProductName']; ?></td>
                                            <td><?php echo $product['Category']; ?></td>
                                            <td><?php echo $product['Weight'] . ' ' . $product['weight_unit']; ?></td>
                                            <td>₱<?php echo number_format($product['SellingPrice'], 2); ?></td>
                                            <td><?php echo $product['productWarehouse'] ?: 'Not Assigned'; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No products found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <a href="products.php" class="view-all">View Full Inventory →</a>
                    </div>
                    
                    <!-- Current Raw Materials List -->
                    <div class="dashboard-section">
                        <h2>Current Raw Materials</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Warehouse</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Supplier ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($materialsResult && $materialsResult->num_rows > 0): ?>
                                    <?php while($material = $materialsResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $material['MaterialName']; ?></td>
                                            <td><?php echo $material['raw_warehouse']; ?></td>
                                            <td><?php echo number_format($material['QuantityInStock']); ?></td>
                                            <td>₱<?php echo number_format($material['UnitCost'], 2); ?></td>
                                            <td>#<?php echo $material['SupplierID']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No raw materials found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <a href="raw_materials.php" class="view-all">Manage Raw Materials →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>