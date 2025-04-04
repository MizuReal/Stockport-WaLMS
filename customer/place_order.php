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

// Get products for dropdown selection
$sql = "SELECT ProductID, ProductName, product_img, SellingPrice FROM products";
$result = $conn->query($sql);
$products = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productID = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $expectedDeliveryDate = $_POST['expected_delivery_date'];
    $companyName = $_SESSION['customer_name'];
    
    // Validate inputs
    if (empty($productID) || empty($quantity) || empty($expectedDeliveryDate)) {
        $_SESSION['order_message'] = '<div class="alert alert-danger">All fields are required</div>';
    } elseif ($quantity <= 0) {
        $_SESSION['order_message'] = '<div class="alert alert-danger">Quantity must be greater than zero</div>';
    } else {
        // Insert into customerTicket table
        $sql = "INSERT INTO customerTicket (CompanyName, ProductID, Quantity, ExpectedDeliveryDate) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siis", $companyName, $productID, $quantity, $expectedDeliveryDate);
        
        if ($stmt->execute()) {
            $_SESSION['order_message'] = '<div class="alert alert-success">Request ticket created successfully!</div>';
        } else {
            $_SESSION['order_message'] = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        
        $stmt->close();
    }
    
    // Redirect to home page
    header("Location: home.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Request Ticket</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f8fa;
            color: var(--dark-color);
            padding-top: 2rem;
        }
        
        .container {
            max-width: 1000px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-bottom: none;
            padding: 1.2rem 1.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 0.6rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 6px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .product-card {
            border: 2px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .product-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .product-image {
            height: 150px;
            width: 100%;
            object-fit: contain;
            background-color: #fff;
        }
        
        .product-info {
            padding: 1rem;
        }
        
        .product-name {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .product-price {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .alert {
            border-radius: 6px;
            border: none;
        }
        
        .top-bar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
            margin-bottom: 2rem;
        }
        
        .company-name {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Stockport Metal Manufacturing</h4>
                <div class="company-name">
                    Welcome, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>
                    <a href="logout.php" class="btn btn-sm btn-outline-secondary ms-3">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Create Request Ticket</h3>
            <a href="home.php" class="btn btn-outline-primary">Return to Dashboard</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Request Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <h5 class="mb-3">Select Product</h5>
                        <div class="row row-cols-1 row-cols-md-3 g-4">
                            <?php foreach ($products as $product): ?>
                            <div class="col">
                                <div class="product-card" data-product-id="<?php echo $product['ProductID']; ?>">
                                    <img src="../assets/imgs/<?php echo $product['product_img']; ?>" alt="<?php echo htmlspecialchars($product['ProductName']); ?>" class="product-image">
                                    <div class="product-info">
                                        <h6 class="product-name"><?php echo htmlspecialchars($product['ProductName']); ?></h6>
                                        <p class="product-price">₱<?php echo number_format($product['SellingPrice'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="product_id" id="product_id" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="expected_delivery_date">Expected Delivery Date</label>
                            <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date" required>
                            <small class="text-muted">Please allow at least 3 business days for processing</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div id="selected-product-info" class="alert alert-info d-none">
                            <h6>Selected Product: <span id="selected-product-name"></span></h6>
                            <p class="mb-0">Price: <span id="selected-product-price"></span></p>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="home.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set minimum date for expected delivery date (today + 3 days)
        const expectedDeliveryDateInput = document.getElementById('expected_delivery_date');
        const today = new Date();
        const minDate = new Date(today);
        minDate.setDate(today.getDate() + 3);
        
        const formatDate = date => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        
        expectedDeliveryDateInput.min = formatDate(minDate);
        
        // Product selection functionality
        const productCards = document.querySelectorAll('.product-card');
        const productIdInput = document.getElementById('product_id');
        const selectedProductInfo = document.getElementById('selected-product-info');
        const selectedProductName = document.getElementById('selected-product-name');
        const selectedProductPrice = document.getElementById('selected-product-price');
        
        // Product data from PHP
        const products = <?php echo json_encode($products); ?>;
        
        productCards.forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                productCards.forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Get product ID and update hidden input
                const productId = this.getAttribute('data-product-id');
                productIdInput.value = productId;
                
                // Find product details
                const product = products.find(p => p.ProductID == productId);
                
                // Update selected product info box
                if (product) {
                    selectedProductName.textContent = product.ProductName;
                    selectedProductPrice.textContent = '₱' + parseFloat(product.SellingPrice).toFixed(2);
                    selectedProductInfo.classList.remove('d-none');
                }
            });
        });
    </script>
</body>
</html>