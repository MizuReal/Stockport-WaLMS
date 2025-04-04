<?php
session_start();
require_once 'session_check.php'; // Adjust path as needed
requireActiveLogin(); // This ensures user is logged in AND has Active status
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <title>Material Order History</title>
    <style>
        .table-container {
            margin: 2px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .table-responsive {
            overflow-x: auto;
            max-width: 100%;
        }
        .table-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color:rgb(255, 255, 255);
            border-bottom: 1px solid #e0e0e0;
        }
        .table-top h2 {
            margin: 0;
            color: #333;
            font-size: 1.3rem;
        }
        .search-container {
            display: flex;
            align-items: center;
        }
        .search-container input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        .filter-dropdown {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background-color: #f7f7f7;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background-color: #f9f9f9;
        }

        /* Column-specific styles */
        .order-id,
        .start-date,
        .end-date,
        .status,
        .qty-ordered,
        .qty-produced,
        .total-weight,
        .raw-material-required {
            width: 8%;
            padding: 8px 5px;
        }
        .product,
        .material,
        .warehouse {
            width: 15%;
        }
        .ordered-by {
            width: 10%;
        }
        .actions {
            width: 10%;
        }

        .material-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-in-progress {
            background-color: #fff8e1;
            color: #ffa000;
        }
        .status-completed {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        .status-cancelled {
            background-color: #ffebee;
            color: #f44336;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .update-btn {
            background-color: #4285f4;
            color: white;
        }
        .update-btn:hover {
            background-color: #2a75f3;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            padding: 0;
        }
        .pagination li {
            list-style: none;
            margin: 0 5px;
        }
        .pagination li a {
            display: block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
        }
        .pagination li.active a {
            background-color: #4285f4;
            color: white;
            border-color: #4285f4;
        }
        /* Modal styles */
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
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #777;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            border: none;
        }
        .cancel-btn {
            background-color: #f1f1f1;
            color: #333;
        }
        .submit-btn {
            background-color: #4285f4;
            color: white;
        }
    </style>
</head>
<body>
    <?php renderSidebar('materialOrderHistory'); ?>
    
    <div class="content-wrapper">
        <?php renderHeader('Material Order History'); ?>
        
        <div class="table-container">
            <div class="table-top">
                <h2>Production Orders</h2>
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search orders...">
                    <select class="filter-dropdown" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="ordersTable">
                    <thead>
                    <tr>
                        <th class="order-id">Order ID</th>
                        <th class="product">Product</th>
                        <th class="material">Material</th>
                        <th class="ordered-by">Ordered By</th>
                        <th class="start-date">Start Date</th>
                        <th class="end-date">End Date</th>
                        <th class="warehouse">Warehouse</th>
                        <th class="status">Status</th>
                        <th class="qty-ordered">Quantity Ordered</th>
                        <th class="qty-produced">Quantity Produced</th>
                        <th class="total-weight">Total Weight</th>
                        <th class="raw-material-required">Raw Material Required</th>
                        <th class="actions">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Connect to the database
                    require_once '../server/database.php';

                    // Set items per page
                    $items_per_page = 5;

                    // Get current page
                    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $offset = ($current_page - 1) * $items_per_page;

                    // Prepare query to fetch orders with all required information
                    $query = "
                        SELECT 
                            po.OrderID,
                            p.ProductID,
                            p.ProductName,
                            r.MaterialID,
                            r.MaterialName,
                            r.raw_material_img,
                            CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                            po.StartDate,
                            po.EndDate,
                            pw.productWarehouse AS Warehouse,
                            po.Status,
                            po.QuantityOrdered,
                            po.QuantityProduced,
                            (p.Weight * po.QuantityOrdered) AS TotalWeight,
                            p.weight_unit,
                            p.minimum_quantity -- Ratio for calculating raw material required
                        FROM 
                            productionorders po
                        JOIN 
                            products p ON po.ProductID = p.ProductID
                        JOIN 
                            rawmaterials r ON p.MaterialID = r.MaterialID
                        JOIN 
                            employees e ON po.EmployeeID = e.EmployeeID
                        JOIN 
                            products_warehouse pw ON po.warehouseID = pw.productLocationID
                        ORDER BY 
                            po.OrderID DESC
                        LIMIT ? OFFSET ?
                    ";

                    // Get total number of records for pagination
                    $total_query = "SELECT COUNT(*) as total FROM productionorders";
                    $total_result = $conn->query($total_query);
                    $total_rows = $total_result->fetch_assoc()['total'];
                    $total_pages = ceil($total_rows / $items_per_page);

                    // Prepare and execute the main query
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $items_per_page, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Determine status class for styling
                            $statusClass = '';
                            switch ($row['Status']) {
                                case 'In Progress':
                                    $statusClass = 'status-in-progress';
                                    break;
                                case 'Completed':
                                    $statusClass = 'status-completed';
                                    break;
                                case 'Cancelled':
                                    $statusClass = 'status-cancelled';
                                    break;
                            }

                            // Format dates
                            $startDate = date('M d, Y', strtotime($row['StartDate']));
                            $endDate = $row['EndDate'] ? date('M d, Y', strtotime($row['EndDate'])) : 'N/A';

                            // Format total weight with unit
                            $totalWeight = number_format($row['TotalWeight'], 2) . ' ' . $row['weight_unit'];

                            // Calculate raw material required using minimum_quantity
                            $rawMaterialRequired = ceil($row['QuantityOrdered'] / $row['minimum_quantity']);

                            echo "<tr>";
                            echo "<td class='order-id'>{$row['OrderID']}</td>";
                            echo "<td class='product'>{$row['ProductName']}</td>";
                            echo "<td class='material'>
                                    <div style='display: flex; align-items: center;'>
                                        <img src='../assets/imgs/{$row['raw_material_img']}' class='material-img' alt='{$row['MaterialName']}'>
                                        <span style='margin-left: 10px;'>{$row['MaterialName']}</span>
                                    </div>
                                </td>";
                            echo "<td class='ordered-by'>{$row['EmployeeName']}</td>";
                            echo "<td class='start-date'>{$startDate}</td>";
                            echo "<td class='end-date'>{$endDate}</td>";
                            echo "<td class='warehouse'>{$row['Warehouse']}</td>";
                            echo "<td class='status'><span class='status-badge {$statusClass}'>{$row['Status']}</span></td>";
                            echo "<td class='qty-ordered'>" . number_format($row['QuantityOrdered']) . "</td>";
                            echo "<td class='qty-produced'>" . number_format($row['QuantityProduced']) . "</td>";
                            echo "<td class='total-weight'>{$totalWeight}</td>";
                            echo "<td class='raw-material-required'>" . number_format($rawMaterialRequired) . "</td>";
                            echo "<td class='actions'>
                                    <button class='action-btn update-btn' onclick='openUpdateModal({$row['OrderID']}, \"{$row['Status']}\", {$row['QuantityProduced']}, {$row['QuantityOrdered']}, {$row['minimum_quantity']})'>
                                        <i class='fas fa-edit'></i> Update
                                    </button>
                                </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='13' style='text-align: center;'>No orders found</td>";
                    }

                    $conn->close();
                    ?>
                    </tbody>
                </table>
            </div>
            
            <ul class="pagination">
                <?php if($current_page > 1): ?>
                    <li><a href="?page=<?php echo ($current_page - 1); ?>">&laquo; Previous</a></li>
                <?php endif; ?>
                
                <?php for($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <li class="<?php echo ($i == $current_page) ? 'active' : ''; ?>">
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if($current_page < $total_pages): ?>
                    <li><a href="?page=<?php echo ($current_page + 1); ?>">Next &raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Update Order Modal -->
        <div id="updateModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Update Order</h3>
                    <span class="close-btn" onclick="closeUpdateModal()">&times;</span>
                </div>
                <form id="updateOrderForm" method="post" action="update_order.php">
                    <input type="hidden" id="orderId" name="orderId">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantityProduced">Quantity Produced:</label>
                        <input type="number" id="quantityProduced" name="quantityProduced" required min="0">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel-btn" onclick="closeUpdateModal()">Cancel</button>
                        <button type="submit" class="modal-btn submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('ordersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) { // Skip header row
                let found = false;
                const cells = rows[i].getElementsByTagName('td');
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.indexOf(searchText) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            const filterValue = this.value.toLowerCase();
            const table = document.getElementById('ordersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) { // Skip header row
                const statusCell = rows[i].getElementsByTagName('td')[7]; // Status column
                if (!statusCell) continue;
                
                const statusText = statusCell.textContent.toLowerCase();
                rows[i].style.display = filterValue === '' || statusText === filterValue.toLowerCase() ? '' : 'none';
            }
        });
        
        // Modal functions
        function openUpdateModal(orderId, currentStatus, currentQuantityProduced, maxQuantity) {
            document.getElementById('orderId').value = orderId;
            document.getElementById('status').value = currentStatus;
            const quantityInput = document.getElementById('quantityProduced');
            quantityInput.value = currentQuantityProduced;
            quantityInput.max = maxQuantity;
            document.getElementById('updateModal').style.display = 'block';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target === modal) {
                closeUpdateModal();
            }
        };

        // Form validation for updateOrderForm
        document.getElementById('updateOrderForm').addEventListener('submit', function(event) {
            const status = document.getElementById('status').value;
            const quantityProduced = parseInt(document.getElementById('quantityProduced').value, 10);
            const maxQuantity = parseInt(document.getElementById('quantityProduced').max, 10);

            // Clear previous error messages
            const errorElement = document.getElementById('formError');
            if (errorElement) {
                errorElement.remove();
            }

            // Validate quantity produced for "Completed" status
            if (status === 'Completed' && quantityProduced !== maxQuantity) {
                event.preventDefault(); // Prevent form submission

                // Create and display error message
                const errorMessage = document.createElement('div');
                errorMessage.id = 'formError';
                errorMessage.style.color = 'red';
                errorMessage.style.marginBottom = '10px';
                errorMessage.textContent = 'To mark as completed, quantity produced must equal quantity ordered.';
                
                const form = document.getElementById('updateOrderForm');
                form.insertBefore(errorMessage, form.firstChild);

                return false; // Stop further processing
            }

            return true; // Allow form submission
        });
    </script>
</body>
</html>