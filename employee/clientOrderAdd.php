<?php
session_start();
require_once 'session_check.php'; // Adjust path as needed
requireActiveLogin(); // This ensures user is logged in AND has Active status
require_once '../layouts/employeeSidebar.php';
require_once '../layouts/employeeHeader.php';
require_once '../server/database.php'; // Ensure this path is correct

// Get the employee ID from the session
$employeeID = $_SESSION['employeeID'];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customerID = $_POST['customerID'];
    $productID = $_POST['productID'];
    $quantity = $_POST['quantity'];
    $deliveryDate = $_POST['deliveryDate'];
    $orderDate = date('Y-m-d H:i:s');
    $ticketID = isset($_POST['ticketID']) ? $_POST['ticketID'] : null;
    
    // Check if requested quantity is available from completed production orders
    $availabilityCheck = $conn->prepare("
        SELECT 
            COALESCE(
                (
                    SELECT SUM(po.QuantityProduced)
                    FROM productionorders po
                    WHERE po.ProductID = ? 
                    AND po.Status = 'Completed'
                ), 0
            ) - 
            COALESCE(
                (
                    SELECT SUM(pdt.quantity_delivered)
                    FROM production_delivery_tracking pdt
                    JOIN productionorders po ON pdt.production_order_id = po.OrderID
                    WHERE po.ProductID = ?
                ), 0
            ) as available_quantity
    ");

    if (!$availabilityCheck) {
        die("Prepare failed: " . $conn->error);
    }

    $availabilityCheck->bind_param("ii", $productID, $productID);
    if (!$availabilityCheck->execute()) {
        die("Execute failed: " . $availabilityCheck->error);
    }

    $availabilityResult = $availabilityCheck->get_result();
    if (!$availabilityResult) {
        die("Result failed: " . $availabilityCheck->error);
    }

    $availability = $availabilityResult->fetch_assoc();
    
    if ($availability === null || $availability['available_quantity'] < $quantity) {
        $error_message = "Insufficient processed quantity available. Available: " . 
            ($availability ? $availability['available_quantity'] : 0);
    } else {
        // Get product price with error handling
        $stmt = $conn->prepare("SELECT SellingPrice FROM products WHERE ProductID = ?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $productID);
        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            die("Result failed: " . $stmt->error);
        }

        $product = $result->fetch_assoc();
        if (!$product) {
            die("Product not found");
        }

        $unitPrice = $product['SellingPrice'];
        $totalAmount = $unitPrice * $quantity;

        try {
            // Start transaction
            $conn->begin_transaction();

            // Insert into customerorders with error handling
            $orderStmt = $conn->prepare("INSERT INTO customerorders (CustomerID, OrderDate, TotalAmount, Status) VALUES (?, ?, ?, 'Pending')");
            if (!$orderStmt) {
                throw new Exception("Order prepare failed: " . $conn->error);
            }

            $orderStmt->bind_param("isd", $customerID, $orderDate, $totalAmount);
            if (!$orderStmt->execute()) {
                throw new Exception("Order insert failed: " . $orderStmt->error);
            }

            $customerOrderID = $conn->insert_id;

            // Insert order details with error handling
            $detailStmt = $conn->prepare("INSERT INTO orderdetails (CustomerOrderID, ProductID, Quantity, UnitPrice, EmployeeID) VALUES (?, ?, ?, ?, ?)");
            if (!$detailStmt) {
                throw new Exception("Detail prepare failed: " . $conn->error);
            }

            $detailStmt->bind_param("iiidi", $customerOrderID, $productID, $quantity, $unitPrice, $employeeID);
            if (!$detailStmt->execute()) {
                throw new Exception("Detail insert failed: " . $detailStmt->error);
            }
            
            $orderDetailID = $conn->insert_id;

            // Find production orders with proper error handling
            $findProductionOrders = $conn->prepare("
                SELECT 
                    po.OrderID, 
                    po.QuantityProduced,
                    COALESCE(
                        (SELECT SUM(quantity_delivered)
                         FROM production_delivery_tracking
                         WHERE production_order_id = po.OrderID)
                    , 0) as already_delivered
                FROM productionorders po
                WHERE po.ProductID = ?
                AND po.Status = 'Completed'
                HAVING (po.QuantityProduced - already_delivered) > 0
                ORDER BY po.OrderID
            ");

            if (!$findProductionOrders) {
                throw new Exception("Production orders query failed: " . $conn->error);
            }

            $findProductionOrders->bind_param("i", $productID);
            if (!$findProductionOrders->execute()) {
                throw new Exception("Production orders execute failed: " . $findProductionOrders->error);
            }

            $productionOrdersResult = $findProductionOrders->get_result();
            if (!$productionOrdersResult) {
                throw new Exception("Production orders result failed");
            }

            $remainingQuantity = $quantity;
            while ($row = $productionOrdersResult->fetch_assoc()) {
                if ($remainingQuantity <= 0) break;

                $availableFromThis = $row['QuantityProduced'] - $row['already_delivered'];
                $quantityToTake = min($remainingQuantity, $availableFromThis);

                // Insert delivery tracking with error handling
                $trackDelivery = $conn->prepare("
                    INSERT INTO production_delivery_tracking 
                    (production_order_id, quantity_delivered, order_detail_id)
                    VALUES (?, ?, ?)
                ");

                if (!$trackDelivery) {
                    throw new Exception("Track delivery prepare failed: " . $conn->error);
                }

                $trackDelivery->bind_param("iii", $row['OrderID'], $quantityToTake, $orderDetailID);
                if (!$trackDelivery->execute()) {
                    throw new Exception("Track delivery insert failed: " . $trackDelivery->error);
                }

                $remainingQuantity -= $quantityToTake;
            }

            if ($remainingQuantity > 0) {
                throw new Exception("Could not allocate full quantity from production orders");
            }
            
            // Delete the ticket if one was used
            if ($ticketID) {
                $deleteTicket = $conn->prepare("DELETE FROM customerticket WHERE TicketID = ?");
                if (!$deleteTicket) {
                    throw new Exception("Delete ticket prepare failed: " . $conn->error);
                }
                
                $deleteTicket->bind_param("i", $ticketID);
                if (!$deleteTicket->execute()) {
                    throw new Exception("Delete ticket failed: " . $deleteTicket->error);
                }
            }

            $conn->commit();
            $_SESSION['success_message'] = "Customer order added successfully!";
            header("Location: clientOrderAdd.php?success=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch customers for dropdown
$customerQuery = "SELECT CustomerID, CustomerName FROM customers WHERE customer_status = 'approved' ORDER BY CustomerName";
$customerResult = $conn->query($customerQuery);

// Modify the product query to properly handle production orders and delivery tracking
$productQuery = "
    SELECT 
        p.ProductID, 
        p.ProductName, 
        p.SellingPrice,
        (
            COALESCE(
                (
                    SELECT SUM(po.QuantityProduced)
                    FROM productionorders po
                    WHERE po.ProductID = p.ProductID 
                    AND po.Status = 'Completed'
                ), 0
            ) - 
            COALESCE(
                (
                    SELECT SUM(pdt.quantity_delivered)
                    FROM production_delivery_tracking pdt
                    JOIN productionorders po ON pdt.production_order_id = po.OrderID
                    WHERE po.ProductID = p.ProductID
                ), 0
            )
        ) as available_quantity
    FROM products p
    HAVING available_quantity > 0
    ORDER BY p.ProductName
";

$productResult = $conn->query($productQuery);

if (!$productResult) {
    die("Error in product query: " . $conn->error);
}

// Store products data for reuse with error checking
$products = [];
if ($productResult && $productResult->num_rows > 0) {
    while($product = $productResult->fetch_assoc()) {
        $products[] = $product;
    }
}

// Fetch open customer tickets
$ticketQuery = "
    SELECT 
        ct.TicketID,
        ct.CompanyName,
        c.CustomerID,
        p.ProductID,
        p.ProductName,
        ct.Quantity,
        ct.ExpectedDeliveryDate,
        p.SellingPrice
    FROM customerticket ct
    JOIN products p ON ct.ProductID = p.ProductID
    JOIN customers c ON ct.CompanyName = c.CustomerName
    ORDER BY ct.CreatedAt DESC
";
$ticketResult = $conn->query($ticketQuery);

if (!$ticketResult) {
    die("Error in ticket query: " . $conn->error);
}

// Store tickets data for reuse
$tickets = [];
if ($ticketResult && $ticketResult->num_rows > 0) {
    while($ticket = $ticketResult->fetch_assoc()) {
        $tickets[] = $ticket;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/eminventory.css">
    <title>Add Client Orders</title>
    <style>
        .main-content {
            margin-left: 60px; /* Initial state */
            transition: margin-left 0.3s ease;
            padding: 20px;
        }
        
        body.sidebar-expanded .main-content {
            margin-left: 220px;
        }
        
        body.sidebar-hidden .main-content {
            margin-left: 20px;
        }
        
        .form-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-container {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #f44336;
            color: white;
        }
        .error-message {
            color: #f44336;
            margin-top: 15px;
        }
        .product-stats {
            margin-bottom: 30px;
        }
        
        .stats-table {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .product-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .product-table th,
        .product-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .product-table th {
            background-color: #f5f5f5;
        }
        
        .available-high {
            color: #4CAF50;
        }
        
        .available-medium {
            color: #ff9800;
        }
        
        .available-low {
            color: #f44336;
        }
        
        /* New styles for tickets section */
        .tickets-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ticket-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .ticket-card:hover {
            background-color: #f9f9f9;
        }
        
        .ticket-card.selected {
            border-color: #4CAF50;
            background-color: rgba(76, 175, 80, 0.1);
        }
        
        .ticket-header {
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .ticket-details {
            display: flex;
            flex-wrap: wrap;
        }
        
        .ticket-detail {
            flex: 1;
            min-width: 200px;
            margin-bottom: 5px;
        }
        
        .ticket-detail span {
            font-weight: bold;
        }
        
        .success-message {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            animation: fadeOut 0.5s ease-in-out 3s forwards;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
        
    </style>
</head>
<body>
    <?php renderSidebar('clientOrderAdd'); ?>
    
    <div class="main-content">
        <?php renderHeader('Add Client Orders'); ?>
        
        <?php if (isset($_GET['success']) && isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Customer Tickets Section -->
        <?php if (!empty($tickets)): ?>
        <div class="tickets-container">
            <h3>Customer Tickets</h3>
            <p>Select a ticket to auto-fill the order form:</p>
            
            <div class="tickets-list">
                <?php foreach($tickets as $index => $ticket): ?>
                <div class="ticket-card" id="ticket-<?php echo $ticket['TicketID']; ?>" 
                     data-ticket-id="<?php echo $ticket['TicketID']; ?>"
                     data-customer-id="<?php echo $ticket['CustomerID']; ?>"
                     data-product-id="<?php echo $ticket['ProductID']; ?>"
                     data-quantity="<?php echo $ticket['Quantity']; ?>"
                     data-delivery-date="<?php echo $ticket['ExpectedDeliveryDate']; ?>">
                    <div class="ticket-header">
                        <div>Ticket #<?php echo $ticket['TicketID']; ?> - <?php echo htmlspecialchars($ticket['CompanyName']); ?></div>
                        <div>Created: <?php echo date('M d, Y', strtotime($ticket['ExpectedDeliveryDate'])); ?></div>
                    </div>
                    <div class="ticket-details">
                        <div class="ticket-detail"><span>Product:</span> <?php echo htmlspecialchars($ticket['ProductName']); ?></div>
                        <div class="ticket-detail"><span>Quantity:</span> <?php echo $ticket['Quantity']; ?></div>
                        <div class="ticket-detail"><span>Expected Delivery:</span> <?php echo date('M d, Y', strtotime($ticket['ExpectedDeliveryDate'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Product statistics section -->
        <div class="product-stats">
            <div class="stats-table">
                <h3>Available Processed Products</h3>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Available Quantity</th>
                            <th>Price (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $product): 
                            $availabilityClass = '';
                            if($product['available_quantity'] > 100) {
                                $availabilityClass = 'available-high';
                            } elseif($product['available_quantity'] > 50) {
                                $availabilityClass = 'available-medium';
                            } else {
                                $availabilityClass = 'available-low';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['ProductName']); ?></td>
                            <td class="<?php echo $availabilityClass; ?>">
                                <?php echo $product['available_quantity']; ?>
                            </td>
                            <td><?php echo number_format($product['SellingPrice'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Order form container -->
        <div class="form-container">                
            <form method="POST" action="" id="orderForm">
                <!-- Hidden field for ticket ID if selected -->
                <input type="hidden" name="ticketID" id="ticketID" value="">
                
                <div class="form-group">
                    <label for="customerID">Customer:</label>
                    <select name="customerID" id="customerID" class="form-control" required>
                        <option value="">-- Select Customer --</option>
                        <?php 
                        // Reset the result pointer to the beginning
                        if ($customerResult) {
                            $customerResult->data_seek(0);
                            while($customer = $customerResult->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $customer['CustomerID']; ?>">
                                <?php echo htmlspecialchars($customer['CustomerName']); ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="productID">Product:</label>
                    <select name="productID" id="productID" class="form-control" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach($products as $product): ?>
                            <option value="<?php echo $product['ProductID']; ?>" 
                                    data-price="<?php echo $product['SellingPrice']; ?>"
                                    data-available="<?php echo $product['available_quantity']; ?>">
                                <?php echo htmlspecialchars($product['ProductName']); ?> 
                                (₱<?php echo number_format($product['SellingPrice'], 2); ?>) 
                                [Available: <?php echo $product['available_quantity']; ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="totalPrice">Total Price:</label>
                    <input type="text" id="totalPrice" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="deliveryDate">Delivery Date:</label>
                    <input type="datetime-local" name="deliveryDate" id="deliveryDate" class="form-control" required>
                </div>
                
                <div class="btn-container">
                    <a href="clientOrders.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Order</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Calculate total price when product or quantity changes
        document.getElementById('productID').addEventListener('change', updateTotalPrice);
        document.getElementById('quantity').addEventListener('input', updateTotalPrice);
        
        function updateTotalPrice() {
            const productSelect = document.getElementById('productID');
            const quantity = document.getElementById('quantity').value;
            const totalPriceField = document.getElementById('totalPrice');
            
            if (productSelect.selectedIndex > 0 && quantity > 0) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                const total = price * quantity;
                totalPriceField.value = '₱' + total.toFixed(2);
            } else {
                totalPriceField.value = '';
            }
        }
        
        // Set minimum date for delivery to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().slice(0, 16);
        document.getElementById('deliveryDate').min = tomorrowStr;

        // Add quantity validation
        document.getElementById('quantity').addEventListener('change', function() {
            const productSelect = document.getElementById('productID');
            if (productSelect.selectedIndex > 0) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const availableQuantity = parseInt(selectedOption.getAttribute('data-available'));
                const requestedQuantity = parseInt(this.value);
                
                if (requestedQuantity > availableQuantity) {
                    alert(`Only ${availableQuantity} units available!`);
                    this.value = availableQuantity;
                    updateTotalPrice();
                }
            }
        });
        
        // Ticket selection functionality
        // Ticket selection functionality
        const tickets = document.querySelectorAll('.ticket-card');
        if (tickets) {
            tickets.forEach(ticket => {
                ticket.addEventListener('click', function() {
                    // Remove selected class from all tickets
                    tickets.forEach(t => {
                        t.classList.remove('selected');
                        // Remove any existing warning messages
                        const existingWarning = t.querySelector('.stock-warning');
                        if (existingWarning) {
                            existingWarning.remove();
                        }
                    });
                    
                    // Add selected class to clicked ticket
                    this.classList.add('selected');
                    
                    // Get data attributes
                    const ticketID = this.getAttribute('data-ticket-id');
                    const customerID = this.getAttribute('data-customer-id');
                    const productID = this.getAttribute('data-product-id');
                    const quantity = this.getAttribute('data-quantity');
                    const deliveryDate = this.getAttribute('data-delivery-date');
                    
                    // Set hidden ticket ID field
                    document.getElementById('ticketID').value = ticketID;
                    
                    // Set form fields
                    const customerSelect = document.getElementById('customerID');
                    for (let i = 0; i < customerSelect.options.length; i++) {
                        if (customerSelect.options[i].value === customerID) {
                            customerSelect.selectedIndex = i;
                            break;
                        }
                    }
                    
                    // Get current stock level from the product option's data attribute
                    const productSelect = document.getElementById('productID');
                    let hasEnoughStock = false;
                    let available_quantity = 0;
                    
                    for (let i = 0; i < productSelect.options.length; i++) {
                        if (productSelect.options[i].value === productID) {
                            available_quantity = parseInt(productSelect.options[i].getAttribute('data-available') || 0);
                            if (available_quantity >= parseInt(quantity)) {
                                hasEnoughStock = true;
                                productSelect.selectedIndex = i;
                            }
                            break;
                        }
                    }
                    
                    if (!hasEnoughStock) {
                        // Create a warning element and add it to the ticket card
                        const warningElement = document.createElement('div');
                        warningElement.className = 'stock-warning';
                        warningElement.style.color = 'red';
                        warningElement.style.fontWeight = 'bold';
                        warningElement.style.marginTop = '10px';
                        warningElement.innerHTML = `Warning: Only ${available_quantity} units in stock. Order requires ${quantity} units.`;
                        
                        this.appendChild(warningElement);
                        return; // Don't proceed with filling the form if there's not enough stock
                    }
                    
                    document.getElementById('quantity').value = quantity;
                    
                    // Format delivery date for datetime-local input
                    const formattedDate = new Date(deliveryDate + "T12:00:00");
                    let dateStr = formattedDate.toISOString().slice(0, 16);
                    document.getElementById('deliveryDate').value = dateStr;
                    
                    // Update total price
                    updateTotalPrice();
                });
            });
        }
    </script>
</body>
</html>