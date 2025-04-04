<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('session_customer.php');
require_once('../server/database.php');

// Verify customer is logged in
checkCustomerLogin();

// Get customer information from session
$customerInfo = getCurrentCustomerInfo();
if (!$customerInfo || $customerInfo['customer_status'] !== 'approved') {
    header("Location: ../customer-login.php");
    exit();
}

// Check database connection
if (!isset($conn) || $conn === null) {
    die("Database connection not available.");
}

// Fetch customer's orders
$customer_id = $customerInfo['CustomerID'];
$sql = "SELECT 
            co.CustomerOrderID,
            co.OrderDate,
            co.TotalAmount,
            co.Status as OrderStatus,
            od.Quantity,
            od.UnitPrice,
            p.ProductName,
            p.product_img
        FROM customerorders co
        JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
        JOIN products p ON od.ProductID = p.ProductID
        WHERE co.CustomerID = ?
        ORDER BY co.OrderDate DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch customer's pending tickets
$companyName = $customerInfo['customer_name'];
$ticketSql = "SELECT 
                ct.TicketID,
                ct.CompanyName,
                ct.Quantity,
                ct.CreatedAt,
                ct.ExpectedDeliveryDate,
                p.ProductName,
                p.product_img,
                p.SellingPrice
            FROM customerTicket ct
            JOIN products p ON ct.ProductID = p.ProductID
            WHERE ct.CompanyName = ?
            ORDER BY ct.CreatedAt DESC";  

$ticketStmt = $conn->prepare($ticketSql);
$ticketStmt->bind_param("s", $companyName);
$ticketStmt->execute();
$ticketResult = $ticketStmt->get_result();

// Get order message from session if it exists
$orderMessage = '';
if (isset($_SESSION['order_message'])) {
    $orderMessage = $_SESSION['order_message'];
    unset($_SESSION['order_message']); // Clear the message after displaying it
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .nav-tabs .nav-link {
            color: #495057;
            background-color: #f8f9fa;
            border-color: #dee2e6 #dee2e6 #fff;
            margin-right: 5px;
        }
        
        .nav-tabs .nav-link.active {
            color: #3498db;
            font-weight: 600;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            border-bottom: 3px solid #3498db;
        }
        
        .badge {
            font-size: 0.85em;
            padding: 0.35em 0.65em;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">StockPort</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-light">Welcome, <?php echo htmlspecialchars($customerInfo['customer_name']); ?></span>
                <a class="nav-link" href="customer_logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($orderMessage)) : ?>
            <?php echo $orderMessage; ?>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h2>My Dashboard</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="place_order.php" class="btn btn-primary">Place New Order</a>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#tickets" type="button" role="tab" aria-controls="tickets" aria-selected="true">
                    Pending Requests 
                    <?php if ($ticketResult && $ticketResult->num_rows > 0) : ?>
                    <span class="badge bg-primary rounded-pill"><?php echo $ticketResult->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab" aria-controls="orders" aria-selected="false">
                    Orders
                    <?php if ($result && $result->num_rows > 0) : ?>
                    <span class="badge bg-primary rounded-pill"><?php echo $result->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Tickets Tab -->
            <div class="tab-pane fade show active" id="tickets" role="tabpanel" aria-labelledby="tickets-tab">
                <h4 class="mb-3">Pending Request Tickets</h4>
                
                <?php if ($ticketResult && $ticketResult->num_rows > 0) : ?>
                    <div class="row">
                        <?php while ($ticket = $ticketResult->fetch_assoc()) : ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Ticket #<?php echo $ticket['TicketID']; ?></h5>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <p class="card-text">
                                                    <strong>Product:</strong> <?php echo htmlspecialchars($ticket['ProductName']); ?><br>
                                                    <strong>Quantity:</strong> <?php echo htmlspecialchars($ticket['Quantity']); ?><br>
                                                    <strong>Price per Unit:</strong> ₱<?php echo number_format($ticket['SellingPrice'], 2); ?><br>
                                                    <strong>Estimated Total:</strong> ₱<?php echo number_format($ticket['SellingPrice'] * $ticket['Quantity'], 2); ?><br>
                                                    <strong>Requested Delivery:</strong> <?php echo date('M d, Y', strtotime($ticket['ExpectedDeliveryDate'])); ?><br>
                                                    <strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($ticket['CreatedAt'])); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <img src="../assets/imgs/<?php echo $ticket['product_img']; ?>" alt="<?php echo htmlspecialchars($ticket['ProductName']); ?>" class="img-fluid" style="max-height: 100px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info">You don't have any pending request tickets.</div>
                <?php endif; ?>
            </div>
            
            <!-- Orders Tab -->
            <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
                <h4 class="mb-3">My Orders</h4>
                
                <?php if ($result && $result->num_rows > 0) : ?>
                    <div class="row">
                        <?php while ($order = $result->fetch_assoc()) : ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Order #<?php echo $order['CustomerOrderID']; ?></h5>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <p class="card-text">
                                                    <strong>Product:</strong> <?php echo htmlspecialchars($order['ProductName']); ?><br>
                                                    <strong>Quantity:</strong> <?php echo htmlspecialchars($order['Quantity']); ?><br>
                                                    <strong>Price per Unit:</strong> ₱<?php echo number_format($order['UnitPrice'], 2); ?><br>
                                                    <strong>Total Amount:</strong> ₱<?php echo number_format($order['TotalAmount'], 2); ?><br>
                                                    <strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($order['OrderDate'])); ?><br>
                                                    <strong>Status:</strong> 
                                                    <span class="badge bg-<?php echo getStatusColor($order['OrderStatus']); ?>">
                                                        <?php echo htmlspecialchars($order['OrderStatus']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <img src="../assets/imgs/<?php echo $order['product_img']; ?>" alt="<?php echo htmlspecialchars($order['ProductName']); ?>" class="img-fluid" style="max-height: 100px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info">You haven't placed any orders yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'warning';
        case 'processing':
            return 'info';
        case 'shipped':
            return 'primary';
        case 'delivered':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>