<?php
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();
$current_page = basename($_SERVER['PHP_SELF']);

// Add new queries for profit and expenses calculation
$profitQuery = "SELECT 
    c.CustomerName,
    COUNT(co.CustomerOrderID) as TotalOrders,
    SUM(od.Quantity * od.UnitPrice) as TotalRevenue,
    SUM(od.Quantity * p.ProductionCost) as TotalCost,
    SUM(od.Quantity * (od.UnitPrice - p.ProductionCost)) as TotalProfit
    FROM customers c
    JOIN customerorders co ON c.CustomerID = co.CustomerID
    JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
    JOIN products p ON od.ProductID = p.ProductID
    WHERE co.Status != 'Cancelled'
    GROUP BY c.CustomerID, c.CustomerName
    ORDER BY TotalProfit DESC";
$profitResult = $conn->query($profitQuery);

// Calculate total expenses (sum of raw materials cost and production costs)
$expensesQuery = "SELECT 
    SUM(rm.UnitCost * rm.QuantityInStock) as RawMaterialsCost,
    SUM(p.ProductionCost * p.minimum_quantity) as ProductionCost
    FROM rawmaterials rm, products p";
$expensesResult = $conn->query($expensesQuery);
$expensesData = $expensesResult->fetch_assoc();
$totalExpenses = $expensesData['RawMaterialsCost'] + $expensesData['ProductionCost'];

// Fetch raw materials analytics
$rawMaterialsQuery = "SELECT 
    MaterialName,
    QuantityInStock,
    MinimumStock,
    UnitCost,
    (QuantityInStock * UnitCost) as TotalValue,
    LastRestockedDate,
    raw_warehouse
    FROM rawmaterials";
$rawMaterialsResult = $conn->query($rawMaterialsQuery);

// Fetch production orders analytics with correct join
$productionQuery = "SELECT 
    p.ProductName,
    po.Status,
    COUNT(*) as OrderCount,
    SUM(po.QuantityOrdered) as TotalOrdered,
    SUM(po.QuantityProduced) as TotalProduced
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    GROUP BY p.ProductName, po.Status";
$productionResult = $conn->query($productionQuery);

// Fetch customer orders analytics - Note: No customer orders in current DB
$orderQuery = "SELECT 
    Status,
    COUNT(*) as OrderCount,
    SUM(TotalAmount) as TotalRevenue
    FROM customerorders
    GROUP BY Status";
$orderResult = $conn->query($orderQuery);

// Fetch product inventory analytics - Using actual minimum_quantity field
$inventoryQuery = "SELECT 
    p.ProductID,
    p.ProductName,
    p.Category,
    p.minimum_quantity as Quantity,
    p.Weight,
    p.weight_unit,
    pw.productWarehouse,
    pw.Section,
    (p.minimum_quantity * p.ProductionCost) as InventoryValue
    FROM products p
    JOIN products_warehouse pw ON p.LocationID = pw.productLocationID
    ORDER BY p.Category, p.ProductName";
$inventoryResult = $conn->query($inventoryQuery);

// Calculate active orders count
$activeOrdersQuery = "SELECT COUNT(*) as activeOrders FROM productionorders WHERE Status = 'In Progress' OR Status = 'Planned'";
$activeOrdersResult = $conn->query($activeOrdersQuery);
$activeOrdersData = $activeOrdersResult->fetch_assoc();
$activeOrders = $activeOrdersData['activeOrders'];

// Get total product inventory value
$productValueQuery = "SELECT SUM(minimum_quantity * ProductionCost) as TotalValue FROM products";
$productValueResult = $conn->query($productValueQuery);
$productValueData = $productValueResult->fetch_assoc();
$productInventoryValue = $productValueData['TotalValue'] ?: 0;

// Modify low stock calculation to only check raw materials
$lowStock = 0;
$rawMaterialsResult->data_seek(0);
while($row = $rawMaterialsResult->fetch_assoc()) {
    if($row['QuantityInStock'] <= $row['MinimumStock']) {
        $lowStock++;
    }
}

// Get products by category for category distribution chart
$categoryQuery = "SELECT Category, COUNT(*) as ProductCount FROM products GROUP BY Category ORDER BY ProductCount DESC";
$categoryResult = $conn->query($categoryQuery);

// Update warehouse distribution query to use current usage and capacity
$warehouseQuery = "SELECT 
    pw.productWarehouse,
    pw.Capacity,
    pw.current_usage,
    pw.warehouse_weight_unit,
    (pw.current_usage / pw.Capacity * 100) as usage_percentage
    FROM products_warehouse pw
    ORDER BY pw.productWarehouse";
$warehouseResult = $conn->query($warehouseQuery);

// Update production orders status query to get more concise details
$productionStatusQuery = "SELECT 
    po.Status,
    COUNT(*) as StatusCount,
    SUM(po.QuantityOrdered) as TotalQuantity,
    GROUP_CONCAT(
        CONCAT(p.ProductName, ': ', po.QuantityOrdered)
        ORDER BY po.OrderID DESC
        SEPARATOR '\\n'
        LIMIT 3
    ) as OrderDetails
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    WHERE po.Status != 'Cancelled'
    GROUP BY po.Status";
$productionStatusResult = $conn->query($productionStatusQuery);

// Get current active production order details
$currentOrderQuery = "SELECT 
    p.ProductName,
    po.QuantityOrdered,
    po.Status
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    WHERE po.Status = 'In Progress'
    ORDER BY po.OrderID DESC
    LIMIT 1";
$currentOrderResult = $conn->query($currentOrderQuery);
$currentOrderData = $currentOrderResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Main page container styles - FIXED */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto; /* Ensure vertical scrolling is enabled */
        }

        /* Dashboard container - FIXED */
        .dashboard-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            position: relative;
            overflow: visible; /* Allow content to overflow and be scrollable */
        }

        /* Main content area - FIXED */
        .main-content {
            flex: 1;
            padding: 20px;
            width: calc(100% - 250px);
            box-sizing: border-box;
            overflow-y: auto;
            max-height: 100vh; /* Set a maximum height */
        }

        /* Analytics styles */
        .analytics-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .analytics-card:hover {
            transform: translateY(-5px);
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .metric-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
            width: 100%;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        .table-responsive {
            margin-top: 20px;
            width: 100%;
            overflow-x: auto;
        }
        .low-stock {
            background-color: #ffeeee;
        }
        .critical-stock {
            background-color: #ffdddd;
        }
        .card-title {
            margin-bottom: 15px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Header styling */
        header {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        /* Responsive adjustments - FIXED */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 220px; /* Match the responsive sidebar width */
                width: calc(100% - 220px);
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header class="d-flex justify-content-between align-items-center" style="padding: 0 20px;">
                <h1>Reports & Analytics</h1>
                <form action="download_pdf.php" method="post" target="_blank">
                    <input type="hidden" name="report_type" value="analytics">
                    <button type="submit" name="download_pdf" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download as PDF
                    </button>
                </form>
            </header>
            <div class="container-fluid p-0">

                <!-- Quick Stats Row -->
                <div class="row mb-4">
                    <?php
                    // Raw Materials Stats
                    $totalMaterials = $rawMaterialsResult->num_rows;
                    $totalValue = 0;
                    $lowStock = 0;
                    
                    // Reset pointer to beginning of result
                    $rawMaterialsResult->data_seek(0);
                    
                    while($row = $rawMaterialsResult->fetch_assoc()) {
                        $totalValue += $row['TotalValue'];
                        if($row['QuantityInStock'] <= $row['MinimumStock']) {
                            $lowStock++;
                        }
                    }
                    
                    // Add product inventory value to total
                    $totalValue += $productInventoryValue;

                    // Get product count
                    $inventoryResult->data_seek(0);
                    $productCount = $inventoryResult->num_rows;
                    ?>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h6 class="metric-label">Total Materials & Products</h6>
                                <div class="metric-value"><?php echo ($totalMaterials + $productCount); ?></div>
                                <div class="small text-muted mt-2">
                                    <?php echo $totalMaterials; ?> Materials, <?php echo $productCount; ?> Products
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h6 class="metric-label">Total Inventory Value</h6>
                                <div class="metric-value">₱<?php echo number_format($totalValue, 2); ?></div>
                                <div class="small text-muted mt-2">
                                    Raw materials and finished products
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h6 class="metric-label">Low Stock Raw Materials</h6>
                                <div class="metric-value"><?php echo $lowStock; ?></div>
                                <div class="small text-muted mt-2">
                                    Materials below minimum level
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h6 class="metric-label">Active Orders</h6>
                                <div class="metric-value"><?php echo $activeOrders; ?></div>
                                <div class="small text-muted mt-2">
                                    In Progress or Planned
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit and Expenses Row -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Customer Profit Analysis</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Orders</th>
                                                <th>Revenue</th>
                                                <th>Cost</th>
                                                <th>Net Profit</th>
                                                <th>Margin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalRevenue = 0;
                                            $totalCost = 0;
                                            $totalProfit = 0;
                                            while($row = $profitResult->fetch_assoc()) {
                                                $margin = ($row['TotalRevenue'] > 0) 
                                                    ? ($row['TotalProfit'] / $row['TotalRevenue'] * 100) 
                                                    : 0;
                                                $totalRevenue += $row['TotalRevenue'];
                                                $totalCost += $row['TotalCost'];
                                                $totalProfit += $row['TotalProfit'];
                                                
                                                echo "<tr>
                                                    <td>{$row['CustomerName']}</td>
                                                    <td>{$row['TotalOrders']}</td>
                                                    <td>₱" . number_format($row['TotalRevenue'], 2) . "</td>
                                                    <td>₱" . number_format($row['TotalCost'], 2) . "</td>
                                                    <td>₱" . number_format($row['TotalProfit'], 2) . "</td>
                                                    <td>" . number_format($margin, 1) . "%</td>
                                                </tr>";
                                            }
                                            $overallMargin = ($totalRevenue > 0) ? ($totalProfit / $totalRevenue * 100) : 0;
                                            ?>
                                            <tr class="table-info">
                                                <td><strong>Totals</strong></td>
                                                <td></td>
                                                <td><strong>₱<?php echo number_format($totalRevenue, 2); ?></strong></td>
                                                <td><strong>₱<?php echo number_format($totalCost, 2); ?></strong></td>
                                                <td><strong>₱<?php echo number_format($totalProfit, 2); ?></strong></td>
                                                <td><strong><?php echo number_format($overallMargin, 1); ?>%</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Raw Materials Chart -->
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h5 class="card-title">Raw Materials Stock Level</h5>
                                <div class="chart-container">
                                    <canvas id="rawMaterialsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Category Distribution -->
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h5 class="card-title">Product Categories</h5>
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Charts Row -->
                <div class="row mb-4">
                    <!-- Warehouse Distribution -->
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h5 class="card-title">Product Distribution by Warehouse</h5>
                                <div class="chart-container">
                                    <canvas id="warehouseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Production Status Chart -->
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-body">
                                <h5 class="card-title">Production Order Status</h5>
                                <div class="chart-container">
                                    <canvas id="productionStatusChart"></canvas>
                                </div>
                                <div class="text-center mt-3">
                                    <?php if ($currentOrderData): ?>
                                        <span class="badge bg-info">
                                            Current: <?php echo htmlspecialchars($currentOrderData['QuantityOrdered']); ?> 
                                            <?php echo htmlspecialchars($currentOrderData['ProductName']); ?> 
                                            (<?php echo htmlspecialchars($currentOrderData['Status']); ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Raw Materials Table -->
                <div class="card analytics-card">
                    <div class="card-body">
                        <h5 class="card-title">Raw Materials Inventory</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Material</th>
                                        <th>Warehouse</th>
                                        <th>Quantity</th>
                                        <th>Minimum Stock</th>
                                        <th>Status</th>
                                        <th>Last Restocked</th>
                                        <th>Unit Cost</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rawMaterialsResult->data_seek(0);
                                    while($row = $rawMaterialsResult->fetch_assoc()) {
                                        $stockRatio = $row['QuantityInStock'] / $row['MinimumStock'];
                                        $rowClass = '';
                                        
                                        if ($stockRatio <= 0.75) {
                                            $rowClass = 'critical-stock';
                                            $status = '<span class="badge bg-danger">Critical Stock</span>';
                                        } else if ($stockRatio <= 1) {
                                            $rowClass = 'low-stock';
                                            $status = '<span class="badge bg-warning text-dark">Low Stock</span>';
                                        } else {
                                            $status = '<span class="badge bg-success">Adequate</span>';
                                        }
                                        
                                        echo "<tr class='{$rowClass}'>
                                            <td>{$row['MaterialName']}</td>
                                            <td>{$row['raw_warehouse']}</td>
                                            <td>{$row['QuantityInStock']}</td>
                                            <td>{$row['MinimumStock']}</td>
                                            <td>{$status}</td>
                                            <td>{$row['LastRestockedDate']}</td>
                                            <td>₱" . number_format($row['UnitCost'], 2) . "</td>
                                            <td>₱" . number_format($row['TotalValue'], 2) . "</td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Products Inventory Table - New table based on products -->
                <div class="card analytics-card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Products Inventory</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Warehouse</th>
                                        <th>Section</th>
                                        <th>Quantity Per Sheet</th>
                                        <th>Weight</th>
                                        <th>Production Cost</th>
                                        <th>Selling Price</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $inventoryResult->data_seek(0);
                                    
                                    // Group products by category for better readability
                                    $currentCategory = '';
                                    
                                    while($row = $inventoryResult->fetch_assoc()) {
                                        // Get production cost from products table
                                        $costQuery = "SELECT ProductionCost, SellingPrice FROM products WHERE ProductID = {$row['ProductID']}";
                                        $costResult = $conn->query($costQuery);
                                        $costData = $costResult->fetch_assoc();
                                        
                                        // Apply row background for categories
                                        $categoryClass = '';
                                        if ($currentCategory != $row['Category']) {
                                            $currentCategory = $row['Category'];
                                            $categoryClass = 'table-secondary';
                                        }
                                        
                                        // Highlight low stock items
                                        $stockClass = '';
                                        if ($row['Quantity'] <= 100) {
                                            $stockClass = 'low-stock';
                                        }
                                        if ($row['Quantity'] <= 50) {
                                            $stockClass = 'critical-stock';
                                        }
                                        
                                        $rowClass = $stockClass ? $stockClass : $categoryClass;
                                        
                                        echo "<tr class='{$rowClass}'>
                                            <td>{$row['ProductName']}</td>
                                            <td>{$row['Category']}</td>
                                            <td>{$row['productWarehouse']}</td>
                                            <td>{$row['Section']}</td>
                                            <td>{$row['Quantity']}</td>
                                            <td>{$row['Weight']} {$row['weight_unit']}</td>
                                            <td>₱" . number_format($costData['ProductionCost'], 2) . "</td>
                                            <td>₱" . number_format($costData['SellingPrice'], 2) . "</td>
                                            <td>₱" . number_format($row['InventoryValue'], 2) . "</td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Raw Materials Chart
        const rawMaterialsCtx = document.getElementById('rawMaterialsChart').getContext('2d');
        const rawMaterialsData = {
            labels: <?php 
                $rawMaterialsResult->data_seek(0);
                $labels = [];
                $data = [];
                $minStock = [];
                while($row = $rawMaterialsResult->fetch_assoc()) {
                    $labels[] = $row['MaterialName'];
                    $data[] = $row['QuantityInStock'];
                    $minStock[] = $row['MinimumStock'];
                }
                echo json_encode($labels);
            ?>,
            datasets: [{
                label: 'Current Stock',
                data: <?php echo json_encode($data); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Minimum Stock',
                data: <?php echo json_encode($minStock); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        };

        new Chart(rawMaterialsCtx, {
            type: 'bar',
            data: rawMaterialsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Raw Materials Inventory Levels'
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = {
            labels: <?php 
                $categoryResult->data_seek(0);
                $categoryLabels = [];
                $categoryCount = [];
                while($row = $categoryResult->fetch_assoc()) {
                    $categoryLabels[] = $row['Category'];
                    $categoryCount[] = $row['ProductCount'];
                }
                echo json_encode($categoryLabels);
            ?>,
            datasets: [{
                data: <?php echo json_encode($categoryCount); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)'
                ],
                borderWidth: 1
            }]
        };

        new Chart(categoryCtx, {
            type: 'pie',
            data: categoryData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Products by Category'
                    }
                }
            }
        });

        // Warehouse Distribution Chart
        const warehouseCtx = document.getElementById('warehouseChart').getContext('2d');
        const warehouseData = {
            labels: <?php 
                $warehouseResult->data_seek(0);
                $warehouseLabels = [];
                $warehouseUsage = [];
                $warehouseCapacity = [];
                while($row = $warehouseResult->fetch_assoc()) {
                    $warehouseLabels[] = $row['productWarehouse'];
                    $warehouseUsage[] = round($row['current_usage'], 2);
                    $warehouseCapacity[] = round($row['Capacity'], 2);
                }
                echo json_encode($warehouseLabels);
            ?>,
            datasets: [{
                label: 'Current Usage (' + <?php 
                    $warehouseResult->data_seek(0);
                    $row = $warehouseResult->fetch_assoc();
                    echo json_encode($row['warehouse_weight_unit']); 
                ?> + ')',
                data: <?php echo json_encode($warehouseUsage); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Total Capacity',
                data: <?php echo json_encode($warehouseCapacity); ?>,
                backgroundColor: 'rgba(255, 206, 86, 0.4)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1,
                type: 'line',
                fill: false
            }]
        };

        new Chart(warehouseCtx, {
            type: 'bar',
            data: warehouseData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Weight'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Warehouse Capacity and Usage'
                    }
                }
            }
        });

        // Production Status Chart
        const productionStatusCtx = document.getElementById('productionStatusChart').getContext('2d');
        const productionStatusData = {
            labels: <?php 
                $productionStatusResult->data_seek(0);
                $statusLabels = [];
                $statusCounts = [];
                $totalQuantities = [];
                $orderDetails = [];
                while($row = $productionStatusResult->fetch_assoc()) {
                    $statusLabels[] = $row['Status'];
                    $statusCounts[] = $row['StatusCount'];
                    $totalQuantities[] = $row['TotalQuantity'];
                    $orderDetails[] = $row['OrderDetails'];
                }
                echo json_encode($statusLabels);
            ?>,
            datasets: [{
                data: <?php echo json_encode($statusCounts); ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',  // In Progress
                    'rgba(255, 206, 86, 0.7)',  // Planned
                    'rgba(75, 192, 192, 0.7)'   // Completed
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 1
            }]
        };

        new Chart(productionStatusCtx, {
            type: 'doughnut',
            data: productionStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Production Orders by Status'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const quantity = <?php echo json_encode($totalQuantities); ?>[context.dataIndex];
                                const details = <?php echo json_encode($orderDetails); ?>[context.dataIndex];
                                
                                // Format the tooltip with line breaks
                                const lines = [
                                    `${label}: ${value} orders`,
                                    `Total Units: ${quantity}`,
                                    '───────────────',
                                    'Recent Orders:',
                                    ...details.split('\\n')
                                ];
                                
                                // Only show first few orders if there are many
                                if (details.split('\\n').length > 3) {
                                    lines.push('...');
                                }
                                
                                return lines;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>