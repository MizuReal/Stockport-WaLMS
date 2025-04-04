<?php
session_start();
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php';

// Fetch material orders summary
$materialOrdersQuery = "
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN Status = 'In Progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN Status = 'Completed' THEN 1 END) as completed
    FROM productionorders
";
$materialOrdersResult = $conn->query($materialOrdersQuery);
$materialOrdersStats = $materialOrdersResult->fetch_assoc();

// Fetch client orders summary
$clientOrdersQuery = "
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN Status = 'Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN Status = 'Processing' THEN 1 END) as processing,
        COUNT(CASE WHEN Status = 'Shipped' THEN 1 END) as shipped
    FROM customerorders
";
$clientOrdersResult = $conn->query($clientOrdersQuery);
$clientOrdersStats = $clientOrdersResult->fetch_assoc();

// Fetch low stock materials
$lowStockQuery = "
    SELECT MaterialName, QuantityInStock, MinimumStock
    FROM rawmaterials
    WHERE QuantityInStock <= MinimumStock
    ORDER BY (QuantityInStock/MinimumStock)
    LIMIT 5
";
$lowStockResult = $conn->query($lowStockQuery);

// Fetch recent deliveries
$recentDeliveriesQuery = "
    SELECT co.CustomerOrderID, c.CustomerName, co.OrderDate, co.Status
    FROM customerorders co
    JOIN customers c ON co.CustomerID = c.CustomerID
    WHERE co.Status IN ('Shipped', 'Delivered')
    ORDER BY co.OrderDate DESC
    LIMIT 5
";
$recentDeliveriesResult = $conn->query($recentDeliveriesQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overall Warehouse Processing</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background-color: #f4f6f9;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px; /* Match sidebar width */
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s ease-in-out;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .stats-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .stats-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #3498db;
            line-height: 1;
        }
        
        .stats-details p {
            margin: 0.5rem 0;
            color: #666;
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        
        .action-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            background-color: #3498db;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-weight: 500;
            flex: 1;
            text-align: center;
        }
        
        .action-button:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-2px);
        }
        
        .status-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }
        
        .status-table th {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            border-bottom: 2px solid #eee;
        }
        
        .status-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }
        
        .status-low { background-color: #ff6b6b; color: white; }
        .status-pending { background-color: #ffd700; color: black; }
        .status-processing { background-color: #87ceeb; color: black; }
        .status-shipped { background-color: #9932cc; color: white; }
        .status-delivered { background-color: #90ee90; color: black; }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="sidebar-expanded">
    <?php renderSidebar('overall_processing'); ?>
    
    <div class="content-wrapper">
        <?php renderHeader('Overall Warehouse Processing'); ?>
        
        <div class="dashboard-grid">
            <!-- Material Orders Statistics -->
            <div class="stats-card">
                <div class="stats-header">
                    <h2>Material Orders</h2>
                    <div class="stats-number"><?php echo $materialOrdersStats['total_orders']; ?></div>
                </div>
                <div class="stats-details">
                    <p>In Progress: <?php echo $materialOrdersStats['in_progress']; ?></p>
                    <p>Completed: <?php echo $materialOrdersStats['completed']; ?></p>
                </div>
                <div class="quick-actions">
                    <a href="materialOrderAdd.php" class="action-button">New Order</a>
                    <a href="materialOrderHistory.php" class="action-button">View History</a>
                </div>
            </div>

            <!-- Client Orders Statistics -->
            <div class="stats-card">
                <div class="stats-header">
                    <h2>Client Orders</h2>
                    <div class="stats-number"><?php echo $clientOrdersStats['total_orders']; ?></div>
                </div>
                <div class="stats-details">
                    <p>Pending: <?php echo $clientOrdersStats['pending']; ?></p>
                    <p>Processing: <?php echo $clientOrdersStats['processing']; ?></p>
                    <p>Shipped: <?php echo $clientOrdersStats['shipped']; ?></p>
                </div>
                <div class="quick-actions">
                    <a href="clientOrderAdd.php" class="action-button">New Order</a>
                    <a href="clientOrderTracker.php" class="action-button">Track Orders</a>
                </div>
            </div>

            <!-- Low Stock Materials -->
            <div class="stats-card">
                <div class="stats-header">
                    <h2>Low Stock Alert</h2>
                </div>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($material = $lowStockResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($material['MaterialName']); ?></td>
                                <td><?php echo $material['QuantityInStock']; ?>/<?php echo $material['MinimumStock']; ?></td>
                                <td><span class="status-badge status-low">Low Stock</span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Deliveries -->
            <div class="stats-card">
                <div class="stats-header">
                    <h2>Recent Deliveries</h2>
                </div>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($delivery = $recentDeliveriesResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $delivery['CustomerOrderID']; ?></td>
                                <td><?php echo htmlspecialchars($delivery['CustomerName']); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($delivery['Status']); ?>">
                                    <?php echo $delivery['Status']; ?>
                                </span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and other scripts if needed -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
