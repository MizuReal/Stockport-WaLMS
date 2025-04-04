<?php
session_start();
include '../server/database.php';
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header('Location: inventory.php');
    exit();
}

$orderId = (int)$_GET['id'];

// Get order details with customer information
$orderQuery = "
    SELECT 
        po.*, 
        p.ProductName,
        p.ProductID,
        p.Category,
        p.Weight,
        p.weight_unit,
        p.product_img,
        e.FirstName as EmployeeFirstName,
        e.LastName as EmployeeLastName,
        pw.productWarehouse as WarehouseName,
        pw.Section as WarehouseSection
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    JOIN employees e ON po.EmployeeID = e.EmployeeID
    JOIN products_warehouse pw ON po.warehouseID = pw.productLocationID
    WHERE po.OrderID = ?";

$stmt = $conn->prepare($orderQuery);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    header('Location: inventory.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo "ORD-" . sprintf('%03d', $orderId); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .order-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Create 2 columns */
            gap: 20px;
            margin: 1rem;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-header {
            grid-column: 1 / -1; /* Span full width */
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .order-info {
            display: contents; /* Let children participate in parent grid */
        }
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .info-card:hover {
            transform: translateY(-2px);
        }
        .info-card h3 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 1.2em;
            margin-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
        }
        .info-item {
            margin: 12px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .info-item strong {
            color: #495057;
        }
        .product-image {
            width: 100%;
            max-width: 200px;
            height: auto;
            margin: 10px 0;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-Planned { background: #ffd700; color: #000; }
        .status-InProgress { background: #87ceeb; color: #000; }
        .status-Completed { background: #90ee90; color: #000; }
        .status-Cancelled { background: #ff6b6b; color: #fff; }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: #4CAF50;
            transition: width 0.3s ease;
        }
        .action-container {
            grid-column: 1 / -1; /* Span full width */
            text-align: right;
            margin-top: 20px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transform: translateY(-1px);
        }
        
        .back-btn i {
            margin-right: 8px;
        }

        /* Responsive layout */
        @media (max-width: 1200px) {
            .order-container {
                grid-template-columns: 1fr; /* Single column on smaller screens */
            }
        }
    </style>
</head>
<body>
    <?php renderSidebar('inventory'); ?>
    
    <div class="content-wrapper">
        <div class="order-container">
            <div class="order-header">
                <h2>Order Details: ORD-<?php echo sprintf('%03d', $orderId); ?></h2>
                <span class="status-badge status-<?php echo str_replace(' ', '', $order['Status']); ?>">
                    <?php echo $order['Status']; ?>
                </span>
            </div>

            <div class="order-info">
                <div class="info-card">
                    <h3>Product Information</h3>
                    <?php if (!empty($order['product_img'])): ?>
                        <img src="../assets/imgs/<?php echo htmlspecialchars($order['product_img']); ?>" 
                             alt="<?php echo htmlspecialchars($order['ProductName']); ?>"
                             class="product-image">
                    <?php endif; ?>
                    <div class="info-item">
                        <strong>Product Name:</strong> <?php echo htmlspecialchars($order['ProductName']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Category:</strong> <?php echo htmlspecialchars($order['Category']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Weight per Unit:</strong> <?php echo $order['Weight'] . ' ' . $order['weight_unit']; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Order Details</h3>
                    <div class="info-item">
                        <strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($order['StartDate'])); ?>
                    </div>
                    <div class="info-item">
                        <strong>End Date:</strong> 
                        <?php echo $order['EndDate'] ? date('M d, Y', strtotime($order['EndDate'])) : 'Not Set'; ?>
                    </div>
                    <div class="info-item">
                        <strong>Assigned To:</strong> 
                        <?php echo htmlspecialchars($order['EmployeeFirstName'] . ' ' . $order['EmployeeLastName']); ?>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Production Progress</h3>
                    <div class="info-item">
                        <strong>Quantity Ordered:</strong> <?php echo number_format($order['QuantityOrdered']); ?> units
                    </div>
                    <div class="info-item">
                        <strong>Quantity Produced:</strong> <?php echo number_format($order['QuantityProduced']); ?> units
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php 
                            echo min(100, ($order['QuantityProduced'] / $order['QuantityOrdered']) * 100); 
                        ?>%"></div>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Warehouse Information</h3>
                    <div class="info-item">
                        <strong>Warehouse:</strong> <?php echo htmlspecialchars($order['WarehouseName']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Section:</strong> <?php echo htmlspecialchars($order['WarehouseSection']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Delivery Status:</strong> 
                        <?php echo $order['Delivery_Status'] ? 'Delivered' : 'Pending'; ?>
                    </div>
                </div>
            </div>

            <div class="action-container">
                <a href="inventory.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Inventory
                </a>
            </div>
        </div>
    </div>

</body>
</html>
