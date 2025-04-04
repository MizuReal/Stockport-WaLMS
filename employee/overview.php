<?php
session_start();
include '../server/database.php';
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';

// Raw Materials Overview
$rawMaterialsQuery = "SELECT 
    COUNT(*) as TotalMaterials,
    SUM(QuantityInStock) as TotalStock,
    SUM(QuantityInStock * UnitCost) as TotalValue,
    COUNT(CASE WHEN QuantityInStock < MinimumStock THEN 1 END) as LowStockCount
    FROM rawmaterials";
$rawMaterialsResult = $conn->query($rawMaterialsQuery);
$rawMaterialsData = $rawMaterialsResult->fetch_assoc();

// Products Overview
$productsQuery = "SELECT 
    COUNT(*) as TotalProducts,
    COUNT(DISTINCT Category) as CategoryCount
    FROM products";
$productsResult = $conn->query($productsQuery);
$productsData = $productsResult->fetch_assoc();

// Production Orders Overview
$productionQuery = "SELECT 
    COUNT(*) as TotalOrders,
    COUNT(CASE WHEN Status = 'Planned' THEN 1 END) as PlannedCount,
    COUNT(CASE WHEN Status = 'In Progress' THEN 1 END) as InProgressCount,
    COUNT(CASE WHEN Status = 'Completed' THEN 1 END) as CompletedCount,
    SUM(QuantityOrdered) as TotalQuantityOrdered,
    SUM(QuantityProduced) as TotalQuantityProduced
    FROM productionorders";
$productionResult = $conn->query($productionQuery);
$productionData = $productionResult->fetch_assoc();

// Recent materials with critical stock levels
$criticalStockQuery = "SELECT 
    MaterialName,
    QuantityInStock,
    MinimumStock,
    raw_warehouse,
    (MinimumStock - QuantityInStock) as Shortage
    FROM rawmaterials
    WHERE QuantityInStock < MinimumStock
    ORDER BY (MinimumStock - QuantityInStock) DESC
    LIMIT 5";
$criticalStockResult = $conn->query($criticalStockQuery);

// Top products by production quantity
$topProductsQuery = "SELECT 
    p.ProductName,
    p.Category,
    SUM(po.QuantityOrdered) as TotalOrdered
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    GROUP BY p.ProductID
    ORDER BY TotalOrdered DESC
    LIMIT 5";
$topProductsResult = $conn->query($topProductsQuery);

// Recent production orders
$recentOrdersQuery = "SELECT 
    po.OrderID,
    p.ProductName,
    po.Status,
    po.StartDate,
    po.EndDate,
    po.QuantityOrdered,
    po.QuantityProduced,
    e.FirstName,
    e.LastName
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    JOIN employees e ON po.EmployeeID = e.EmployeeID
    ORDER BY po.StartDate DESC
    LIMIT 5";
$recentOrdersResult = $conn->query($recentOrdersQuery);

// Update Warehouse utilization query
$warehouseQuery = "SELECT 
    pw.productWarehouse,
    pw.Section,
    pw.Capacity,
    pw.current_usage,
    pw.warehouse_weight_unit,
    GREATEST(0, pw.Capacity - pw.current_usage) as available_capacity,
    (pw.current_usage / pw.Capacity * 100) as usage_percentage
    FROM products_warehouse pw";
$warehouseResult = $conn->query($warehouseQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <title>System Overview</title>
    <style>
        .main-content {
            margin-left: 60px; /* Initial state matches collapsed sidebar */
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        body.sidebar-expanded .main-content {
            margin-left: 220px; /* Expanded state matches sidebar width */
        }

        body.sidebar-hidden .main-content {
            margin-left: 20px; /* Fully collapsed state */
        }

        /* Keep your existing styles... */
    </style>
</head>
<body>
    <?php renderSidebar('overview'); ?>

    <div class="main-content">
        <?php renderHeader('System Overview'); ?>

        <!-- Overall Stats -->
        <div class="dashboard-grid" style="padding-top: 20px;">
            <!-- Raw Materials Summary -->
            <div class="card">
                <div class="card-header">Raw Materials</div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $rawMaterialsData['TotalMaterials']; ?>
                        </div>
                        <div class="stat-label">Types</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo number_format($rawMaterialsData['TotalStock']); ?>
                        </div>
                        <div class="stat-label">Units in Stock</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            ₱<?php echo number_format($rawMaterialsData['TotalValue'], 0); ?>
                        </div>
                        <div class="stat-label">Total Value</div>
                    </div>
                </div>
            </div>

            <!-- Production Summary -->
            <div class="card">
                <div class="card-header">Production</div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $productionData['TotalOrders']; ?>
                        </div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $productionData['InProgressCount']; ?>
                        </div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                                if ($productionData['TotalQuantityOrdered'] > 0) {
                                    echo round(($productionData['TotalQuantityProduced'] / $productionData['TotalQuantityOrdered']) * 100) . '%';
                                } else {
                                    echo '0%';
                                }
                            ?>
                        </div>
                        <div class="stat-label">Completion Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row Stats -->
        <div class="dashboard-grid">
            <!-- Products Summary -->
            <div class="card">
                <div class="card-header">Products</div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $productsData['TotalProducts']; ?>
                        </div>
                        <div class="stat-label">Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $productsData['CategoryCount']; ?>
                        </div>
                        <div class="stat-label">Categories</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $rawMaterialsData['LowStockCount']; ?>
                        </div>
                        <div class="stat-label">Low Stock Alerts</div>
                    </div>
                </div>
            </div>

            <!-- Warehouse Status -->
            <div class="card">
                <div class="card-header">Warehouse Status</div>
                <div class="stat-grid">
                    <?php 
                    $totalCapacity = 0;
                    $totalUsage = 0;
                    $totalWarehouses = 0;
                    
                    if ($warehouseResult && $warehouseResult->num_rows > 0) {
                        while ($row = $warehouseResult->fetch_assoc()) {
                            $totalCapacity += $row['Capacity'];
                            $totalUsage += $row['current_usage'];
                            $totalWarehouses++;
                        }
                        $overallUtilization = $totalCapacity > 0 ? ($totalUsage / $totalCapacity) * 100 : 0;
                        // Reset pointer for later use
                        $warehouseResult->data_seek(0);
                    }
                    ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalWarehouses; ?></div>
                        <div class="stat-label">Warehouses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($totalUsage, 2); ?></div>
                        <div class="stat-label">Total Usage</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($overallUtilization, 1); ?>%</div>
                        <div class="stat-label">Utilization</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Stock Levels -->
        <div class="card">
            <div class="card-header">Critical Stock Levels</div>
            <?php if ($criticalStockResult && $criticalStockResult->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Warehouse</th>
                            <th>Current Stock</th>
                            <th>Minimum Required</th>
                            <th>Shortage</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $criticalStockResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['MaterialName']); ?></td>
                                <td><?php echo htmlspecialchars($row['raw_warehouse']); ?></td>
                                <td><?php echo number_format($row['QuantityInStock']); ?></td>
                                <td><?php echo number_format($row['MinimumStock']); ?></td>
                                <td><?php echo number_format($row['Shortage']); ?></td>
                                <td>
                                    <span class="status-badge critical">Critical</span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert-success">
                    All materials are at adequate stock levels.
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Production Orders -->
        <div class="card">
            <div class="card-header">Recent Production Orders</div>
            <?php if ($recentOrdersResult && $recentOrdersResult->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>Assigned To</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentOrdersResult->fetch_assoc()): ?>
                            <tr>
                                <td>ORD-<?php echo sprintf('%03d', $row['OrderID']); ?></td>
                                <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                <td>
                                    <?php 
                                        $statusClass = '';
                                        switch ($row['Status']) {
                                            case 'Planned':
                                                $statusClass = 'planned';
                                                break;
                                            case 'In Progress':
                                                $statusClass = 'in-progress';
                                                break;
                                            case 'Completed':
                                                $statusClass = 'completed';
                                                break;
                                            case 'Cancelled':
                                                $statusClass = 'cancelled';
                                                break;
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $row['Status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['StartDate'])); ?></td>
                                <td><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></td>
                                <td>
                                    <?php 
                                        $progressPercent = 0;
                                        if ($row['QuantityOrdered'] > 0) {
                                            $progressPercent = round(($row['QuantityProduced'] / $row['QuantityOrdered']) * 100);
                                        }
                                    ?>
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo $progressPercent; ?>%"></div>
                                    </div>
                                    <div class="progress-text">
                                        <?php echo $row['QuantityProduced'] . '/' . $row['QuantityOrdered']; ?>
                                        (<?php echo $progressPercent; ?>%)
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert">
                    No recent production orders found.
                </div>
            <?php endif; ?>
        </div>

        <!-- Warehouse Utilization -->
        <div class="card">
            <div class="card-header">Warehouse Utilization</div>
            <?php if ($warehouseResult && $warehouseResult->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Section</th>
                            <th>Total Capacity</th>
                            <th>Current Usage</th>
                            <th>Available</th>
                            <th>Utilization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $warehouseResult->fetch_assoc()): ?>
                            <?php
                                $utilizationPercent = $row['usage_percentage'];
                                
                                $utilizationClass = '';
                                if ($utilizationPercent >= 90) {
                                    $utilizationClass = 'critical';
                                } elseif ($utilizationPercent >= 75) {
                                    $utilizationClass = 'warning';
                                } else {
                                    $utilizationClass = 'normal';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['productWarehouse']); ?></td>
                                <td><?php echo htmlspecialchars($row['Section']); ?></td>
                                <td><?php echo number_format($row['Capacity'], 2) . ' ' . $row['warehouse_weight_unit']; ?></td>
                                <td><?php echo number_format($row['current_usage'], 2) . ' ' . $row['warehouse_weight_unit']; ?></td>
                                <td><?php echo number_format($row['available_capacity'], 2) . ' ' . $row['warehouse_weight_unit']; ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress <?php echo $utilizationClass; ?>" 
                                             style="width: <?php echo min(100, max(0, $utilizationPercent)); ?>%"></div>
                                    </div>
                                    <div class="progress-text">
                                        <?php echo number_format($utilizationPercent, 1); ?>%
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert">
                    No warehouse information available.
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Products -->
        <div class="card">
            <div class="card-header">Top Products by Production Volume</div>
            <?php if ($topProductsResult && $topProductsResult->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Total Ordered Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $topProductsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                <td><?php echo htmlspecialchars($row['Category']); ?></td>
                                <td><?php echo number_format($row['TotalOrdered']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert">
                    No production data available.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add this CSS for the progress bars and status badges -->
    <style>
        .progress-bar {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 4px;
            height: 8px;
            margin-bottom: 4px;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #4CAF50;
        }
        
        .progress.warning {
            background-color: #ff9800;
        }
        
        .progress.critical {
            background-color: #f44336;
        }
        
        .progress-text {
            font-size: 0.8rem;
            color: #666;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }
        
        .status-badge.planned {
            background-color: #9E9E9E;
        }
        
        .status-badge.in-progress {
            background-color: #2196F3;
        }
        
        .status-badge.completed {
            background-color: #4CAF50;
        }
        
        .status-badge.cancelled {
            background-color: #F44336;
        }
        
        .status-badge.critical {
            background-color: #F44336;
        }
        
        .alert-success {
            padding: 15px;
            background-color: #dff0d8;
            border-left: 5px solid #4CAF50;
            color: #3c763d;
            margin: 15px 0;
        }
    </style>
</body>
</html>