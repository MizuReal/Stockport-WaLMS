<?php
session_start();
require_once 'session_check.php'; // Adjust path as needed
requireActiveLogin(); // This ensures user is logged in AND has Active status
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php'; // Make sure to include database connection

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle update order
    if (isset($_POST['action']) && $_POST['action'] === 'update_order') {
        $orderDetailId = $_POST['order_detail_id'];
        $quantity = $_POST['quantity'];
        $status = $_POST['status'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First, get current order status
            $checkStatusQuery = "SELECT co.Status, co.CustomerOrderID 
                               FROM orderdetails od 
                               JOIN customerorders co ON od.CustomerOrderID = co.CustomerOrderID 
                               WHERE od.OrderDetailID = ?";
            $statusStmt = $conn->prepare($checkStatusQuery);
            $statusStmt->bind_param("i", $orderDetailId);
            $statusStmt->execute();
            $currentStatus = $statusStmt->get_result()->fetch_assoc();
            
            // Validate status transitions
            if ($currentStatus['Status'] === 'Delivered') {
                throw new Exception("Delivered orders cannot be modified");
            }
            if ($currentStatus['Status'] === 'Shipped' && $status !== 'Cancelled') {
                throw new Exception("Shipped orders can only be cancelled");
            }
            
            // Proceed with update if validation passes
            if ($currentStatus['Status'] !== 'Shipped' || $status === 'Cancelled') {
                // Update orderdetails table
                $query = "UPDATE orderdetails SET Quantity = ? WHERE OrderDetailID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $quantity, $orderDetailId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update order details");
                }
                
                // Update customer order status
                $updateStatusQuery = "UPDATE customerorders SET Status = ? WHERE CustomerOrderID = ?";
                $custStmt = $conn->prepare($updateStatusQuery);
                $custStmt->bind_param("si", $status, $currentStatus['CustomerOrderID']);
                
                if (!$custStmt->execute()) {
                    throw new Exception("Failed to update order status");
                }
                
                $conn->commit();
                $response = ['success' => true];
            }
        } catch (Exception $e) {
            $conn->rollback();
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Handle delivery status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_delivery_status') {
        $orderDetailId = $_POST['order_detail_id'];
        $readyToShip = $_POST['ready_to_ship'];
        
        $query = "UPDATE orderdetails SET ReadyToShip = ? WHERE OrderDetailID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $readyToShip, $orderDetailId);
        
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = [
                'success' => false,
                'message' => 'Failed to update shipping status: ' . $stmt->error
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Handle deliver order
    if (isset($_POST['action']) && $_POST['action'] === 'deliver_order') {
        $customerOrderId = $_POST['customer_order_id'];
        
        // Update customer order status to Shipped instead of Delivered
        $query = "UPDATE customerorders SET Status = 'Shipped' WHERE CustomerOrderID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $customerOrderId);
        
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = [
                'success' => false,
                'message' => 'Failed to ship order: ' . $stmt->error
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <title>Client Order Tracker</title>
    <style>
        /* Remove the conflicting container style and update main-content */
        .main-content {
            margin-left: 60px; /* Default margin for collapsed sidebar */
            padding: 20px;
            transition: margin-left 0.3s ease;
            width: calc(100% - 60px);
            min-height: 100vh;
        }
        
        body.sidebar-expanded .main-content {
            margin-left: 220px;
            width: calc(100% - 220px);
        }
        
        body.sidebar-hidden .main-content {
            margin-left: 20px;
            width: calc(100% - 20px);
        }

        /* Adjust table container for better responsiveness */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            width: 100%;
        }

        /* Media query for mobile responsiveness */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            body.sidebar-expanded .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* Keep your existing styles */
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .order-table th, .order-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .order-table th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        .order-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .order-table tr:hover {
            background-color: #f1f1f1;
        }
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
        .status-badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #FFD700;
            color: #000;
        }
        .status-processing {
            background-color: #1E90FF;
            color: #fff;
        }
        .status-shipped {
            background-color: #9932CC;
            color: #fff;
        }
        .status-delivered {
            background-color: #32CD32;
            color: #fff;
        }
        .status-cancelled {
            background-color: #FF6347;
            color: #fff;
        }
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 2px;
        }
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        .btn-deliver {
            background-color: #FF9800;
            color: white;
        }
        .btn-view {
            background-color: #4CAF50;
            color: white;
        }
        .search-container {
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-box {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
        }
        .filter-select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-center {
            text-align: center;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 60%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .close-btn {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .modal-form-group {
            margin-bottom: 15px;
        }
        .modal-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .modal-form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .btn-save {
            background-color: #4CAF50;
            color: white;
        }
        .btn-cancel {
            background-color: #f44336;
            color: white;
            margin-right: 10px;
        }
        
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            background-color: #4CAF50;
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            display: none;
            z-index: 1001;
            animation: fadeIn 0.3s, fadeOut 0.3s 2.7s;
            max-width: 300px;
        }
        .notification.error {
            background-color: #f44336;
        }
        @keyframes fadeIn {
            from {opacity: 0;}
            to {opacity: 1;}
        }
        @keyframes fadeOut {
            from {opacity: 1;}
            to {opacity: 0;}
        }
    </style>
</head>
<body>
    <?php renderSidebar('clientOrderTracker'); ?>
    
    <div class="main-content">
        <?php renderHeader('Client Order Tracker'); ?>

        <div class="search-container">
            <input type="text" id="searchInput" class="search-box" placeholder="Search orders...">
            <div>
                <select id="statusFilter" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Shipped">Shipped</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <select id="customerFilter" class="filter-select">
                    <option value="">All Customers</option>
                    <?php
                    // Get all customers for dropdown
                    $customerQuery = "SELECT CustomerID, CustomerName FROM customers ORDER BY CustomerName";
                    $customerResult = mysqli_query($conn, $customerQuery);
                    while ($customer = mysqli_fetch_assoc($customerResult)) {
                        echo "<option value='{$customer['CustomerID']}'>{$customer['CustomerName']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="table-container">
            <table class="order-table" id="orderTable">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query to get all the necessary data from customerorders and orderdetails tables
                    $query = "
                        SELECT 
                            co.CustomerOrderID, 
                            co.OrderDate,
                            co.Status,
                            od.OrderDetailID,
                            od.Quantity,
                            od.UnitPrice,
                            p.ProductID, 
                            p.ProductName, 
                            p.product_img, 
                            c.CustomerID,
                            c.CustomerName
                        FROM customerorders co
                        INNER JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
                        INNER JOIN products p ON od.ProductID = p.ProductID
                        INNER JOIN customers c ON co.CustomerID = c.CustomerID
                        ORDER BY co.OrderDate DESC
                    ";
                    
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Calculate total amount
                            $totalAmount = $row['Quantity'] * $row['UnitPrice'];
                            
                            // Determine status class for styling
                            $statusClass = '';
                            switch ($row['Status']) {
                                case 'Pending':
                                    $statusClass = 'status-pending';
                                    break;
                                case 'Processing':
                                    $statusClass = 'status-processing';
                                    break;
                                case 'Shipped':
                                    $statusClass = 'status-shipped';
                                    break;
                                case 'Delivered':
                                    $statusClass = 'status-delivered';
                                    break;
                                case 'Cancelled':
                                    $statusClass = 'status-cancelled';
                                    break;
                            }
                            
                            // Format date
                            $orderDate = date('M d, Y', strtotime($row['OrderDate']));
                            
                            echo "<tr data-customer='{$row['CustomerID']}' data-status='{$row['Status']}'>";
                            echo "<td>{$row['CustomerOrderID']}</td>";
                            echo "<td>
                                    <div style='display: flex; align-items: center;'>
                                        <img src='../assets/imgs/{$row['product_img']}' alt='{$row['ProductName']}' class='product-img'>
                                        <span style='margin-left: 10px;'>{$row['ProductName']}</span>
                                    </div>
                                  </td>";
                            echo "<td>{$row['CustomerName']}</td>";
                            echo "<td>{$orderDate}</td>";
                            echo "<td><span class='status-badge {$statusClass}'>{$row['Status']}</span></td>";
                            echo "<td>{$row['Quantity']}</td>";
                            echo "<td>₱" . number_format($totalAmount, 2) . "</td>";
                            echo "<td>
                                    <a href='view_clientorder.php?id={$row['CustomerOrderID']}' class='btn btn-view'>View</a>";
                            
                            // Only show edit button if not delivered
                            if ($row['Status'] !== 'Delivered') {
                                echo "<button class='btn btn-edit' onclick='showEditModal({$row['OrderDetailID']}, \"{$row['ProductName']}\", \"{$row['CustomerName']}\", \"{$row['Status']}\", {$row['Quantity']})'>Edit</button>";
                            }
                            
                            if ($row['Status'] == 'Processing') {
                                echo "<button class='btn btn-deliver' onclick='shipOrder({$row['CustomerOrderID']})'>Ship</button>";
                            }
                            
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align: center;'>No orders found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div id="editOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Order</h3>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form id="editOrderForm">
                <input type="hidden" id="editOrderDetailId" name="order_detail_id">
                <input type="hidden" name="action" value="update_order">
                
                <div class="modal-form-group">
                    <label for="editProductName">Product</label>
                    <input type="text" id="editProductName" class="modal-form-control" readonly>
                </div>
                
                <div class="modal-form-group">
                    <label for="editCustomerName">Customer</label>
                    <input type="text" id="editCustomerName" class="modal-form-control" readonly>
                </div>
                
                <div class="modal-form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus" name="status" class="modal-form-control">
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="modal-form-group">
                    <label for="editQuantity">Quantity</label>
                    <input type="number" id="editQuantity" name="quantity" class="modal-form-control">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="button" class="btn btn-save" onclick="saveOrderChanges()">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification"></div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            filterTable();
        });
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            filterTable();
        });
        
        // Customer filter
        document.getElementById('customerFilter').addEventListener('change', function() {
            filterTable();
        });
        
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const customerFilter = document.getElementById('customerFilter').value;
            const rows = document.getElementById('orderTable').getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const textContent = row.textContent.toLowerCase();
                const rowStatus = row.getAttribute('data-status');
                const rowCustomer = row.getAttribute('data-customer');
                
                const statusMatch = !statusFilter || rowStatus === statusFilter;
                const customerMatch = !customerFilter || rowCustomer === customerFilter;
                const textMatch = textContent.includes(searchValue);
                
                if (textMatch && statusMatch && customerMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
        
        // Show notification
        function showNotification(message, isError = false) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification' + (isError ? ' error' : '');
            notification.style.display = 'block';
            
            // Hide after 3 seconds
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
        
        // Update shipping status
        function updateShippingStatus(checkbox, orderDetailId) {
            const readyToShip = checkbox.checked ? 1 : 0;
            
            const formData = new FormData();
            formData.append('action', 'update_delivery_status');
            formData.append('order_detail_id', orderDetailId);
            formData.append('ready_to_ship', readyToShip);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Shipping status updated successfully');
                } else {
                    showNotification('Failed to update shipping status: ' + data.message, true);
                    checkbox.checked = !checkbox.checked; // Revert the checkbox
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating shipping status', true);
                checkbox.checked = !checkbox.checked; // Revert the checkbox
            });
        }
        
        // Show edit modal
        function showEditModal(orderDetailId, productName, customerName, status, quantity) {
            // Populate form fields
            document.getElementById('editOrderDetailId').value = orderDetailId;
            document.getElementById('editProductName').value = productName;
            document.getElementById('editCustomerName').value = customerName;
            document.getElementById('editStatus').value = status;
            document.getElementById('editQuantity').value = quantity;
            
            // Configure status and fields based on current status
            const statusSelect = document.getElementById('editStatus');
            const quantityInput = document.getElementById('editQuantity');
            
            // Disable both fields if delivered
            if (status === 'Delivered') {
                statusSelect.disabled = true;
                quantityInput.disabled = true;
                return; // Don't show modal for delivered orders
            }
            
            // Handle shipped orders
            if (status === 'Shipped') {
                quantityInput.disabled = true;
                statusSelect.innerHTML = `
                    <option value="Shipped">Shipped</option>
                    <option value="Cancelled">Cancelled</option>
                `;
            } else {
                // For non-shipped orders, show normal progression
                quantityInput.disabled = false;
                switch(status) {
                    case 'Pending':
                        statusSelect.innerHTML = `
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Cancelled">Cancelled</option>
                        `;
                        break;
                    case 'Processing':
                        statusSelect.innerHTML = `
                            <option value="Processing">Processing</option>
                            <option value="Cancelled">Cancelled</option>
                        `;
                        break;
                    case 'Cancelled':
                        statusSelect.innerHTML = `
                            <option value="Cancelled">Cancelled</option>
                        `;
                        break;
                }
            }
            statusSelect.value = status;
            
            // Show modal
            document.getElementById('editOrderModal').style.display = 'block';
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('editOrderModal').style.display = 'none';
        }
        
        // Save order changes
        function saveOrderChanges() {
            const form = document.getElementById('editOrderForm');
            const quantity = document.getElementById('editQuantity').value;
            
            if (!quantity) {
                showNotification('Please fill out all required fields', true);
                return;
            }
            
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Order updated successfully!');
                    closeModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Failed to update order: ' + (data.message || 'Unknown error'), true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating the order', true);
            });
        }
        
        // Ship order (update status to shipped)
        function shipOrder(customerOrderId) {
            if (confirm('Are you sure you want to mark this order as shipped?')) {
                const formData = new FormData();
                formData.append('action', 'deliver_order');
                formData.append('customer_order_id', customerOrderId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Order successfully marked as shipped!');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Failed to ship order: ' + data.message, true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while processing the shipment', true);
                });
            }
        }
        
        // Close modal if clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editOrderModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>