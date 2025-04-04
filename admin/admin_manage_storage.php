<?php
include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transfer_id = $_POST['transfer_id'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['employeeID'];
    
    if ($action === 'approve' || $action === 'reject') {
        try {
            $conn->begin_transaction();
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            // Get transfer details first
            $transferQuery = "SELECT * FROM storage_transfers WHERE transfer_id = ? AND status = 'pending'";
            $transferStmt = $conn->prepare($transferQuery);
            $transferStmt->bind_param("i", $transfer_id);
            $transferStmt->execute();
            $transferResult = $transferStmt->get_result();
            
            if ($transferResult->num_rows === 0) {
                throw new Exception("Transfer request not found or already processed");
            }
            
            $transfer = $transferResult->fetch_assoc();
            
            // Update the transfer status
            $updateStmt = $conn->prepare("UPDATE storage_transfers 
                                       SET status = ?, approved_by = ?, approved_at = NOW() 
                                       WHERE transfer_id = ? AND status = 'pending'");
            $updateStmt->bind_param("sii", $status, $admin_id, $transfer_id);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update transfer status");
            }
            
            // If approved, update inventory and warehouse capacities
            if ($status === 'approved') {
                // Get product weight and unit
                $productQuery = "SELECT Weight, weight_unit FROM products WHERE ProductID = ?";
                $productStmt = $conn->prepare($productQuery);
                $productStmt->bind_param("i", $transfer['product_id']);
                $productStmt->execute();
                $productResult = $productStmt->get_result();
                $product = $productResult->fetch_assoc();
                
                $product_weight = $product['Weight'];
                $product_weight_unit = $product['weight_unit'];
                
                // Get warehouse units
                $fromWarehouseQuery = "SELECT warehouse_weight_unit, Capacity FROM products_warehouse WHERE productLocationID = ?";
                $fromWarehouseStmt = $conn->prepare($fromWarehouseQuery);
                $fromWarehouseStmt->bind_param("i", $transfer['from_warehouse']);
                $fromWarehouseStmt->execute();
                $fromWarehouseResult = $fromWarehouseStmt->get_result();
                $fromWarehouse = $fromWarehouseResult->fetch_assoc();
                
                $toWarehouseQuery = "SELECT warehouse_weight_unit, Capacity FROM products_warehouse WHERE productLocationID = ?";
                $toWarehouseStmt = $conn->prepare($toWarehouseQuery);
                $toWarehouseStmt->bind_param("i", $transfer['to_warehouse']);
                $toWarehouseStmt->execute();
                $toWarehouseResult = $toWarehouseStmt->get_result();
                $toWarehouse = $toWarehouseResult->fetch_assoc();
                
                // Calculate weight conversions
                $from_converted_weight = $product_weight * $transfer['quantity'];
                if ($product_weight_unit === 'g' && $fromWarehouse['warehouse_weight_unit'] === 'kg') {
                    $from_converted_weight *= 0.001;
                } else if ($product_weight_unit === 'kg' && $fromWarehouse['warehouse_weight_unit'] === 'g') {
                    $from_converted_weight *= 1000;
                }
                
                $to_converted_weight = $product_weight * $transfer['quantity'];
                if ($product_weight_unit === 'g' && $toWarehouse['warehouse_weight_unit'] === 'kg') {
                    $to_converted_weight *= 0.001;
                } else if ($product_weight_unit === 'kg' && $toWarehouse['warehouse_weight_unit'] === 'g') {
                    $to_converted_weight *= 1000;
                }
                
                // Update source warehouse inventory
                $checkFromQuery = "SELECT * FROM warehouse_inventory WHERE warehouse_id = ? AND product_id = ?";
                $checkFromStmt = $conn->prepare($checkFromQuery);
                $checkFromStmt->bind_param("ii", $transfer['from_warehouse'], $transfer['product_id']);
                $checkFromStmt->execute();
                $fromInventoryResult = $checkFromStmt->get_result();
                
                if ($fromInventoryResult->num_rows > 0) {
                    $updateFromQuery = "UPDATE warehouse_inventory 
                                      SET quantity = quantity - ?,
                                          current_usage = GREATEST(0, current_usage - ?)
                                      WHERE warehouse_id = ? AND product_id = ?";
                    $updateFromStmt = $conn->prepare($updateFromQuery);
                    $updateFromStmt->bind_param("idii", $transfer['quantity'], $from_converted_weight, 
                                            $transfer['from_warehouse'], $transfer['product_id']);
                    if (!$updateFromStmt->execute()) {
                        throw new Exception("Failed to update source warehouse inventory");
                    }
                } else {
                    $insertFromQuery = "INSERT INTO warehouse_inventory 
                                     (warehouse_id, product_id, quantity, warehouse_weight_unit, current_usage, capacity)
                                     VALUES (?, ?, -?, ?, ?, ?)";
                    $insertFromStmt = $conn->prepare($insertFromQuery);
                    $insertFromStmt->bind_param("iidsdd", $transfer['from_warehouse'], $transfer['product_id'], 
                                             $transfer['quantity'], $fromWarehouse['warehouse_weight_unit'], 
                                             $from_converted_weight, $fromWarehouse['Capacity']);
                    if (!$insertFromStmt->execute()) {
                        throw new Exception("Failed to create source warehouse inventory entry");
                    }
                }
                
                // Update destination warehouse inventory
                $checkToQuery = "SELECT * FROM warehouse_inventory WHERE warehouse_id = ? AND product_id = ?";
                $checkToStmt = $conn->prepare($checkToQuery);
                $checkToStmt->bind_param("ii", $transfer['to_warehouse'], $transfer['product_id']);
                $checkToStmt->execute();
                $toInventoryResult = $checkToStmt->get_result();
                
                if ($toInventoryResult->num_rows > 0) {
                    $updateToQuery = "UPDATE warehouse_inventory 
                                     SET quantity = quantity + ?,
                                         current_usage = current_usage + ?
                                     WHERE warehouse_id = ? AND product_id = ?";
                    $updateToStmt = $conn->prepare($updateToQuery);
                    $updateToStmt->bind_param("idii", $transfer['quantity'], $to_converted_weight, 
                                           $transfer['to_warehouse'], $transfer['product_id']);
                    if (!$updateToStmt->execute()) {
                        throw new Exception("Failed to update destination warehouse inventory");
                    }
                } else {
                    $insertToQuery = "INSERT INTO warehouse_inventory 
                                   (warehouse_id, product_id, quantity, warehouse_weight_unit, current_usage, capacity)
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    $insertToStmt = $conn->prepare($insertToQuery);
                    $insertToStmt->bind_param("iidsdd", $transfer['to_warehouse'], $transfer['product_id'], 
                                          $transfer['quantity'], $toWarehouse['warehouse_weight_unit'], 
                                          $to_converted_weight, $toWarehouse['Capacity']);
                    if (!$insertToStmt->execute()) {
                        throw new Exception("Failed to create destination warehouse inventory entry");
                    }
                }
                
                // Update warehouse capacities
                // First, calculate total usage for source warehouse
                $fromCapacityQuery = "SELECT COALESCE(SUM(current_usage), 0) as total_usage 
                                    FROM warehouse_inventory WHERE warehouse_id = ?";
                $fromCapacityStmt = $conn->prepare($fromCapacityQuery);
                $fromCapacityStmt->bind_param("i", $transfer['from_warehouse']);
                $fromCapacityStmt->execute();
                $fromUsageResult = $fromCapacityStmt->get_result();
                $fromUsage = $fromUsageResult->fetch_assoc();
                
                // Update source warehouse capacity
                $updateFromCapacityQuery = "UPDATE products_warehouse SET current_usage = ? WHERE productLocationID = ?";
                $updateFromCapacityStmt = $conn->prepare($updateFromCapacityQuery);
                $updateFromCapacityStmt->bind_param("di", $fromUsage['total_usage'], $transfer['from_warehouse']);
                if (!$updateFromCapacityStmt->execute()) {
                    throw new Exception("Failed to update source warehouse capacity");
                }
                
                // Calculate total usage for destination warehouse
                $toCapacityQuery = "SELECT COALESCE(SUM(current_usage), 0) as total_usage 
                                  FROM warehouse_inventory WHERE warehouse_id = ?";
                $toCapacityStmt = $conn->prepare($toCapacityQuery);
                $toCapacityStmt->bind_param("i", $transfer['to_warehouse']);
                $toCapacityStmt->execute();
                $toUsageResult = $toCapacityStmt->get_result();
                $toUsage = $toUsageResult->fetch_assoc();
                
                // Update destination warehouse capacity
                $updateToCapacityQuery = "UPDATE products_warehouse SET current_usage = ? WHERE productLocationID = ?";
                $updateToCapacityStmt = $conn->prepare($updateToCapacityQuery);
                $updateToCapacityStmt->bind_param("di", $toUsage['total_usage'], $transfer['to_warehouse']);
                if (!$updateToCapacityStmt->execute()) {
                    throw new Exception("Failed to update destination warehouse capacity");
                }
                
                // Record in transfer history
                $historyQuery = "INSERT INTO transfer_history 
                              (transfer_id, product_id, quantity, from_warehouse, to_warehouse, status)
                              VALUES (?, ?, ?, ?, ?, ?)";
                $historyStmt = $conn->prepare($historyQuery);
                $historyStmt->bind_param("iiiiis", $transfer['transfer_id'], $transfer['product_id'], 
                                      $transfer['quantity'], $transfer['from_warehouse'], 
                                      $transfer['to_warehouse'], $status);
                if (!$historyStmt->execute()) {
                    throw new Exception("Failed to update transfer history");
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Transfer request " . $status;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    elseif ($action === 'in_transit' || $action === 'complete') {
        try {
            $new_status = ($action === 'in_transit') ? 'in_transit' : 'transferred';
            
            // Simplified query that only updates the status and completed_at
            $stmt = $conn->prepare("UPDATE storage_transfers 
                                  SET status = ?, 
                                      completed_at = CASE WHEN ? = 'transferred' THEN NOW() ELSE NULL END 
                                  WHERE transfer_id = ?");
            $stmt->bind_param("ssi", $new_status, $new_status, $transfer_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Transfer marked as " . $new_status;
            } else {
                throw new Exception("Failed to update transfer status");
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Rest of your code for fetching transfers remains the same
// Fetch all transfer requests with additional warehouse capacity information
$transfersQuery = "SELECT st.*, 
    p.ProductName,
    p.product_img,
    p.Weight as product_weight,
    p.weight_unit as product_weight_unit,
    CONCAT(fw.productWarehouse, ' - ', fw.Section) as from_name,
    CONCAT(tw.productWarehouse, ' - ', tw.Section) as to_name,
    fw.current_usage as from_current_usage,
    fw.Capacity as from_capacity,
    fw.remaining_capacity as from_remaining,
    tw.current_usage as to_current_usage,
    tw.Capacity as to_capacity,
    tw.remaining_capacity as to_remaining,
    e.FirstName as requestor_name,
    a.FirstName as approver_name
    FROM storage_transfers st
    JOIN products p ON st.product_id = p.ProductID 
    JOIN products_warehouse fw ON st.from_warehouse = fw.productLocationID
    JOIN products_warehouse tw ON st.to_warehouse = tw.productLocationID
    JOIN employees e ON st.requested_by = e.EmployeeID
    LEFT JOIN employees a ON st.approved_by = a.EmployeeID
    ORDER BY st.requested_at DESC";
$transfersResult = $conn->query($transfersQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Storage Transfers - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            background-color: #f8f9fa;
        }

        .table {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-in_transit { background-color: #cce5ff; color: #004085; }
        .status-transferred { background-color: #d1e7dd; color: #0f5132; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }

        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            margin: 0.25rem;
        }

        .alert {
            margin-bottom: 1.5rem;
        }
        
        .capacity-bar {
            height: 20px;
            border-radius: 10px;
            margin-top: 5px;
            overflow: hidden;
            background-color: #e9ecef;
        }
        
        .capacity-fill {
            height: 100%;
            background-color: #0d6efd;
        }
        
        .capacity-warning {
            background-color: #ffc107;
        }
        
        .capacity-danger {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="container-fluid">
                <h2 class="mb-4">Storage Transfer Requests</h2>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Requested By</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transfer = $transfersResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $transfer['transfer_id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($transfer['ProductName']); ?>
                                                <div class="small text-muted">
                                                    Weight: <?php echo htmlspecialchars($transfer['product_weight'] . ' ' . $transfer['product_weight_unit']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($transfer['quantity']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($transfer['from_name']); ?>
                                                <div class="small text-muted">
                                                    Capacity: <?php echo round($transfer['from_current_usage'], 2) . ' / ' . $transfer['from_capacity']; ?>
                                                </div>
                                                <?php
                                                    $fromPercentage = ($transfer['from_current_usage'] / $transfer['from_capacity']) * 100;
                                                    $fromColorClass = '';
                                                    if ($fromPercentage > 90) $fromColorClass = 'capacity-danger';
                                                    else if ($fromPercentage > 70) $fromColorClass = 'capacity-warning';
                                                ?>
                                                <div class="capacity-bar">
                                                    <div class="capacity-fill <?php echo $fromColorClass; ?>" style="width: <?php echo min(100, $fromPercentage); ?>%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($transfer['to_name']); ?>
                                                <div class="small text-muted">
                                                    Capacity: <?php echo round($transfer['to_current_usage'], 2) . ' / ' . $transfer['to_capacity']; ?>
                                                </div>
                                                <?php
                                                    $toPercentage = ($transfer['to_current_usage'] / $transfer['to_capacity']) * 100;
                                                    $toColorClass = '';
                                                    if ($toPercentage > 90) $toColorClass = 'capacity-danger';
                                                    else if ($toPercentage > 70) $toColorClass = 'capacity-warning';
                                                ?>
                                                <div class="capacity-bar">
                                                    <div class="capacity-fill <?php echo $toColorClass; ?>" style="width: <?php echo min(100, $toPercentage); ?>%"></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($transfer['requestor_name']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $transfer['status']; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($transfer['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="transfer_id" value="<?php echo $transfer['transfer_id']; ?>">
                                                    
                                                    <?php if ($transfer['status'] === 'pending'): ?>
                                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-action">
                                                            <i class="bi bi-check-lg"></i> Approve
                                                        </button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-action">
                                                            <i class="bi bi-x-lg"></i> Reject
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($transfer['status'] === 'approved'): ?>
                                                        <button type="submit" name="action" value="in_transit" class="btn btn-info btn-action">
                                                            <i class="bi bi-truck"></i> Mark In Transit
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($transfer['status'] === 'in_transit'): ?>
                                                        <button type="submit" name="action" value="complete" class="btn btn-primary btn-action">
                                                            <i class="bi bi-check-circle"></i> Complete
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Empty script tag can be removed if not needed for other functionality
    </script>
</body>
</html>