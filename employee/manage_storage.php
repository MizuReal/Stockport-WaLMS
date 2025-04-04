<?php
session_start();
require_once 'session_check.php';
requireActiveLogin();
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $from_warehouse = $_POST['from_warehouse'];
    $to_warehouse = $_POST['to_warehouse'];
    $notes = $_POST['notes'];
    
    // Check source warehouse inventory only
    $checkStock = $conn->prepare("SELECT 
        p.Weight,
        p.weight_unit,
        wi.quantity as stored_quantity,
        wi.current_usage as warehouse_usage,
        pw.warehouse_weight_unit,
        pw.Capacity,
        pw.current_usage
        FROM products p
        LEFT JOIN warehouse_inventory wi ON wi.product_id = p.ProductID AND wi.warehouse_id = ?
        LEFT JOIN products_warehouse pw ON pw.productLocationID = ?
        WHERE p.ProductID = ?");
    $checkStock->bind_param("iii", $from_warehouse, $from_warehouse, $product_id);
    $checkStock->execute();
    $sourceData = $checkStock->get_result()->fetch_assoc();

    if (!$sourceData['stored_quantity']) {
        $_SESSION['error_message'] = "No stock available in source warehouse";
    } else if ($sourceData['stored_quantity'] < $quantity) {
        $_SESSION['error_message'] = "Insufficient stock available";
    } else {
        // Submit transfer request
        $stmt = $conn->prepare("INSERT INTO storage_transfers 
            (product_id, quantity, from_warehouse, to_warehouse, requested_by, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiiiss", $product_id, $quantity, $from_warehouse, $to_warehouse, 
                         $_SESSION['employeeID'], $notes);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Transfer request submitted successfully";
        } else {
            $_SESSION['error_message'] = "Error submitting transfer request";
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Fetch all warehouses and their inventories (modified query)
$warehouseQuery = "SELECT 
    pw.productLocationID,
    pw.productWarehouse,
    pw.Section,
    pw.Capacity,
    pw.current_usage,
    pw.warehouse_weight_unit,
    pw.remaining_capacity
    FROM products_warehouse pw
    ORDER BY pw.productWarehouse, pw.Section";

$warehouseResult = $conn->query($warehouseQuery);
$warehouses = array();

while ($row = $warehouseResult->fetch_assoc()) {
    $id = $row['productLocationID'];
    $warehouses[$id] = [
        'productLocationID' => $row['productLocationID'],
        'productWarehouse' => $row['productWarehouse'],
        'Section' => $row['Section'],
        'Capacity' => $row['Capacity'],
        'warehouse_weight_unit' => $row['warehouse_weight_unit'],
        'current_usage' => $row['current_usage'] ?? 0,
        'remaining_capacity' => $row['remaining_capacity'] ?? $row['Capacity'],
        'inventory' => []
    ];
}

// Now fetch inventory separately
$inventoryQuery = "SELECT 
    wi.warehouse_id,
    wi.product_id,
    wi.quantity as stored_quantity,
    wi.current_usage as item_usage,
    p.ProductName,
    p.Weight,
    p.weight_unit,
    p.product_img
    FROM warehouse_inventory wi
    JOIN products p ON wi.product_id = p.ProductID
    WHERE wi.quantity > 0
    ORDER BY p.ProductName";

$inventoryResult = $conn->query($inventoryQuery);

while ($item = $inventoryResult->fetch_assoc()) {
    $warehouseId = $item['warehouse_id'];
    if (isset($warehouses[$warehouseId])) {
        $warehouses[$warehouseId]['inventory'][] = [
            'id' => $item['product_id'],
            'name' => $item['ProductName'],
            'quantity' => $item['stored_quantity'],
            'weight' => $item['Weight'],
            'weight_unit' => $item['weight_unit'],
            'product_img' => $item['product_img'],
            'current_usage' => $item['item_usage'] ?? 0
        ];
    }
}

// Fetch transfers with product details
$transfersQuery = "SELECT 
    st.*,
    p.ProductName,
    p.product_img,
    p.Weight,
    p.weight_unit,
    CONCAT(fw.productWarehouse, ' - ', fw.Section) as from_name,
    CONCAT(tw.productWarehouse, ' - ', tw.Section) as to_name
    FROM storage_transfers st
    JOIN products p ON st.product_id = p.ProductID 
    JOIN products_warehouse fw ON st.from_warehouse = fw.productLocationID
    JOIN products_warehouse tw ON st.to_warehouse = tw.productLocationID
    WHERE st.requested_by = ?
    ORDER BY st.requested_at DESC";
$transfersStmt = $conn->prepare($transfersQuery);
$transfersStmt->bind_param("i", $_SESSION['employeeID']);
$transfersStmt->execute();
$transfersResult = $transfersStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Storage Transfers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <style>
        .content-wrapper {
            margin-left: 250px; /* Match sidebar width */
            transition: margin-left 0.3s;
            padding: 20px;
            background: #f4f6f9;
        }
        
        body.sidebar-collapsed .content-wrapper {
            margin-left: 70px;
        }
        
        .transfer-form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        select, input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 2px rgba(74,144,226,0.2);
        }
        
        .btn {
            background: #4a90e2;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #357abd;
        }
        
        .alert {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table {
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .product-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-in_transit { background: #cce5ff; color: #004085; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .section-title {
            margin: 30px 0 20px;
            color: #2c3e50;
            font-size: 24px;
            font-weight: 500;
        }
        
        .warehouse-info {
            font-size: 13px;
            color: #666;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .warehouse-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
        }

        .utilization-bar {
            width: 60px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            margin-left: 10px;
            overflow: hidden;
        }

        .utilization-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .utilization-low { background: #28a745; }
        .utilization-medium { background: #ffc107; }
        .utilization-high { background: #dc3545; }

        .warehouse-details {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .utilization-text {
            min-width: 45px;
            text-align: right;
            font-size: 12px;
            color: #666;
        }

        .warehouse-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 20px;
        }

        .warehouse-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .warehouse-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .progress {
            height: 8px;
        }

        .progress-sm {
            height: 4px;
        }

        .warehouse-metric {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .capacity-info {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
        }

        .warehouse-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .warehouse-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s;
        }

        .warehouse-card:hover {
            transform: translateY(-3px);
        }

        .warehouse-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }

        .warehouse-products {
            margin-top: 15px;
            max-height: 150px;
            overflow-y: auto;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item img {
            width: 30px;
            height: 30px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }

        .warehouse-stats {
            margin: 15px 0;
        }

        .utilization-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .progress {
            flex-grow: 1;
            height: 8px;
        }
    </style>
</head>
<body>
    <?php 
    // Render sidebar with current page
    renderSidebar('manage_storage');
    ?>
    
    <div class="content-wrapper">
        <div class="warehouse-grid">
            <?php foreach ($warehouses as $warehouse): 
                $utilization = round(($warehouse['current_usage'] / $warehouse['Capacity']) * 100, 1);
                $progressClass = $utilization > 80 ? 'danger' : ($utilization > 60 ? 'warning' : 'success');
            ?>
                <div class="warehouse-card">
                    <h3><?php echo htmlspecialchars($warehouse['productWarehouse'] . ' - ' . $warehouse['Section']); ?></h3>
                    
                    <div class="warehouse-stats">
                        <div>Current Usage: <?php echo number_format($warehouse['current_usage'], 1); ?> <?php echo $warehouse['warehouse_weight_unit']; ?></div>
                        <div>Available: <?php echo number_format($warehouse['remaining_capacity'], 1); ?> <?php echo $warehouse['warehouse_weight_unit']; ?></div>
                        
                        <div class="utilization-indicator">
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $progressClass; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $utilization; ?>%" 
                                     aria-valuenow="<?php echo $utilization; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <span class="small"><?php echo $utilization; ?>%</span>
                        </div>
                    </div>

                    <?php if (!empty($warehouse['inventory'])): ?>
                    <div class="warehouse-products">
                        <?php foreach ($warehouse['inventory'] as $item): ?>
                        <div class="product-item">
                            <img src="../assets/imgs/<?php echo htmlspecialchars($item['product_img']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div>
                                <?php echo htmlspecialchars($item['name']); ?>
                                <div class="small text-muted">
                                    Stock: <?php echo $item['quantity']; ?> units
                                    <br>
                                    Usage: <?php echo number_format($item['current_usage'], 1); ?> <?php echo $warehouse['warehouse_weight_unit']; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-muted small">No inventory in this warehouse</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 class="section-title">Request Storage Transfer</h2>
        
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

        <form method="POST" class="transfer-form">
            <div class="form-row">
                <div class="form-group">
                    <label>From Warehouse:</label>
                    <select name="from_warehouse" id="from_warehouse" required onchange="updateProducts()">
                        <option value="">Select Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['productLocationID']; ?>">
                                <?php echo htmlspecialchars($warehouse['productWarehouse'] . ' - ' . $warehouse['Section']); ?>
                                (<?php echo number_format($warehouse['remaining_capacity'], 1); ?> <?php echo $warehouse['warehouse_weight_unit']; ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>To Warehouse:</label>
                    <select name="to_warehouse" required>
                        <option value="">Select Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['productLocationID']; ?>" 
                                    class="to-warehouse-option"
                                    data-from-id="<?php echo $warehouse['productLocationID']; ?>">
                                <?php echo htmlspecialchars($warehouse['productWarehouse'] . ' - ' . $warehouse['Section']); ?>
                                (<?php echo number_format($warehouse['remaining_capacity'], 1); ?> <?php echo $warehouse['warehouse_weight_unit']; ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Product:</label>
                    <select name="product_id" id="product_id" required onchange="updateWeightIndicator()">
                        <option value="">Select Product</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" name="quantity" id="quantity" required min="1" onchange="updateWeightIndicator()" oninput="updateWeightIndicator()">
                </div>
            </div>

            <div class="form-group">
                <div class="weight-indicator mb-3" style="display: none;">
                    <label>Total Weight to Transfer:</label>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <span id="weightText" class="text-muted" style="min-width: 120px;"></span>
                    </div>
                </div>
                <label>Notes:</label>
                <textarea name="notes" rows="3" placeholder="Enter any additional notes..."></textarea>
            </div>

            <button type="submit" class="btn">Submit Transfer Request</button>
        </form>

        <h2 class="section-title">My Transfer Requests</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Requested At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($transfer = $transfersResult->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <img src="../assets/imgs/<?php echo htmlspecialchars($transfer['product_img']); ?>" 
                                     alt="<?php echo htmlspecialchars($transfer['ProductName']); ?>"
                                     class="product-img">
                                <?php echo htmlspecialchars($transfer['ProductName']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($transfer['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($transfer['from_name']); ?></td>
                            <td><?php echo htmlspecialchars($transfer['to_name']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                    <?php echo ucfirst(htmlspecialchars($transfer['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($transfer['requested_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        function updateProducts() {
            const warehouseId = document.getElementById('from_warehouse').value;
            const productSelect = document.getElementById('product_id');
            productSelect.innerHTML = '<option value="">Select Product</option>';

            if (warehouseId) {
                const warehouses = <?php echo json_encode($warehouses); ?>;
                const warehouse = warehouses[warehouseId];
                
                if (warehouse && warehouse.inventory) {
                    warehouse.inventory.forEach(item => {
                        if (item.quantity > 0) {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.dataset.weight = item.weight;
                            option.dataset.weightUnit = item.weight_unit;
                            option.textContent = `${item.name} (${item.quantity} in stock)`;
                            productSelect.appendChild(option);
                        }
                    });
                }
            }
        }

        function updateWeightIndicator() {
            const productSelect = document.getElementById('product_id');
            const quantityInput = document.getElementById('quantity');
            const weightIndicator = document.querySelector('.weight-indicator');
            const progressBar = weightIndicator.querySelector('.progress-bar');
            const weightText = document.getElementById('weightText');
            
            if (productSelect.value && quantityInput.value) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const weight = parseFloat(selectedOption.dataset.weight);
                const weightUnit = selectedOption.dataset.weightUnit;
                const quantity = parseInt(quantityInput.value);
                let totalWeight = weight * quantity;
                
                // Get destination warehouse capacity
                const toWarehouse = document.querySelector('[name="to_warehouse"]');
                const warehouseId = toWarehouse.value;
                if (warehouseId) {
                    const warehouses = <?php echo json_encode($warehouses); ?>;
                    const capacity = warehouses[warehouseId].Capacity;
                    const currentUsage = warehouses[warehouseId].current_usage;
                    const remainingCapacity = capacity - currentUsage;
                    
                    // Convert weight if units don't match
                    let displayWeight = totalWeight;
                    let displayUnit = weightUnit;
                    if (weightUnit === 'g' && warehouses[warehouseId].warehouse_weight_unit === 'kg') {
                        displayWeight = totalWeight / 1000; // Convert grams to kilograms
                        displayUnit = 'kg';
                    }
                    
                    // Calculate percentage of remaining capacity using converted weight
                    const percentageUsed = (displayWeight / remainingCapacity) * 100;
                    const colorClass = percentageUsed > 80 ? 'bg-danger' : 
                                     percentageUsed > 60 ? 'bg-warning' : 'bg-success';
                    
                    progressBar.className = `progress-bar ${colorClass}`;
                    progressBar.style.width = Math.min(percentageUsed, 100) + '%';
                    weightText.textContent = `${displayWeight.toFixed(1)} ${displayUnit} / ${remainingCapacity.toFixed(1)} ${warehouses[warehouseId].warehouse_weight_unit}`;
                    weightIndicator.style.display = 'block';
                }
            } else {
                weightIndicator.style.display = 'none';
            }
        }

        // Add event listener for destination warehouse change
        document.querySelector('[name="to_warehouse"]').addEventListener('change', updateWeightIndicator);

        // Handle "To Warehouse" options visibility
        document.getElementById('from_warehouse').addEventListener('change', function() {
            const fromId = this.value;
            const toOptions = document.querySelectorAll('.to-warehouse-option');
            
            toOptions.forEach(option => {
                if (option.dataset.fromId === fromId) {
                    option.style.display = 'none';
                    option.disabled = true;
                } else {
                    option.style.display = '';
                    option.disabled = false;
                }
            });
            
            // Reset "To Warehouse" selection if it matches "From Warehouse"
            const toWarehouse = document.querySelector('[name="to_warehouse"]');
            if (toWarehouse.value === fromId) {
                toWarehouse.value = '';
            }
        });

        // Add sidebar state listener to adjust content margin
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.body;
            const sidebarState = localStorage.getItem('sidebarState');
            
            if (sidebarState === 'collapsed' || sidebarState === 'hidden') {
                body.classList.add('sidebar-collapsed');
            }
            
            // Listen for sidebar state changes
            document.addEventListener('sidebarStateChange', function(e) {
                if (e.detail.state === 'collapsed' || e.detail.state === 'hidden') {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });
        });
    </script>
</body>
</html>
