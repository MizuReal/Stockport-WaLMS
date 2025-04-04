<?php
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once 'session_check.php';
requireActiveLogin();

// Database connection
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "stockport";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get inventory counts and stats
$totalProductsQuery = "SELECT SUM(QuantityInStock) as total_stock FROM rawmaterials";
$totalProductsResult = $conn->query($totalProductsQuery);
$totalStock = 0;
if ($totalProductsResult && $row = $totalProductsResult->fetch_assoc()) {
    $totalStock = $row['total_stock'];
}

// Get low stock alerts
$lowStockQuery = "SELECT COUNT(*) as low_stock_count FROM rawmaterials WHERE QuantityInStock < MinimumStock";
$lowStockResult = $conn->query($lowStockQuery);
$lowStockCount = 0;
if ($lowStockResult && $row = $lowStockResult->fetch_assoc()) {
    $lowStockCount = $row['low_stock_count'];
}

// Get pending order counts
$processingQuery = "SELECT COUNT(*) as processing_count FROM productionorders WHERE Status = 'In Progress'";
$processingResult = $conn->query($processingQuery);
$processingCount = 0;
if ($processingResult && $row = $processingResult->fetch_assoc()) {
    $processingCount = $row['processing_count'];
}

$shippedQuery = "SELECT COUNT(*) as shipped_count FROM productionorders WHERE Status = 'Completed' AND Delivery_Status = 0";
$shippedResult = $conn->query($shippedQuery);
$shippedCount = 0;
if ($shippedResult && $row = $shippedResult->fetch_assoc()) {
    $shippedCount = $row['shipped_count'];
}

// Get low stock alerts for display
$lowStockItemsQuery = "SELECT MaterialName, QuantityInStock, MinimumStock FROM rawmaterials WHERE QuantityInStock < MinimumStock LIMIT 3";
$lowStockItemsResult = $conn->query($lowStockItemsQuery);

// Get recent orders
$recentOrdersQuery = "SELECT p.OrderID, c.CustomerName, p.Status, p.StartDate, p.ProductID 
                      FROM productionorders p 
                      JOIN customers c ON p.EmployeeID = c.CustomerID 
                      ORDER BY p.StartDate DESC LIMIT 5";
$recentOrdersResult = $conn->query($recentOrdersQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <title>Inventory Management</title>
</head>
<body class="sidebar-expanded">
    <?php renderSidebar('inventory'); ?>
    
    <div class="content-wrapper">
        <?php renderHeader('Inventory Management'); ?>
        
        <!-- Quick Stats -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">Current Inventory Status</div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($totalStock); ?></div>
                        <div class="stat-label">Total Raw Material Stock</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $lowStockCount; ?></div>
                        <div class="stat-label">Low Stock Alerts</div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Pending Orders</div>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $processingCount; ?></div>
                        <div class="stat-label">Processing</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $shippedCount; ?></div>
                        <div class="stat-label">Ready to Ship</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <ul class="nav-tabs">
                <li class="tab-item active" data-tab="alerts">
                    <i class="fas fa-exclamation-triangle"></i> Alerts
                </li>
                <li class="tab-item" data-tab="raw-materials">
                    <i class="fas fa-boxes"></i> Raw Materials
                </li>
                <li class="tab-item" data-tab="products">
                    <i class="fas fa-box-open"></i> Products
                </li>
                <li class="tab-item" data-tab="orders">
                    <i class="fas fa-clipboard-list"></i> Recent Orders
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Alerts Tab -->
                <div id="alerts" class="tab-pane active">
                    <div class="card">
                        <div class="card-header">Low Stock Alerts</div>
                        <?php
                        if ($lowStockItemsResult && $lowStockItemsResult->num_rows > 0) {
                            while ($row = $lowStockItemsResult->fetch_assoc()) {
                                echo '<div class="alert">';
                                echo 'Low stock alert: ' . $row['MaterialName'] . ' - Current: ' . 
                                     $row['QuantityInStock'] . ', Minimum required: ' . $row['MinimumStock'];
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="alert">No low stock alerts at this time.</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Raw Materials Tab -->
                <div id="raw-materials" class="tab-pane">
                    <div class="card">
                        <div class="card-header">Raw Materials Inventory</div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Material ID</th>
                                    <th>Material Name</th>
                                    <th>Current Stock</th>
                                    <th>Min Stock</th>
                                    <th>Supplier</th>
                                    <th>Warehouse</th>
                                    <th>Last Restocked</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $materialsQuery = "SELECT r.MaterialID, r.MaterialName, r.QuantityInStock, 
                                              r.MinimumStock, s.SupplierName, r.raw_warehouse, r.LastRestockedDate 
                                              FROM rawmaterials r
                                              JOIN suppliers s ON r.SupplierID = s.SupplierID
                                              ORDER BY r.QuantityInStock < r.MinimumStock DESC, r.MaterialID ASC";
                            $materialsResult = $conn->query($materialsQuery);
                            
                            if ($materialsResult && $materialsResult->num_rows > 0) {
                                while ($row = $materialsResult->fetch_assoc()) {
                                    $rowClass = $row['QuantityInStock'] < $row['MinimumStock'] ? 'class="low-stock"' : '';
                                    echo "<tr $rowClass>";
                                    echo "<td>" . $row['MaterialID'] . "</td>";
                                    echo "<td>" . $row['MaterialName'] . "</td>";
                                    echo "<td>" . $row['QuantityInStock'] . "</td>";
                                    echo "<td>" . $row['MinimumStock'] . "</td>";
                                    echo "<td>" . $row['SupplierName'] . "</td>";
                                    echo "<td>" . $row['raw_warehouse'] . "</td>";
                                    echo "<td>" . $row['LastRestockedDate'] . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7'>No materials found</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Products Tab -->
                <div id="products" class="tab-pane">
                    <div class="card">
                        <div class="card-header">Products Inventory</div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Main Material</th>
                                    <th>Min Quantity</th>
                                    <th>Warehouse</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $productsQuery = "SELECT p.ProductID, p.ProductName, p.Category, r.MaterialName, 
                                             p.minimum_quantity, w.productWarehouse, p.SellingPrice
                                             FROM products p
                                             JOIN rawmaterials r ON p.MaterialID = r.MaterialID
                                             JOIN products_warehouse w ON p.LocationID = w.productLocationID
                                             ORDER BY p.ProductID ASC";
                            $productsResult = $conn->query($productsQuery);
                            
                            if ($productsResult && $productsResult->num_rows > 0) {
                                while ($row = $productsResult->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row['ProductID'] . "</td>";
                                    echo "<td>" . $row['ProductName'] . "</td>";
                                    echo "<td>" . $row['Category'] . "</td>";
                                    echo "<td>" . $row['MaterialName'] . "</td>";
                                    echo "<td>" . $row['minimum_quantity'] . "</td>";
                                    echo "<td>" . $row['productWarehouse'] . "</td>";
                                    echo "<td>₱" . number_format($row['SellingPrice'], 2) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7'>No products found</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div id="orders" class="tab-pane">
                    <div class="card">
                        <div class="card-header">Recent Production Orders</div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Quantity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $ordersQuery = "SELECT po.OrderID, p.ProductName, po.Status, po.StartDate, 
                                           po.QuantityOrdered, po.QuantityProduced
                                           FROM productionorders po
                                           JOIN products p ON po.ProductID = p.ProductID
                                           ORDER BY po.StartDate DESC LIMIT 5";
                            $ordersResult = $conn->query($ordersQuery);
                            
                            if ($ordersResult && $ordersResult->num_rows > 0) {
                                while ($row = $ordersResult->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>ORD-" . sprintf('%03d', $row['OrderID']) . "</td>";
                                    echo "<td>" . $row['ProductName'] . "</td>";
                                    echo "<td>" . $row['Status'] . "</td>";
                                    echo "<td>" . date('Y-m-d', strtotime($row['StartDate'])) . "</td>";
                                    echo "<td>" . $row['QuantityProduced'] . "/" . $row['QuantityOrdered'] . "</td>";
                                    echo "<td><a href='view_order.php?id=" . $row['OrderID'] . "' class='btn'>View</a></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No recent orders found</td></tr>";
                            }
                            
                            // Close the database connection
                            $conn->close();
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tabs-container {
            margin-top: 20px;
        }

        .nav-tabs {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            border-bottom: 2px solid #ddd;
            background: #fff;
        }

        .tab-item {
            padding: 12px 20px;
            cursor: pointer;
            margin-right: 2px;
            border-radius: 4px 4px 0 0;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .tab-item:hover {
            background: #e9ecef;
        }

        .tab-item.active {
            background: #fff;
            border-bottom: 2px solid #fff;
            margin-bottom: -2px;
            color: #4CAF50;
        }

        .tab-pane {
            display: none;
            padding: 20px 0;
        }

        .tab-pane.active {
            display: block;
        }

        /* Animation for tab transitions */
        .tab-pane {
            opacity: 0;
            transform: translateY(15px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .tab-pane.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>

    <script>
        // Ensure sidebar is expanded by default for this page
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('sidebar-expanded');
            document.getElementById('sidebar').classList.add('expanded');
        });

        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabItems = document.querySelectorAll('.tab-item');
            const tabPanes = document.querySelectorAll('.tab-pane');

            function switchTab(tabId) {
                // Remove active class from all tabs and panes
                tabItems.forEach(item => item.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));

                // Add active class to selected tab and pane
                const selectedTab = document.querySelector(`[data-tab="${tabId}"]`);
                const selectedPane = document.getElementById(tabId);
                
                selectedTab.classList.add('active');
                selectedPane.classList.add('active');

                // Save active tab to localStorage
                localStorage.setItem('activeInventoryTab', tabId);
            }

            // Add click event listeners to tabs
            tabItems.forEach(tab => {
                tab.addEventListener('click', () => {
                    switchTab(tab.dataset.tab);
                });
            });

            // Restore active tab from localStorage or default to 'alerts'
            const activeTab = localStorage.getItem('activeInventoryTab') || 'alerts';
            switchTab(activeTab);
        });
    </script>
</body>
</html>