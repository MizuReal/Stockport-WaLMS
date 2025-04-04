<?php
require_once '../vendor/autoload.php';
include '../server/database.php';
require_once 'session_check_admin.php';
requireAdminAccess();

use Dompdf\Dompdf;
use Dompdf\Options;

if (isset($_POST['download_pdf']) && isset($_POST['report_type'])) {
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->setIsRemoteEnabled(true); // Enable remote images

    $dompdf = new Dompdf($options);
    
    // Start output buffering
    ob_start();
    
    // Include necessary queries
    include 'reports_queries.php';
    
    // Generate the HTML content
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <style>
            body { 
                font-family: DejaVu Sans, Arial, sans-serif;
                padding: 20px;
            }
            .table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 1rem;
                font-size: 12px;
            }
            .table th, .table td { 
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: left; 
            }
            .table th { 
                background-color: #f8f9fa;
                font-weight: bold;
            }
            .stats-container {
                display: flex;
                flex-wrap: wrap;
                margin: 20px 0;
                gap: 10px;
            }
            .metric-box {
                width: 45%;
                margin-bottom: 15px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background-color: #f8f9fa;
            }
            .metric-value { 
                font-size: 20px; 
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 5px;
            }
            .metric-label { 
                font-size: 14px; 
                color: #666;
            }
            h1 {
                color: #2c3e50;
                border-bottom: 2px solid #3498db;
                padding-bottom: 10px;
            }
            h2 {
                color: #2c3e50;
                font-size: 18px;
                margin-top: 30px;
                margin-bottom: 15px;
            }
            .date-generated {
                color: #666;
                font-size: 12px;
                margin-bottom: 20px;
            }
            .low-stock { background-color: #ffeeee; }
            .critical-stock { background-color: #ffdddd; }
            .profit-analysis {
                margin-top: 30px;
                margin-bottom: 30px;
            }
            .total-row {
                background-color: #e9ecef;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <h1>Analytics Report</h1>
        <div class="date-generated">Generated on: <?php echo date('Y-m-d H:i:s'); ?></div>

        <!-- Quick Stats -->
        <div class="stats-container">
            <div class="metric-box">
                <div class="metric-value"><?php echo ($totalMaterials + $productCount); ?></div>
                <div class="metric-label">Total Materials & Products</div>
            </div>
            <div class="metric-box">
                <div class="metric-value">&#8369;<?php echo number_format($totalValue, 2); ?></div>
                <div class="metric-label">Total Inventory Value</div>
            </div>
            <div class="metric-box">
                <div class="metric-value"><?php echo ($lowStock + $lowStockProducts); ?></div>
                <div class="metric-label">Low Stock Items</div>
            </div>
            <div class="metric-box">
                <div class="metric-value"><?php echo $activeOrders; ?></div>
                <div class="metric-label">Active Orders</div>
            </div>
        </div>

        <!-- Customer Profit Analysis -->
        <div class="profit-analysis">
            <h2>Customer Profit Analysis</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Net Profit</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $profitResult->data_seek(0);
                    $totalRevenue = 0;
                    $totalCost = 0;
                    $totalProfit = 0;
                    
                    while($row = $profitResult->fetch_assoc()) {
                        $margin = ($row['TotalRevenue'] > 0) 
                            ? ($row['TotalProfit'] / $row['TotalRevenue'] * 100) 
                            : 0;
                        $totalRevenue += $row['TotalRevenue'];
                        $totalCost += $row['TotalCost'];
                        $totalProfit += $row['TotalProfit'];
                        
                        echo "<tr>
                            <td>{$row['CustomerName']}</td>
                            <td>{$row['TotalOrders']}</td>
                            <td>₱" . number_format($row['TotalRevenue'], 2) . "</td>
                            <td>₱" . number_format($row['TotalCost'], 2) . "</td>
                            <td>₱" . number_format($row['TotalProfit'], 2) . "</td>
                            <td>" . number_format($margin, 1) . "%</td>
                        </tr>";
                    }
                    $overallMargin = ($totalRevenue > 0) ? ($totalProfit / $totalRevenue * 100) : 0;
                    ?>
                    <tr class="total-row">
                        <td><strong>Totals</strong></td>
                        <td></td>
                        <td><strong>₱<?php echo number_format($totalRevenue, 2); ?></strong></td>
                        <td><strong>₱<?php echo number_format($totalCost, 2); ?></strong></td>
                        <td><strong>₱<?php echo number_format($totalProfit, 2); ?></strong></td>
                        <td><strong><?php echo number_format($overallMargin, 1); ?>%</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Raw Materials Table -->
        <h2>Raw Materials Inventory</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Material</th>
                    <th>Warehouse</th>
                    <th>Quantity</th>
                    <th>Minimum Stock</th>
                    <th>Last Restocked</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rawMaterialsResult->data_seek(0);
                while($row = $rawMaterialsResult->fetch_assoc()) {
                    $stockRatio = $row['QuantityInStock'] / $row['MinimumStock'];
                    $rowClass = '';
                    if ($stockRatio <= 0.75) {
                        $rowClass = 'critical-stock';
                    } else if ($stockRatio <= 1) {
                        $rowClass = 'low-stock';
                    }
                    echo "<tr class='{$rowClass}'>
                        <td>{$row['MaterialName']}</td>
                        <td>{$row['raw_warehouse']}</td>
                        <td>{$row['QuantityInStock']}</td>
                        <td>{$row['MinimumStock']}</td>
                        <td>{$row['LastRestockedDate']}</td>
                        <td>&#8369;" . number_format($row['TotalValue'], 2) . "</td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Products Table -->
        <h2>Products Inventory</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Warehouse</th>
                    <th>Section</th>
                    <th>Quantity</th>
                    <th>Weight</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $inventoryResult->data_seek(0);
                while($row = $inventoryResult->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['ProductName']}</td>
                        <td>{$row['Category']}</td>
                        <td>{$row['productWarehouse']}</td>
                        <td>{$row['Section']}</td>
                        <td>{$row['Quantity']}</td>
                        <td>{$row['Weight']} {$row['weight_unit']}</td>
                        <td>&#8369;" . number_format($row['InventoryValue'], 2) . "</td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output the generated PDF with a custom filename
    $dompdf->stream("analytics_report_" . date('Y-m-d') . ".pdf", array("Attachment" => 1));
    exit();
}
?>
