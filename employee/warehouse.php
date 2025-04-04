<?php
session_start();
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php';

// Fetch warehouse data with utilization
$warehouseQuery = "SELECT 
    pw.*,
    (pw.current_usage / pw.Capacity * 100) as usage_percentage,
    GREATEST(0, pw.Capacity - pw.current_usage) as available_capacity
    FROM products_warehouse pw
    ORDER BY pw.productWarehouse";
$warehouseResult = $conn->query($warehouseQuery);

// Fetch products with warehouse info
$productsQuery = "SELECT 
    p.*, pw.productWarehouse, pw.Section
    FROM products p
    JOIN products_warehouse pw ON p.LocationID = pw.productLocationID
    ORDER BY pw.productWarehouse, p.ProductName";
$productsResult = $conn->query($productsQuery);

// Fetch raw materials
$materialsQuery = "SELECT * FROM rawmaterials ORDER BY raw_warehouse, MaterialName";
$materialsResult = $conn->query($materialsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background: #f4f6f9;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        .container-fluid {
            padding: 0;
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
        }
        .main-content {
            flex: 1;
            margin-left: 200px; /* Reduced from 250px */
            padding: 1.5rem;
            background: #f4f6f9;
            min-height: 100vh;
            width: calc(100% - 200px); /* Adjust width based on sidebar */
            max-width: 100%;
        }
        /* Make warehouse grid more responsive */
        .warehouse-grid {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .warehouse-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .warehouse-card:hover {
            transform: translateY(-5px);
        }
        .warehouse-card h3 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .progress {
            height: 1.2rem;
            background-color: #f1f1f1;
            margin: 1rem 0;
        }
        .progress.normal { background-color: #4CAF50 !important; }
        .progress.warning { background-color: #FFC107 !important; }
        .progress.critical { background-color: #DC3545 !important; }
        .info-text {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0.5rem 0;
        }
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        .section-title {
            position: relative;
            color: #2c3e50;
            padding-bottom: 0.5rem;
            margin: 2rem 0;
            font-weight: 600;
        }
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: #ff7f50;
        }
        .table {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
        }
        .low-stock {
            background-color: #fff5f5;
        }
        .badge {
            padding: 0.5em 1em;
            border-radius: 30px;
            font-weight: 500;
        }
        .stats-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        /* Tab Styles */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 1.5rem;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .nav-tabs .nav-link:hover {
            border: none;
            color: #ff7f50;
        }
        .nav-tabs .nav-link.active {
            border: none;
            border-bottom: 2px solid #ff7f50;
            color: #ff7f50;
        }
        .tab-content {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php renderSidebar('warehouse'); ?>
        
        <div class="main-content">
            <?php renderHeader('Warehouse Operations'); ?>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                        <i class="bi bi-grid"></i> Warehouse Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#products" type="button">
                        <i class="bi bi-box-seam"></i> Stored Products
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#materials" type="button">
                        <i class="bi bi-archive"></i> Raw Materials
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div class="warehouse-grid">
                        <?php while ($warehouse = $warehouseResult->fetch_assoc()): 
                            $usagePercent = floatval($warehouse['usage_percentage']);
                            $statusClass = $usagePercent >= 90 ? 'critical' : 
                                        ($usagePercent >= 75 ? 'warning' : 'normal');
                            $statusBadge = $usagePercent >= 90 ? 'danger' : 
                                        ($usagePercent >= 75 ? 'warning' : 'success');
                        ?>
                            <div class="warehouse-card">
                                <h3>
                                    <i class="bi bi-building"></i> 
                                    <?php echo htmlspecialchars($warehouse['productWarehouse']); ?>
                                </h3>
                                <p class="text-muted mb-3">
                                    <i class="bi bi-geo-alt"></i> 
                                    Section: <?php echo htmlspecialchars($warehouse['Section']); ?>
                                </p>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $statusBadge; ?>" 
                                         role="progressbar"
                                         style="width: <?php echo min(100, $usagePercent); ?>%"
                                         aria-valuenow="<?php echo $usagePercent; ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                        <?php echo round($usagePercent); ?>%
                                    </div>
                                </div>
                                <div class="info-text">
                                    <p><span class="info-value">Capacity:</span> 
                                       <?php echo number_format($warehouse['Capacity']) . ' ' . $warehouse['warehouse_weight_unit']; ?></p>
                                    <p><span class="info-value">Current Usage:</span> 
                                       <?php echo number_format($warehouse['current_usage'], 2) . ' ' . $warehouse['warehouse_weight_unit']; ?></p>
                                    <p><span class="info-value">Available:</span> 
                                       <?php echo number_format($warehouse['available_capacity'], 2) . ' ' . $warehouse['warehouse_weight_unit']; ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Products Tab -->
                <div class="tab-pane fade" id="products" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Weight</th>
                                    <th>Warehouse</th>
                                    <th>Section</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $productsResult->data_seek(0);
                                while ($product = $productsResult->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-box-seam me-2"></i>
                                            <?php echo htmlspecialchars($product['ProductName']); ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['Category']); ?></span></td>
                                        <td><?php echo number_format($product['Weight'], 2) . ' ' . $product['weight_unit']; ?></td>
                                        <td><?php echo htmlspecialchars($product['productWarehouse']); ?></td>
                                        <td><?php echo htmlspecialchars($product['Section']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Materials Tab -->
                <div class="tab-pane fade" id="materials" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Material Name</th>
                                    <th>Quantity in Stock</th>
                                    <th>Minimum Stock</th>
                                    <th>Warehouse</th>
                                    <th>Last Restocked</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $materialsResult->data_seek(0);
                                while ($material = $materialsResult->fetch_assoc()): 
                                    $stockStatus = $material['QuantityInStock'] <= $material['MinimumStock'];
                                    $statusBadge = $stockStatus ? 'danger' : 'success';
                                    $statusText = $stockStatus ? 'Low Stock' : 'Adequate';
                                ?>
                                    <tr class="<?php if ($stockStatus) echo 'table-warning'; ?>">
                                        <td>
                                            <i class="bi bi-archive me-2"></i>
                                            <?php echo htmlspecialchars($material['MaterialName']); ?>
                                        </td>
                                        <td><?php echo number_format($material['QuantityInStock']); ?></td>
                                        <td><?php echo number_format($material['MinimumStock']); ?></td>
                                        <td><?php echo htmlspecialchars($material['raw_warehouse']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($material['LastRestockedDate'])); ?></td>
                                        <td><span class="badge bg-<?php echo $statusBadge; ?>"><?php echo $statusText; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html>