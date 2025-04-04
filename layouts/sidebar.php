<?php
// Get the current file name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Include the separate sidebar CSS file -->
<link rel="stylesheet" href="../assets/css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<div class="warehouse-sidebar">
    <h2 class="warehouse-sidebar-title">Warehouse</h2>
    <ul class="warehouse-sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>

        <li>
            <a href="customers.php" class="<?= ($current_page == 'customers.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Customer
            </a>
        </li>

        <li>
            <a href="suppliers.php" class="<?= ($current_page == 'suppliers.php') ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Supplier
            </a>
        </li>

        <!-- Dropdown -->
        <li class="warehouse-dropdown <?= (strpos($current_page, 'inventory') !== false) ? 'active' : ''; ?>">
            <a class="dropdown-toggle <?= (strpos($current_page, 'inventory') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i> Logistics Operations
            </a>
            <ul class="warehouse-dropdown-menu">
                <li>
                    <a href="incoming_materials.php" class="<?= ($current_page == 'incoming_materials.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cubes"></i> Incoming Materials
                    </a>
                </li>
                <li>
                    <a href="outgoing_shipments.php" class="<?= ($current_page == 'outgoing_shipments.php') ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i> Outgoing Shipments
                    </a>
                </li>
                <li>
                    <a href="admin_manage_storage.php" class="<?= ($current_page == 'admin_manage_storage.php') ? 'active' : ''; ?>">
                        <i class="fas fa-exchange-alt"></i> Storage Transfers
                    </a>
                </li>
            </ul>
        </li>
        <li class="warehouse-dropdown <?= (strpos($current_page, 'products') !== false) ? 'active' : ''; ?>">
            <a class="dropdown-toggle <?= (strpos($current_page, 'products') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Products & Materials
            </a>
            <ul class="warehouse-dropdown-menu">
                <li>
                    <a href="products.php" class="<?= ($current_page == 'products.php') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All Products
                    </a>
                </li>
                <li>
                    <a href="raw_materials.php" class="<?= ($current_page == 'raw_materials.php') ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle"></i> Raw Materials
                    </a>
                </li>
                <li>
                    <a href="product_warehouse.php" class="<?= ($current_page == 'product_warehouse.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i> Product Warehouse
                    </a>
                </li>
            </ul>
        </li>
        <li>
            <a href="expenses.php" class="<?= ($current_page == 'expenses.php') ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign"></i> Expenses
            </a>
        </li>

        <!-- Staff Dropdown -->
        <li class="warehouse-dropdown <?= (strpos($current_page, 'staff') !== false) ? 'active' : ''; ?>">
            <a class="dropdown-toggle <?= (strpos($current_page, 'staff') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Staff
            </a>
            <ul class="warehouse-dropdown-menu">
                <li>
                    <a href="employee-status.php" class="<?= ($current_page == 'employee-status.php') ? 'active' : ''; ?>">
                        <i class="fas fa-id-badge"></i> Status
                    </a>
                </li>
            </ul>
        </li>

        <li>
            <a href="reports_analytics_admin.php" class="<?= ($current_page == 'reports_analytics_admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports/Analytics
            </a>
        </li>
        
        <li>
            <a href="support_requests.php" class="<?= ($current_page == 'support_requests.php') ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Support Requests
            </a>
        </li>

        <li>
            <a href="admin_logout.php" class="<?= ($current_page == 'admin_logout.php') ? 'active' : ''; ?>">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<script>
// Add this script to handle the dropdown toggle
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default behavior
            this.parentElement.classList.toggle('active');
        });
    });
});
</script>