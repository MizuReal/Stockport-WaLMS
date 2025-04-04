<?php
session_start();
include '../server/database.php';
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header('Location: clientOrderTracker.php');
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$orderId = (int)$_GET['id'];

// Add debug output
echo "<!-- Order ID: " . $orderId . " -->";

// Get order details with customer information
$orderQuery = "
    SELECT 
        co.*,
        c.CustomerName,
        c.Phone,
        c.Email,
        c.Address,
        od.OrderDetailID,
        od.Quantity,
        od.UnitPrice,
        p.ProductName,
        p.Category,
        p.product_img,
        e.FirstName as EmployeeFirstName,
        e.LastName as EmployeeLastName
    FROM customerorders co
    JOIN customers c ON co.CustomerID = c.CustomerID
    JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
    JOIN products p ON od.ProductID = p.ProductID
    LEFT JOIN employees e ON od.EmployeeID = e.EmployeeID
    WHERE co.CustomerOrderID = ?";

$stmt = $conn->prepare($orderQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $orderId);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Getting result failed: " . $stmt->error);
}

$order = $result->fetch_assoc();

// Debug output for SQL and result
echo "<!-- SQL Query: " . $orderQuery . " -->";
echo "<!-- Order data: " . print_r($order, true) . " -->";

if (!$order) {
    // Instead of redirecting, show error message
    echo "<div style='padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; margin: 20px; border-radius: 4px;'>";
    echo "<h2>Error</h2>";
    echo "<p>Order #" . $orderId . " not found.</p>";
    echo "<p><a href='clientOrderTracker.php'>Return to Order List</a></p>";
    echo "</div>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - #<?php echo sprintf('%05d', $orderId); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Add dashboard container styles */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            transition: margin-left 0.3s ease;
            width: calc(100% - 60px);
        }
        body.sidebar-expanded .main-content {
            margin-left: 160px;
            width: calc(100% - 220px);
        }
        .order-container {
            margin: 1rem;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
        .status-Pending { background: #ffd700; color: #000; }
        .status-Processing { background: #87ceeb; color: #000; }
        .status-Shipped { background: #9932CC; color: #fff; }
        .status-Delivered { background: #90ee90; color: #000; }
        .status-Cancelled { background: #ff6b6b; color: #fff; }

        /* Add custom button styles */
        .custom-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .custom-btn:hover {
            background-color: #3d8b40;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('clientOrderTracker'); ?>
        
        <div class="main-content">
            <div class="content-wrapper">
                <div class="order-container">
                    <div class="order-header">
                        <h2>Order #<?php echo sprintf('%05d', $orderId); ?></h2>
                        <span class="status-badge status-<?php echo $order['Status']; ?>">
                            <?php echo $order['Status']; ?>
                        </span>
                    </div>

                    <div class="order-info">
                        <div class="info-card">
                            <h3>Customer Information</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['CustomerName']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['Email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['Phone']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['Address']); ?></p>
                        </div>

                        <div class="info-card">
                            <h3>Order Information</h3>
                            <p><strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></p>
                            <p><strong>Total Amount:</strong> ₱<?php echo number_format($order['TotalAmount'], 2); ?></p>
                            <p><strong>Handled By:</strong> 
                                <?php echo $order['EmployeeFirstName'] ? 
                                    htmlspecialchars($order['EmployeeFirstName'] . ' ' . $order['EmployeeLastName']) : 
                                    'Not Assigned'; ?>
                            </p>
                        </div>

                        <div class="info-card">
                            <h3>Product Information</h3>
                            <?php if ($order['product_img']): ?>
                                <img src="../assets/imgs/<?php echo htmlspecialchars($order['product_img']); ?>" 
                                     alt="<?php echo htmlspecialchars($order['ProductName']); ?>"
                                     class="product-image">
                            <?php endif; ?>
                            <p><strong>Product:</strong> <?php echo htmlspecialchars($order['ProductName']); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($order['Category']); ?></p>
                            <p><strong>Quantity:</strong> <?php echo $order['Quantity']; ?></p>
                            <p><strong>Unit Price:</strong> ₱<?php echo number_format($order['UnitPrice'], 2); ?></p>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 20px;">
                        <a href="clientOrderTracker.php" class="custom-btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
