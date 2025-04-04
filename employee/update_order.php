<?php
session_start();
require_once 'session_check.php';
requireActiveLogin();

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    if (!isset($_POST['orderId']) || !isset($_POST['status']) || !isset($_POST['quantityProduced'])) {
        $_SESSION['error_message'] = "Missing required fields";
        header("Location: materialOrderHistory.php");
        exit;
    }

    $orderId = filter_var($_POST['orderId'], FILTER_VALIDATE_INT);
    $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
    $quantityProduced = filter_var($_POST['quantityProduced'], FILTER_VALIDATE_INT);

    // Validate order ID
    if ($orderId === false) {
        $_SESSION['error_message'] = "Invalid order ID";
        header("Location: materialOrderHistory.php");
        exit;
    }

    // Validate status
    $validStatuses = ['In Progress', 'Completed', 'Cancelled'];
    if (!in_array($status, $validStatuses)) {
        $_SESSION['error_message'] = "Invalid status value";
        header("Location: materialOrderHistory.php");
        exit;
    }

    // Validate quantity produced
    if ($quantityProduced === false || $quantityProduced < 0) {
        $_SESSION['error_message'] = "Invalid quantity value";
        header("Location: materialOrderHistory.php");
        exit;
    }

    // Connect to database
    require_once '../server/database.php';

    // First, verify the order exists and check maximum quantity
    $checkQuery = "
        SELECT 
            po.QuantityOrdered,
            p.MaterialID,
            p.minimum_quantity,
            r.QuantityInStock
        FROM 
            productionorders po
        JOIN 
            products p ON po.ProductID = p.ProductID
        JOIN 
            rawmaterials r ON p.MaterialID = r.MaterialID
        WHERE 
            po.OrderID = ?
    ";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $orderId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Order not found";
        header("Location: materialOrderHistory.php");
        exit;
    }

    $orderData = $result->fetch_assoc();
    $maxQuantity = $orderData['QuantityOrdered'];
    $materialId = $orderData['MaterialID'];
    $minimumQuantity = $orderData['minimum_quantity'];
    $currentStock = $orderData['QuantityInStock'];

    // Validate that quantity produced doesn't exceed ordered quantity
    if ($quantityProduced > $maxQuantity) {
        $_SESSION['error_message'] = "Quantity produced cannot exceed quantity ordered";
        header("Location: materialOrderHistory.php");
        exit;
    }

    // If status is completed, make sure quantity produced equals quantity ordered
    if ($status === 'Completed' && $quantityProduced != $maxQuantity) {
        $_SESSION['error_message'] = "To mark as completed, quantity produced must equal quantity ordered";
        header("Location: materialOrderHistory.php");
        exit;
    }

    // Update the order
    $updateQuery = "UPDATE productionorders SET Status = ?, QuantityProduced = ? WHERE OrderID = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("sii", $status, $quantityProduced, $orderId);

    if ($updateStmt->execute()) {
        $_SESSION['success_message'] = "Order updated successfully";

        // Handle raw material stock adjustments based on status
        if ($status === 'Cancelled') {
            // Return materials to stock
            $rawMaterialRequired = ceil($maxQuantity / $minimumQuantity); // Calculate raw material required
            $newStock = $currentStock + $rawMaterialRequired;

            $updateMaterialQuery = "
                UPDATE rawmaterials 
                SET QuantityInStock = ? 
                WHERE MaterialID = ?
            ";
            $updateMaterialStmt = $conn->prepare($updateMaterialQuery);
            $updateMaterialStmt->bind_param("ii", $newStock, $materialId);
            $updateMaterialStmt->execute();
        }
    } else {
        $_SESSION['error_message'] = "Error updating order: " . $conn->error;
    }

    $conn->close();
    header("Location: materialOrderHistory.php");
    exit;
} else {
    // If not a POST request, redirect back to the order history page
    header("Location: materialOrderHistory.php");
    exit;
}
?>