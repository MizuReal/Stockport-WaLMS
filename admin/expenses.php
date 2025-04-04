<?php
$current_page = basename($_SERVER['PHP_SELF']);

include '../server/database.php';
require_once '../admin/session_check_admin.php';
requireAdminAccess();

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get total number of records
$total_records_query = "SELECT COUNT(*) as count FROM productionorders";
$total_records_result = $conn->query($total_records_query);
$total_records = $total_records_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $records_per_page);

// Modified query with pagination
$sql = "SELECT 
    po.OrderID,
    po.StartDate,
    p.ProductName,
    p.ProductionCost,
    po.QuantityProduced,
    (p.ProductionCost * po.QuantityProduced) as TotalCost
FROM productionorders po
JOIN products p ON po.ProductID = p.ProductID
ORDER BY po.StartDate DESC
LIMIT $records_per_page OFFSET $offset";

$result = $conn->query($sql);
$production_expenses = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total expenses (for all records)
$total_expenses_query = "SELECT SUM(p.ProductionCost * po.QuantityProduced) as total 
                        FROM productionorders po 
                        JOIN products p ON po.ProductID = p.ProductID";
$total_result = $conn->query($total_expenses_query);
$total_expenses = $total_result->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - Warehouse System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .expenses-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            margin: 20px 0;
        }
        .expenses-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .expenses-table thead {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }
        .expenses-table th {
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            padding: 15px;
            border-bottom: 2px solid #dee2e6;
        }
        .expenses-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .expenses-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .total-expenses {
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            padding: 15px 25px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            text-align: right;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .expense-amount {
            font-weight: 500;
            color: #e74c3c;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .pagination {
            margin-top: 20px;
            justify-content: center;
            gap: 5px;
        }
        .pagination .page-item .page-link {
            padding: 8px 16px;
            color: #6c757d;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        .pagination .page-item .page-link:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Expenses</h1>
            </header>
            <div class="content">
                <div class="expenses-card">
                    <div class="header-section">
                        <h2 class="mb-0">Expense Report</h2>
                        <div class="total-expenses">
                            Total Expenses: <span class="expense-amount">₱<?php echo number_format($total_expenses, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover expenses-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order ID</th>
                                    <th>Product</th>
                                    <th>Cost/Unit</th>
                                    <th>Quantity</th>
                                    <th>Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($production_expenses as $expense): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($expense['StartDate'])); ?></td>
                                    <td>#<?php echo $expense['OrderID']; ?></td>
                                    <td><?php echo $expense['ProductName']; ?></td>
                                    <td>₱<?php echo number_format($expense['ProductionCost'], 2); ?></td>
                                    <td><?php echo number_format($expense['QuantityProduced']); ?></td>
                                    <td class="expense-amount">₱<?php echo number_format($expense['TotalCost'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <!-- Add Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>