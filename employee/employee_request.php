<?php
session_start();
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php';

$employeeID = $_SESSION['employeeID'];

// Fetch production orders
$productionQuery = "
    SELECT DISTINCT
        'Production Order' as request_type,
        po.OrderID as id,
        p.ProductName as item_name,
        po.StartDate as request_date,
        po.Status as status,
        po.QuantityProduced as progress,
        po.QuantityOrdered as total,
        pw.productWarehouse as location,
        p.product_img as image
    FROM productionorders po
    JOIN products p ON po.ProductID = p.ProductID
    JOIN products_warehouse pw ON po.warehouseID = pw.productLocationID
    WHERE po.EmployeeID = ?
    ORDER BY po.StartDate DESC";

$stmt = $conn->prepare($productionQuery);
$stmt->bind_param("i", $employeeID);
$stmt->execute();
$productionOrders = $stmt->get_result();

// Fetch customer orders
$customerQuery = "
    SELECT DISTINCT
        'Customer Order' as request_type,
        co.CustomerOrderID as id,
        c.CustomerName as item_name,
        co.OrderDate as request_date,
        co.Status as status,
        NULL as progress,
        NULL as total,
        'N/A' as location,
        p.product_img as image
    FROM customerorders co
    JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
    JOIN customers c ON co.CustomerID = c.CustomerID
    JOIN products p ON od.ProductID = p.ProductID
    WHERE od.EmployeeID = ?
    ORDER BY co.OrderDate DESC";

$stmt = $conn->prepare($customerQuery);
$stmt->bind_param("i", $employeeID);
$stmt->execute();
$customerOrders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <style>
        .main-content {
            margin-left: 60px; /* Initial state matches collapsed sidebar */
            transition: margin-left 0.3s ease;
            padding: 20px 40px; /* Increased horizontal padding */
        }

        body.sidebar-expanded .main-content {
            margin-left: 220px; /* Expanded state matches sidebar width */
        }

        body.sidebar-hidden .main-content {
            margin-left: 20px; /* Fully collapsed state */
        }

        .request-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            border: 1px solid #eef2f7;
        }
        
        .request-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .request-header {
            padding: 1.25rem;
            border-bottom: 1px solid #eef2f7;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
        }

        .request-body {
            padding: 1.25rem;
        }

        .request-type {
            display: inline-block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .request-id {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .progress {
            height: 6px;
            margin: 1rem 0;
            border-radius: 50px;
            background-color: #eef2f7;
        }

        .progress-bar {
            border-radius: 50px;
            background-color: #4CAF50;
        }

        .detail-item {
            margin-bottom: 0.75rem;
        }

        .detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #495057;
        }

        .btn-view {
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-view:hover {
            transform: translateY(-1px);
        }

        /* Status colors */
        .status-Pending { background: #fff8e1; color: #ffa000; }
        .status-Processing { background: #e3f2fd; color: #1976d2; }
        .status-InProgress { background: #e3f2fd; color: #1976d2; }
        .status-Completed { background: #e8f5e9; color: #2e7d32; }
        .status-Shipped { background: #ede7f6; color: #7b1fa2; }
        .status-Delivered { background: #e8f5e9; color: #2e7d32; }
        .status-Cancelled { background: #ffebee; color: #c62828; }

        /* Add these new header styles */
        .header-title {
            font-family: 'Segoe UI', 'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            font-size: 1.8rem;
            color: #2c3e50;
            text-align: center;
            margin: 1.5rem 0;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eef2f7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Add these new styles */
        .nav-tabs {
            border-bottom: 2px solid #eef2f7;
            margin-bottom: 2rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 2rem;
            position: relative;
        }
        
        .nav-tabs .nav-link.active {
            color: #4CAF50;
            background: none;
            border: none;
        }
        
        .nav-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #4CAF50;
        }
        
        .tab-content {
            padding-top: 1rem;
        }

        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .nav-tabs {
            border-bottom: 2px solid #eef2f7;
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
            width: 100%;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .request-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .request-container {
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .request-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }

        .request-header {
            padding: 1rem;
            border-bottom: 1px solid #eef2f7;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
        }

        .request-body {
            padding: 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            font-size: 0.7rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php renderSidebar('employee_request'); ?>
    
    <div class="main-content">
        <h1 class="header-title">My Requests</h1>
        
        <div class="content-wrapper">
            <ul class="nav nav-tabs" id="requestTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="production-tab" data-bs-toggle="tab" data-bs-target="#production" type="button" role="tab">
                        <i class="fas fa-industry me-2"></i>Production Orders
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customer" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-2"></i>Customer Orders
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="requestTabsContent">
                <div class="tab-pane fade show active" id="production" role="tabpanel">
                    <?php if($productionOrders->num_rows > 0): ?>
                        <div class="request-grid">
                        <?php while($request = $productionOrders->fetch_assoc()): ?>
                            <div class="request-container">
                                <div class="request-header d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="product-image me-3">
                                            <?php if ($request['image']): ?>
                                                <img src="../assets/imgs/<?php echo htmlspecialchars($request['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($request['item_name']); ?>"
                                                     class="rounded"
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="no-image rounded bg-light d-flex align-items-center justify-content-center"
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="request-type">
                                                <i class="fas <?php echo $request['request_type'] === 'Production Order' ? 'fa-industry' : 'fa-shopping-cart'; ?> me-1"></i>
                                                <?php echo $request['request_type']; ?>
                                            </span>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($request['item_name']); ?></h5>
                                            <div class="request-id">ID: <?php echo sprintf('%05d', $request['id']); ?></div>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', $request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="request-body">
                                    <?php if($request['progress'] !== null): ?>
                                        <div class="progress-section">
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo ($request['progress'] / $request['total'] * 100); ?>%" 
                                                     aria-valuenow="<?php echo ($request['progress'] / $request['total'] * 100); ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <div class="text-end text-muted small">
                                                <?php echo $request['progress'] . ' / ' . $request['total']; ?> units
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="detail-item">
                                                <div class="detail-label">Date</div>
                                                <div class="detail-value">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($request['request_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="detail-item">
                                                <div class="detail-label">Location</div>
                                                <div class="detail-value">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($request['location']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end mt-3">
                                        <a href="<?php echo $request['request_type'] === 'Production Order' ? 'view_order.php?id=' : 'view_clientorder.php?id='; ?><?php echo $request['id']; ?>" 
                                           class="btn btn-primary btn-view">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-industry fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No production orders found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-pane fade" id="customer" role="tabpanel">
                    <?php if($customerOrders->num_rows > 0): ?>
                        <div class="request-grid">
                        <?php while($request = $customerOrders->fetch_assoc()): ?>
                            <div class="request-container">
                                <div class="request-header d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="product-image me-3">
                                            <?php if ($request['image']): ?>
                                                <img src="../assets/imgs/<?php echo htmlspecialchars($request['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($request['item_name']); ?>"
                                                     class="rounded"
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="no-image rounded bg-light d-flex align-items-center justify-content-center"
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="request-type">
                                                <i class="fas <?php echo $request['request_type'] === 'Production Order' ? 'fa-industry' : 'fa-shopping-cart'; ?> me-1"></i>
                                                <?php echo $request['request_type']; ?>
                                            </span>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($request['item_name']); ?></h5>
                                            <div class="request-id">ID: <?php echo sprintf('%05d', $request['id']); ?></div>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', $request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="request-body">
                                    <?php if($request['progress'] !== null): ?>
                                        <div class="progress-section">
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo ($request['progress'] / $request['total'] * 100); ?>%" 
                                                     aria-valuenow="<?php echo ($request['progress'] / $request['total'] * 100); ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <div class="text-end text-muted small">
                                                <?php echo $request['progress'] . ' / ' . $request['total']; ?> units
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="detail-item">
                                                <div class="detail-label">Date</div>
                                                <div class="detail-value">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($request['request_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="detail-item">
                                                <div class="detail-label">Location</div>
                                                <div class="detail-value">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($request['location']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end mt-3">
                                        <a href="<?php echo $request['request_type'] === 'Production Order' ? 'view_order.php?id=' : 'view_clientorder.php?id='; ?><?php echo $request['id']; ?>" 
                                           class="btn btn-primary btn-view">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No customer orders found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
