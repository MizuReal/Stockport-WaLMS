<?php
include '../server/database.php';
require_once 'session_check_admin.php';
requireAdminAccess();

if (isset($_GET['transfer_id'])) {
    $transfer_id = $_GET['transfer_id'];
    
    $stmt = $conn->prepare("SELECT 
        st.*,
        p.ProductName as product_name,
        CONCAT(fw.productWarehouse, ' - ', fw.Section) as from_warehouse,
        CONCAT(tw.productWarehouse, ' - ', tw.Section) as to_warehouse
        FROM storage_transfers st
        JOIN products p ON st.product_id = p.ProductID 
        JOIN products_warehouse fw ON st.from_warehouse = fw.productLocationID
        JOIN products_warehouse tw ON st.to_warehouse = tw.productLocationID
        WHERE st.transfer_id = ?");
        
    $stmt->bind_param("i", $transfer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transfer = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode($transfer);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Transfer ID not provided']);
}
