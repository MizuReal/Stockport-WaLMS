<?php
session_start();
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php';
require_once 'mailhelper.php';

// Handle delivery confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delivery'])) {
    $orderDetailId = $_POST['order_detail_id'];
    $deliveryNote = $_POST['delivery_note'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get warehouse and product details first with more specific warehouse details
        $getDetailsQuery = "SELECT 
            od.Quantity,
            od.CustomerOrderID,
            od.UnitPrice as Price,
            co.OrderDate,
            p.ProductID,
            p.ProductName,
            p.Weight,
            p.weight_unit,
            po.warehouseID as warehouse_id,
            wi.current_usage,
            wi.quantity as inventory_quantity,
            pw.Capacity,
            pw.current_usage as warehouse_current_usage,
            pw.warehouse_weight_unit,
            pw.productWarehouse as warehouse_name,
            c.CustomerName,
            c.Email
        FROM orderdetails od
        JOIN products p ON od.ProductID = p.ProductID
        JOIN customerorders co ON od.CustomerOrderID = co.CustomerOrderID
        JOIN customers c ON co.CustomerID = c.CustomerID
        JOIN (
            SELECT DISTINCT ProductID, warehouseID, OrderID
            FROM productionorders 
            WHERE Status = 'Completed'
            AND (ProductID, OrderID) IN (
                SELECT ProductID, MAX(OrderID) as OrderID
                FROM productionorders
                WHERE Status = 'Completed'
                GROUP BY ProductID
            )
        ) po ON po.ProductID = p.ProductID
        JOIN warehouse_inventory wi ON (p.ProductID = wi.product_id AND wi.warehouse_id = po.warehouseID)
        JOIN products_warehouse pw ON wi.warehouse_id = pw.productLocationID
        WHERE od.OrderDetailID = ?
        LIMIT 1";

        $detailsStmt = $conn->prepare($getDetailsQuery);
        if (!$detailsStmt) {
            throw new Exception("Failed to prepare query");
        }

        $detailsStmt->bind_param("i", $orderDetailId);
        if (!$detailsStmt->execute()) {
            throw new Exception("Failed to execute query");
        }

        $result = $detailsStmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("No records found");
        }
        
        $details = $result->fetch_assoc();
        
        // Convert values and calculate weight
        $details['Weight'] = floatval($details['Weight']);
        $details['Quantity'] = intval($details['Quantity']);
        $weightToRemove = $details['Weight'] * $details['Quantity'];
        
        // Convert weight units if needed
        if ($details['weight_unit'] === 'g' && $details['warehouse_weight_unit'] === 'kg') {
            $weightToRemove = $weightToRemove / 1000;
        } else if ($details['weight_unit'] === 'kg' && $details['warehouse_weight_unit'] === 'g') {
            $weightToRemove = $weightToRemove * 1000;
        }

        // Update warehouse inventory
        $updateWarehouseInventoryQuery = "UPDATE warehouse_inventory 
                                          SET quantity = GREATEST(0, quantity - ?),
                                              current_usage = GREATEST(0, current_usage - ?)
                                          WHERE warehouse_id = ? 
                                          AND product_id = ?";
        
        $warehouseInvStmt = $conn->prepare($updateWarehouseInventoryQuery);
        $warehouseInvStmt->bind_param("idii", 
            $details['Quantity'],
            $weightToRemove,
            $details['warehouse_id'],
            $details['ProductID']
        );
        $warehouseInvStmt->execute();

        // Update products warehouse
        $updateProductsWarehouseQuery = "UPDATE products_warehouse 
                                        SET current_usage = GREATEST(0, current_usage - ?)
                                        WHERE productLocationID = ?";
        
        $pwStmt = $conn->prepare($updateProductsWarehouseQuery);
        $pwStmt->bind_param("di", $weightToRemove, $details['warehouse_id']);
        $pwStmt->execute();

        // Update order status
        $updateOrderQuery = "UPDATE customerorders SET Status = 'Delivered' WHERE CustomerOrderID = ?";
        $orderStmt = $conn->prepare($updateOrderQuery);
        $orderStmt->bind_param("i", $details['CustomerOrderID']);
        $orderStmt->execute();

        // Insert delivery tracking record
        $insertDeliveryQuery = "INSERT INTO delivery_tracking 
                              (order_detail_id, delivery_date, delivery_note) 
                              VALUES (?, NOW(), ?)";
        
        $deliveryStmt = $conn->prepare($insertDeliveryQuery);
        $deliveryStmt->bind_param("is", $orderDetailId, $deliveryNote);
        $deliveryStmt->execute();
        
        $conn->commit();
        
        // After successful commit, send email notification
        $mailHelper = new MailHelper();
        $mailResult = $mailHelper->sendDeliveryConfirmation($details['Email'], [
            'CustomerOrderID' => $details['CustomerOrderID'],
            'CustomerName' => $details['CustomerName'],
            'ProductName' => $details['ProductName'],
            'Quantity' => $details['Quantity'],
            'Weight' => $details['Weight'],
            'weight_unit' => $details['weight_unit'],
            'Price' => $details['Price'],
            'OrderDate' => $details['OrderDate']
        ]);

        if ($mailResult) {
            $_SESSION['success_message'] = "Delivery confirmed successfully and notification email sent!";
        } else {
            $_SESSION['success_message'] = "Delivery confirmed successfully but email notification failed to send.";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to process delivery: " . $e->getMessage();
    }
    
    header("Location: deliverproducts.php");
    exit();
}

// Get all shipped orders
$query = "SELECT 
            co.CustomerOrderID,
            co.OrderDate,
            co.Status,
            od.OrderDetailID,
            od.Quantity,
            p.ProductID,
            p.ProductName,
            p.product_img,
            p.Weight,
            p.weight_unit,
            c.CustomerName,
            c.Phone,
            c.Email,
            c.Address,
            po.warehouseID as warehouse_id,
            pw.productWarehouse as warehouse_name,
            wi.quantity as warehouse_quantity
          FROM customerorders co
          INNER JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
          INNER JOIN products p ON od.ProductID = p.ProductID
          INNER JOIN customers c ON co.CustomerID = c.CustomerID
          INNER JOIN productionorders po ON (po.ProductID = p.ProductID AND po.Status = 'Completed')
          LEFT JOIN warehouse_inventory wi ON (p.ProductID = wi.product_id AND wi.warehouse_id = po.warehouseID)
          LEFT JOIN products_warehouse pw ON wi.warehouse_id = pw.productLocationID
          WHERE co.Status = 'Shipped'
          AND po.OrderID = (
              SELECT MAX(OrderID)
              FROM productionorders
              WHERE ProductID = p.ProductID
              AND Status = 'Completed'
          )
          ORDER BY co.OrderDate DESC";

$result = $conn->query($query);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM delivery_tracking";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$perPage = 5;
$totalPages = ceil($totalRows / $perPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $perPage;

// Update history query with pagination
$historyQuery = "SELECT 
    dt.delivery_id,
    dt.delivery_date,
    dt.delivery_note,
    co.CustomerOrderID,
    c.CustomerName,
    p.ProductName,
    p.product_img,
    od.Quantity,
    p.Weight,
    p.weight_unit
FROM delivery_tracking dt
JOIN orderdetails od ON dt.order_detail_id = od.OrderDetailID
JOIN customerorders co ON od.CustomerOrderID = co.CustomerOrderID
JOIN customers c ON co.CustomerID = c.CustomerID
JOIN products p ON od.ProductID = p.ProductID
ORDER BY dt.delivery_date DESC
LIMIT ? OFFSET ?";

$historyStmt = $conn->prepare($historyQuery);
$historyStmt->bind_param("ii", $perPage, $offset);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliver Products</title>
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <style>
        .content-wrapper {
            padding: 20px;
            margin-left: 60px;
            transition: margin-left 0.3s ease;
        }
        body.sidebar-expanded .content-wrapper {
            margin-left: 220px;
        }
        .delivery-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            padding: 25px;
            transition: transform 0.2s ease;
        }

        .delivery-card:hover {
            transform: translateY(-2px);
        }

        .delivery-info {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
        }

        .order-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .order-header h3 {
            margin: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .customer-details, .inventory-info {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 0;
            height: 100%;
        }

        .customer-details {
            border: 1px solid #e0e0e0;
        }

        .inventory-info {
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
        }

        .customer-details h4, .inventory-info h4 {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 1rem;
        }

        .details-content p {
            margin: 5px 0;
            font-size: 0.9rem;
        }

        .product-details {
            text-align: left;
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .product-info {
            flex: 1;
            padding: 0;
        }

        .product-info h4 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 1rem;
        }

        .product-info p {
            margin: 4px 0;
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .delivery-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .delivery-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }

        .btn-deliver {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.2s ease;
        }

        .btn-deliver:hover {
            background-color: #45a049;
        }

        .customer-details {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #e0e0e0;
        }

        .customer-details h4 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background-color: #9932CC;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inventory-info {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #4CAF50;
        }

        .product-details {
            text-align: center;
            margin-bottom: 20px;
        }

        .product-details h4 {
            color: #2c3e50;
            margin: 10px 0;
            font-size: 1.2em;
        }

        .product-details p {
            color: #666;
            margin: 5px 0;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .inventory-info {
            background-color: #e9f7ef;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
        }
        .delivery-history {
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .history-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e1e1e1;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .history-header h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .delivery-date {
            color: #666;
            font-size: 0.9em;
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .history-details {
            text-align: right;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .history-details span {
            display: block;
            font-size: 0.9em;
            color: #666;
        }
        
        .history-details span:first-child {
            color: #4CAF50;
            font-weight: 500;
        }
        
        .history-content {
            display: flex;
            gap: 25px;
            background: #fafafa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .history-product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .history-info {
            flex-grow: 1;
        }
        
        .history-info p {
            margin: 8px 0;
            color: #444;
        }
        
        .history-info strong {
            color: #2c3e50;
            min-width: 100px;
            display: inline-block;
        }
        
        .delivery-note {
            margin-top: 15px;
            padding: 12px;
            background: #fff;
            border-radius: 6px;
            border-left: 4px solid #4CAF50;
        }

        /* Tab Styles */
        .tabs {
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            position: relative;
            color: #666;
        }
        
        .tab-button.active {
            color: #4CAF50;
            font-weight: 500;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #4CAF50;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #4CAF50;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background-color: #f5f5f5;
        }
        
        .page-link.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 0;
        }

        .product-details {
            text-align: left;
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .product-info {
            flex: 1;
            padding: 0;
        }

        .product-info h4 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 1rem;
        }

        .product-info p {
            margin: 4px 0;
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <?php renderSidebar('deliverproducts'); ?>
    
    <div class="content-wrapper">
        <?php renderHeader('Deliver Products'); ?>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab-button <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'make-delivery') ? 'active' : ''; ?>" 
                    onclick="openTab('make-delivery')">Make Delivery</button>
            <button class="tab-button <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'delivery-history') ? 'active' : ''; ?>" 
                    onclick="openTab('delivery-history')">Delivery History</button>
        </div>

        <!-- Make Delivery Tab -->
        <div id="make-delivery" class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'make-delivery') ? 'active' : ''; ?>">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="delivery-card">
                        <div class="delivery-info">
                            <div>
                                <div class="order-header">
                                    <h3>Order #<?php echo $row['CustomerOrderID']; ?></h3>
                                    <p>Order Date: <?php echo date('M d, Y', strtotime($row['OrderDate'])); ?></p>
                                    <span class="status-badge"><?php echo $row['Status']; ?></span>
                                </div>
                                
                                <div class="info-grid">
                                    <div class="customer-details">
                                        <h4><i class="fas fa-shipping-fast"></i> Delivery Information</h4>
                                        <div class="details-content">
                                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($row['CustomerName']); ?></p>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['Phone']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($row['Email']); ?></p>
                                            <p><strong>Address:</strong> <?php echo htmlspecialchars($row['Address']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="inventory-info">
                                        <h4><i class="fas fa-warehouse"></i> Inventory Details</h4>
                                        <div class="details-content">
                                            <p><strong>Warehouse Quantity:</strong> <?php echo isset($row['warehouse_quantity']) ? $row['warehouse_quantity'] : 'Not available'; ?></p>
                                            <p><strong>Warehouse:</strong> <?php echo $row['warehouse_name']; ?></p>
                                            <p><strong>Note:</strong> Upon delivery confirmation, <?php echo $row['Quantity']; ?> units will be deducted from inventory.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="product-details">
                                    <img src="../assets/imgs/<?php echo $row['product_img']; ?>" alt="<?php echo $row['ProductName']; ?>" class="product-image">
                                    <div class="product-info">
                                        <h4><?php echo $row['ProductName']; ?></h4>
                                        <p>Quantity: <?php echo $row['Quantity']; ?> units</p>
                                        <p>Weight per Unit: <?php echo $row['Weight'] . ' ' . $row['weight_unit']; ?></p>
                                        <p>Total Weight: <?php echo ($row['Weight'] * $row['Quantity']) . ' ' . $row['weight_unit']; ?></p>
                                    </div>
                                </div>
                                
                                <form method="POST" class="delivery-form" onsubmit="return confirm('Confirm delivery of this order? This will remove the products from warehouse inventory.');">
                                    <input type="hidden" name="order_detail_id" value="<?php echo $row['OrderDetailID']; ?>">
                                    <textarea name="delivery_note" placeholder="Add delivery notes (optional)" rows="3"></textarea>
                                    <button type="submit" name="confirm_delivery" class="btn-deliver">
                                        <i class="fas fa-check-circle"></i> Confirm Delivery
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>  
                <?php endwhile; ?>
            <?php else: ?>
                <div class="delivery-card">
                    <p>No orders ready for delivery.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Delivery History Tab -->
        <div id="delivery-history" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'delivery-history') ? 'active' : ''; ?>">
            <?php if ($historyResult && $historyResult->num_rows > 0): ?>
                <?php while($history = $historyResult->fetch_assoc()): ?>
                    <div class="history-card">
                        <div class="history-header">
                            <div>
                                <h4>Delivery #<?php echo $history['delivery_id']; ?></h4>
                                <p class="delivery-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y h:i A', strtotime($history['delivery_date'])); ?>
                                </p>
                            </div>
                            <div class="history-details">
                                <span>Order #<?php echo $history['CustomerOrderID']; ?></span>
                                <span><?php echo htmlspecialchars($history['CustomerName']); ?></span>
                            </div>
                        </div>
                        <div class="history-content">
                            <img src="../assets/imgs/<?php echo $history['product_img']; ?>" 
                                 alt="<?php echo $history['ProductName']; ?>" 
                                 class="history-product-image">
                            <div class="history-info">
                                <p><strong>Product:</strong> <?php echo htmlspecialchars($history['ProductName']); ?></p>
                                <p><strong>Quantity:</strong> <?php echo $history['Quantity']; ?> units</p>
                                <p><strong>Total Weight:</strong> 
                                    <?php echo ($history['Weight'] * $history['Quantity']) . ' ' . $history['weight_unit']; ?>
                                </p>
                                <?php if ($history['delivery_note']): ?>
                                    <div class="delivery-note">
                                        <strong>Note:</strong> <?php echo htmlspecialchars($history['delivery_note']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="?tab=delivery-history&page=<?php echo ($currentPage - 1); ?>" class="page-link">&laquo; Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="?tab=delivery-history&page=<?php echo $i; ?>" 
                               class="page-link <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?tab=delivery-history&page=<?php echo ($currentPage + 1); ?>" class="page-link">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="history-card">
                    <p>No delivery history available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Update tab parameter
            urlParams.set('tab', tabName);
            
            // Remove page parameter if switching tabs
            if (urlParams.get('tab') !== tabName) {
                urlParams.delete('page');
            }
            
            // Construct new URL
            const newUrl = window.location.pathname + '?' + urlParams.toString();
            
            // Navigate to new URL
            window.location.href = newUrl;
        }
    </script>
</body>
</html>