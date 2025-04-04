<?php
// All the existing queries from reports_analytics_admin.php should be moved here
// This file will be included by both reports_analytics_admin.php and download_pdf.php

// Fetch raw materials analytics
$rawMaterialsQuery = "SELECT 
    MaterialName,
    QuantityInStock,
    MinimumStock,
    UnitCost,
    (QuantityInStock * UnitCost) as TotalValue,
    LastRestockedDate,
    raw_warehouse
    FROM rawmaterials";
$rawMaterialsResult = $conn->query($rawMaterialsQuery);

// Fetch product inventory analytics
$inventoryQuery = "SELECT 
    p.ProductID,
    p.ProductName,
    p.Category,
    p.minimum_quantity as Quantity,
    p.Weight,
    p.weight_unit,
    pw.productWarehouse,
    pw.Section,
    (p.minimum_quantity * p.ProductionCost) as InventoryValue
    FROM products p
    JOIN products_warehouse pw ON p.LocationID = pw.productLocationID
    ORDER BY p.Category, p.ProductName";
$inventoryResult = $conn->query($inventoryQuery);

// Get total product inventory value
$productValueQuery = "SELECT SUM(minimum_quantity * ProductionCost) as TotalValue FROM products";
$productValueResult = $conn->query($productValueQuery);
$productValueData = $productValueResult->fetch_assoc();
$productInventoryValue = $productValueData['TotalValue'] ?: 0;

// Remove the product low stock query as we're only tracking raw materials
$lowStockProductsQuery = "SELECT COUNT(*) as lowStockCount 
    FROM rawmaterials 
    WHERE QuantityInStock <= MinimumStock";
$lowStockProductsResult = $conn->query($lowStockProductsQuery);
$lowStockProductsData = $lowStockProductsResult->fetch_assoc();
$lowStockProducts = $lowStockProductsData['lowStockCount'];

// Calculate active orders
$activeOrdersQuery = "SELECT COUNT(*) as activeOrders FROM productionorders WHERE Status = 'In Progress' OR Status = 'Planned'";
$activeOrdersResult = $conn->query($activeOrdersQuery);
$activeOrdersData = $activeOrdersResult->fetch_assoc();
$activeOrders = $activeOrdersData['activeOrders'];

// Add profit analysis query
$profitQuery = "SELECT 
    c.CustomerName,
    COUNT(co.CustomerOrderID) as TotalOrders,
    SUM(od.Quantity * od.UnitPrice) as TotalRevenue,
    SUM(od.Quantity * p.ProductionCost) as TotalCost,
    SUM(od.Quantity * (od.UnitPrice - p.ProductionCost)) as TotalProfit
    FROM customers c
    JOIN customerorders co ON c.CustomerID = co.CustomerID
    JOIN orderdetails od ON co.CustomerOrderID = od.CustomerOrderID
    JOIN products p ON od.ProductID = p.ProductID
    WHERE co.Status != 'Cancelled'
    GROUP BY c.CustomerID, c.CustomerName
    ORDER BY TotalProfit DESC";
$profitResult = $conn->query($profitQuery);

// Calculate the summary values
$totalMaterials = $rawMaterialsResult->num_rows;
$totalValue = 0;
$lowStock = 0;

$rawMaterialsResult->data_seek(0);
while($row = $rawMaterialsResult->fetch_assoc()) {
    $totalValue += $row['TotalValue'];
    if($row['QuantityInStock'] <= $row['MinimumStock']) {
        $lowStock++;
    }
}

// Remove redundant low stock counting since we now have it from the direct query
$lowStockProducts = $lowStock; // Use the same value for consistency

// Add product inventory value to total
$totalValue += $productInventoryValue;

// Get product count
$inventoryResult->data_seek(0);
$productCount = $inventoryResult->num_rows;

// Get additional data for charts (if needed for PDF)
$categoryQuery = "SELECT Category, COUNT(*) as ProductCount FROM products GROUP BY Category ORDER BY ProductCount DESC";
$categoryResult = $conn->query($categoryQuery);

$warehouseQuery = "SELECT 
    pw.productWarehouse,
    pw.Capacity,
    pw.current_usage,
    pw.warehouse_weight_unit,
    (pw.current_usage / pw.Capacity * 100) as usage_percentage
    FROM products_warehouse pw
    ORDER BY pw.productWarehouse";
$warehouseResult = $conn->query($warehouseQuery);
?>
