<?php
function renderHeader($pageTitle = '') {
?>
    <div class="page-header">
        <div class="page-header-title">
            <h1><?php echo $pageTitle; ?></h1>
        </div>
        <div class="page-header-actions">
            <a href="../employee/employee-logout.php" class="header-logout-button">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            border-radius: 8px;
            position: relative;
            z-index: 5;
        }

        .page-header-title h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
        }

        .header-logout-button {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .header-logout-button:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .header-logout-button i {
            margin-right: 8px;
        }

        .header-logout-button span {
            font-size: 0.9rem;
        }
    </style>
<?php
}
?>