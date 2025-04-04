<?php
$current_page = basename($_SERVER['PHP_SELF']);

include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// Fetch outgoing shipments (processing, shipped, and delivered orders) with related information
$query = "SELECT co.CustomerOrderID, co.OrderDate, co.TotalAmount, co.Status,
          c.CustomerID, c.CustomerName, c.Email,
          od.OrderDetailID, od.ProductID, od.Quantity, od.UnitPrice,
          p.ProductName,
          e.EmployeeID, e.FirstName, e.LastName, e.Role,
          dt.delivery_id, dt.delivery_date, dt.delivery_note
          FROM customerorders co
          JOIN customers c ON co.CustomerID = c.CustomerID
          JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
          JOIN products p ON od.ProductID = p.ProductID
          JOIN employees e ON od.EmployeeID = e.EmployeeID
          LEFT JOIN delivery_tracking dt ON od.OrderDetailID = dt.order_detail_id
          WHERE co.Status = 'Processing' OR co.Status = 'Shipped' OR co.Status = 'Delivered'
          ORDER BY co.OrderDate DESC";
          
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Get summary counts
$summary_query = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN Status = 'Pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN Status = 'Processing' THEN 1 END) as processing_orders,
    COUNT(CASE WHEN Status = 'Shipped' THEN 1 END) as shipped_orders,
    COUNT(CASE WHEN Status = 'Delivered' THEN 1 END) as delivered_orders
    FROM customerorders";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outgoing Shipments - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .summary-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            flex: 1;
            min-width: 200px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
        }
        .summary-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 16px;
        }
        .summary-card p {
            font-size: 24px;
            font-weight: 600;
            margin: 10px 0 0;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .pending {
            background-color: #f0ad4e;
            color: #fff;
        }
        .processing {
            background-color: #5bc0de;
            color: #fff;
        }
        .shipped {
            background-color: #337ab7;
            color: #fff;
        }
        .delivered {
            background-color: #5cb85c;
            color: #fff;
        }
        .cancelled {
            background-color: #d9534f;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <div class="main-content">
            <header>
                <h1>Outgoing Shipments</h1>
            </header>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Orders</h3>
                    <p><?php echo $summary['total_orders'] ?? 0; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Pending Orders</h3>
                    <p><?php echo $summary['pending_orders'] ?? 0; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Processing</h3>
                    <p><?php echo $summary['processing_orders'] ?? 0; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Shipped Orders</h3>
                    <p><?php echo $summary['shipped_orders'] ?? 0; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Delivered Orders</h3>
                    <p><?php echo $summary['delivered_orders'] ?? 0; ?></p>
                </div>
            </div>

            <div class="content">
                <div class="shipments-table-container">
                    <table class="shipments-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total Value</th>
                                <th>Handled By</th>
                                <th>Order Date</th>
                                <th>Delivery Date</th>
                                <th>Delivery Notes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result)) { 
                                    // Calculate line item value
                                    $lineItemValue = $row['Quantity'] * $row['UnitPrice'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['CustomerOrderID']); ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($row['CustomerName']); ?></strong>
                                                <div class="email"><?php echo htmlspecialchars($row['Email']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Quantity']); ?></td>
                                        <td>₱<?php echo number_format($lineItemValue, 2); ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></strong>
                                                <div class="role"><?php echo htmlspecialchars($row['Role']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($row['OrderDate'])); ?></td>
                                        <td><?php echo $row['delivery_date'] ? date('Y-m-d', strtotime($row['delivery_date'])) : 'Not delivered'; ?></td>
                                        <td>
                                            <span class="delivery-note">
                                                <?php echo htmlspecialchars($row['delivery_note'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($row['Status']); ?>">
                                                <?php echo htmlspecialchars($row['Status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-records">No shipments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>