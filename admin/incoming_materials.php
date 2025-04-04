<?php
$current_page = basename($_SERVER['PHP_SELF']);

include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// Fetch only incoming materials (pending and in-progress orders)
$query = "SELECT po.*, p.ProductName, e.FirstName, e.LastName 
          FROM productionorders po
          LEFT JOIN products p ON po.ProductID = p.ProductID
          LEFT JOIN employees e ON po.EmployeeID = e.EmployeeID
          WHERE po.Status IN ('Pending', 'In Progress')
          ORDER BY po.StartDate DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Materials - Warehouse System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <div class="main-content">
            <header>
                <h1>Incoming Materials</h1>
            </header>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <?php
                $total_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(QuantityOrdered) as total_quantity,
                    COUNT(CASE WHEN Status = 'Pending' THEN 1 END) as pending_orders
                    FROM productionorders 
                    WHERE Status IN ('Pending', 'In Progress')";
                $total_result = mysqli_query($conn, $total_query);
                $totals = mysqli_fetch_assoc($total_result);
                ?>
                <div class="summary-card">
                    <h3>Total Incoming Orders</h3>
                    <p><?php echo $totals['total_orders']; ?></p>
                </div>
                <div class="summary-card">
                    <h3>Total Materials Ordered</h3>
                    <p><?php echo $totals['total_quantity']; ?> units</p>
                </div>
                <div class="summary-card">
                    <h3>Pending Orders</h3>
                    <p><?php echo $totals['pending_orders']; ?></p>
                </div>
            </div>

            <div class="content">
                <div class="orders-table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Assigned To</th>
                                <th>Expected Date</th>
                                <th>Status</th>
                                <th>Quantity</th>
                                <th>Warehouse</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result)) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['OrderID']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['EndDate'])); ?></td>
                                    <td><span class="status-badge <?php echo strtolower($row['Status']); ?>"><?php echo htmlspecialchars($row['Status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['QuantityOrdered']); ?></td>
                                    <td><?php echo htmlspecialchars($row['warehouseID']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>