-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2025 at 01:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stockport`
--

-- --------------------------------------------------------

--
-- Table structure for table `billofmaterials`
--

CREATE TABLE `billofmaterials` (
  `BOMID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `MaterialID` int(11) NOT NULL,
  `QuantityRequired` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `carriers`
--

CREATE TABLE `carriers` (
  `CarrierID` int(11) NOT NULL,
  `CarrierName` varchar(100) NOT NULL,
  `ContactPerson` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customerorders`
--

CREATE TABLE `customerorders` (
  `CustomerOrderID` int(11) NOT NULL,
  `CustomerID` int(11) NOT NULL,
  `OrderDate` datetime NOT NULL DEFAULT current_timestamp(),
  `TotalAmount` decimal(10,2) DEFAULT NULL,
  `Status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `customerorders`
--

INSERT INTO `customerorders` (`CustomerOrderID`, `CustomerID`, `OrderDate`, `TotalAmount`, `Status`) VALUES
(1, 1, '2025-03-11 02:30:20', 3000.00, 'Delivered'),
(2, 2, '2025-03-11 02:32:12', 21000.00, 'Delivered'),
(3, 2, '2025-03-11 03:10:57', 48000.00, 'Delivered'),
(4, 1, '2025-03-11 03:11:38', 30000.00, 'Delivered'),
(5, 2, '2025-03-11 03:25:29', 15000.00, 'Delivered'),
(6, 2, '2025-03-11 03:29:40', 30000.00, 'Delivered'),
(7, 2, '2025-03-11 03:32:56', 60000.00, 'Delivered'),
(8, 1, '2025-03-11 03:33:09', 60000.00, 'Delivered'),
(9, 1, '2025-03-11 03:43:13', 12500.00, 'Delivered'),
(10, 2, '2025-03-11 03:43:29', 10000.00, 'Delivered'),
(11, 2, '2025-03-11 03:44:59', 6175.00, 'Delivered'),
(12, 2, '2025-03-11 03:45:12', 75.00, 'Delivered'),
(13, 1, '2025-03-11 17:17:10', 1500.00, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `CustomerID` int(11) NOT NULL,
  `CustomerName` varchar(100) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `customer_status` varchar(32) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `Password` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`CustomerID`, `CustomerName`, `Phone`, `Email`, `Address`, `customer_status`, `created_at`, `Password`) VALUES
(1, 'Can Company', '09123456789', 'cancompany@gmail.com', 'Block 1 Lot 2 Taguig City', 'approved', '2025-03-11 01:28:25', '$2y$10$B8sZG7YYIF3RR8o/WQ5ATOtICMmztC4TlJQ4SsoXfng/ROp3J4DbW'),
(2, 'Storage Company', '09123456789', 'storagecompany@gmail.com', 'Block 1 Lot 2 Taguig City', 'approved', '2025-03-11 01:29:10', '$2y$10$9tK87u4000Io1yTCWSDGneeNYdXE7sAv8BFfIQDvuZNqRp.aA5ERy');

-- --------------------------------------------------------

--
-- Table structure for table `customerticket`
--

CREATE TABLE `customerticket` (
  `TicketID` int(11) NOT NULL,
  `CompanyName` varchar(255) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL CHECK (`Quantity` > 0),
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ExpectedDeliveryDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customerticket`
--

INSERT INTO `customerticket` (`TicketID`, `CompanyName`, `ProductID`, `Quantity`, `CreatedAt`, `ExpectedDeliveryDate`) VALUES
(1, 'Can Company', 8, 100, '2025-03-11 15:35:54', '2025-03-31'),
(3, 'Can Company', 10, 100, '2025-03-12 00:29:47', '2025-03-31');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_tracking`
--

CREATE TABLE `delivery_tracking` (
  `delivery_id` int(11) NOT NULL,
  `order_detail_id` int(11) NOT NULL,
  `delivery_date` datetime NOT NULL DEFAULT current_timestamp(),
  `delivery_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_tracking`
--

INSERT INTO `delivery_tracking` (`delivery_id`, `order_detail_id`, `delivery_date`, `delivery_note`) VALUES
(1, 1, '2025-03-11 09:30:50', 'Test'),
(16, 2, '2025-03-11 10:08:26', 'q'),
(17, 4, '2025-03-11 10:12:38', 'Test'),
(18, 3, '2025-03-11 10:14:09', ''),
(19, 5, '2025-03-11 10:26:52', 'test'),
(20, 6, '2025-03-11 10:30:06', 'test'),
(21, 8, '2025-03-11 10:34:27', 'done'),
(22, 7, '2025-03-11 10:36:52', 'test'),
(23, 10, '2025-03-11 10:44:05', 'test'),
(24, 9, '2025-03-11 10:44:10', 'test'),
(25, 12, '2025-03-11 10:45:38', 'ty'),
(26, 11, '2025-03-11 10:45:44', 'ty');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `EmployeeID` int(11) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `Role` varchar(45) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `employeeEmail` varchar(100) DEFAULT NULL,
  `employeePassword` varchar(255) NOT NULL,
  `HireDate` date NOT NULL,
  `Status` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`EmployeeID`, `FirstName`, `LastName`, `Role`, `Phone`, `employeeEmail`, `employeePassword`, `HireDate`, `Status`) VALUES
(1, 'Kim Jensen', 'Yebes', 'Admin', '09123456789', 'kimjensenyebes@gmail.com', '$2y$10$zgByliOTroett3FUGuvTbeK1mv0ERICQ.67kgsFql.xOXc6af8Cmm', '2025-02-17', 'Active'),
(2, 'Christian Earl', 'Tapit', 'Employee', '09123456789', 'christianearltapit@gmail.com', '$2y$10$lwK9pau8gk4j5L2uOOwpWe3yUM.poSe8KhuTw1My7b5QU0rdgvNyO', '2025-02-25', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `LocationID` int(11) NOT NULL,
  `LocationName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`LocationID`, `LocationName`) VALUES
(1, 'Warehouse A'),
(2, 'Warehouse B'),
(3, 'Storage Room 1'),
(4, 'Distribution Center');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `messageID` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','resolved') DEFAULT 'unread',
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`messageID`, `employee_id`, `subject`, `message`, `status`, `timestamp`) VALUES
(1, 1, 'pogi', 'test', 'read', '2025-03-09 11:05:17');

-- --------------------------------------------------------

--
-- Table structure for table `orderdetails`
--

CREATE TABLE `orderdetails` (
  `OrderDetailID` int(11) NOT NULL,
  `CustomerOrderID` int(11) DEFAULT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `QuantityProduced` int(11) DEFAULT 0,
  `ReadyToShip` tinyint(1) DEFAULT 0,
  `UnitPrice` decimal(10,2) DEFAULT NULL,
  `EmployeeID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orderdetails`
--

INSERT INTO `orderdetails` (`OrderDetailID`, `CustomerOrderID`, `ProductID`, `Quantity`, `QuantityProduced`, `ReadyToShip`, `UnitPrice`, `EmployeeID`) VALUES
(1, 1, 1, 200, 0, 0, 15.00, 1),
(2, 2, 1, 1400, 0, 0, 15.00, 1),
(3, 3, 1, 3200, 0, 0, 15.00, 1),
(4, 4, 1, 2000, 0, 0, 15.00, 1),
(5, 5, 1, 1000, 0, 0, 15.00, 1),
(6, 6, 11, 6, 0, 0, 10000.00, 1),
(7, 7, 11, 6, 0, 0, 10000.00, 1),
(8, 8, 11, 6, 0, 0, 10000.00, 1),
(9, 9, 2, 500, 0, 0, 25.00, 1),
(10, 10, 2, 400, 0, 0, 25.00, 1),
(11, 11, 2, 247, 0, 0, 25.00, 1),
(12, 12, 2, 3, 0, 0, 25.00, 1),
(13, 13, 1, 100, 0, 0, 15.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `productionorders`
--

CREATE TABLE `productionorders` (
  `OrderID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `StartDate` datetime NOT NULL,
  `EndDate` datetime DEFAULT NULL,
  `Status` enum('Planned','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
  `QuantityOrdered` int(11) NOT NULL,
  `QuantityProduced` int(11) NOT NULL DEFAULT 0,
  `Delivery_Status` tinyint(1) NOT NULL,
  `warehouseID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `productionorders`
--

INSERT INTO `productionorders` (`OrderID`, `ProductID`, `EmployeeID`, `StartDate`, `EndDate`, `Status`, `QuantityOrdered`, `QuantityProduced`, `Delivery_Status`, `warehouseID`) VALUES
(1, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 200, 200, 0, 2),
(2, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 1400, 1400, 0, 2),
(3, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 1200, 1200, 0, 2),
(4, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 2000, 2000, 0, 2),
(5, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 2000, 2000, 0, 2),
(6, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 6000, 6000, 0, 2),
(7, 1, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 4000, 4000, 0, 2),
(8, 11, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 6, 6, 0, 2),
(9, 11, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 120, 120, 0, 4),
(10, 11, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 6, 6, 0, 2),
(11, 2, 1, '2025-03-11 00:00:00', '2025-03-12 00:00:00', 'Completed', 1150, 1150, 0, 3);

-- --------------------------------------------------------

--
-- Table structure for table `production_delivery_tracking`
--

CREATE TABLE `production_delivery_tracking` (
  `tracking_id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `quantity_delivered` int(11) NOT NULL,
  `order_detail_id` int(11) NOT NULL,
  `delivery_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_delivery_tracking`
--

INSERT INTO `production_delivery_tracking` (`tracking_id`, `production_order_id`, `quantity_delivered`, `order_detail_id`, `delivery_date`) VALUES
(1, 1, 200, 1, '2025-03-11 01:30:20'),
(2, 2, 1400, 2, '2025-03-11 01:32:12'),
(3, 3, 1200, 3, '2025-03-11 02:10:57'),
(4, 4, 2000, 3, '2025-03-11 02:10:57'),
(5, 5, 2000, 4, '2025-03-11 02:11:38'),
(6, 6, 1000, 5, '2025-03-11 02:25:29'),
(7, 8, 3, 6, '2025-03-11 02:29:40'),
(8, 8, 3, 7, '2025-03-11 02:32:56'),
(9, 9, 3, 7, '2025-03-11 02:32:56'),
(10, 9, 6, 8, '2025-03-11 02:33:09'),
(11, 11, 500, 9, '2025-03-11 02:43:13'),
(12, 11, 400, 10, '2025-03-11 02:43:29'),
(13, 11, 247, 11, '2025-03-11 02:44:59'),
(14, 11, 3, 12, '2025-03-11 02:45:12'),
(15, 6, 100, 13, '2025-03-11 16:17:10');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `ProductID` int(11) NOT NULL,
  `ProductName` varchar(100) NOT NULL,
  `Category` varchar(45) DEFAULT NULL,
  `Weight` decimal(10,2) DEFAULT NULL,
  `ProductionCost` decimal(10,2) DEFAULT NULL,
  `SellingPrice` decimal(10,2) DEFAULT NULL,
  `LocationID` int(11) DEFAULT NULL,
  `MaterialID` int(11) DEFAULT NULL,
  `minimum_quantity` int(11) NOT NULL,
  `product_img` varchar(255) DEFAULT NULL,
  `weight_unit` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`ProductID`, `ProductName`, `Category`, `Weight`, `ProductionCost`, `SellingPrice`, `LocationID`, `MaterialID`, `minimum_quantity`, `product_img`, `weight_unit`) VALUES
(1, 'Food Can', 'Can', 10.00, 10.00, 15.00, 1, 1, 200, 'century_tuna_can.jpg', 'g'),
(2, 'Biscuit Tin', 'Tin', 10.00, 20.00, 25.00, 1, 1, 115, 'biscuit_tin.jpg', 'g'),
(3, 'Paint can', 'Can', 10.00, 20.00, 25.00, 1, 1, 82, 'paint_can.jpg', 'g'),
(4, 'Baking Mold', 'Mold', 30.00, 35.00, 40.00, 1, 1, 96, 'baking_mold.jpg', 'g'),
(5, 'Oil Drum', 'Drum', 30.00, 2500.00, 3000.00, 2, 2, 3, 'oil_drum.jpg', 'kg'),
(6, 'Fuel Tank', 'Tank', 50.00, 1000.00, 1500.00, 2, 2, 2, 'fuel_tank.jpg', 'kg'),
(7, 'Coin Bank/Safe', 'Safe', 20.00, 700.00, 1000.00, 2, 2, 11, 'coin_bank.jpg', 'kg'),
(8, 'Beverage can', 'Can', 10.00, 50.00, 100.00, 2, 3, 823, 'beverage_can.jpg', 'g'),
(9, 'Food Tray', 'Tray', 50.00, 100.00, 200.00, 2, 3, 640, 'food_tray.jpg', 'g'),
(10, 'Aerosol Can', 'Can', 20.00, 100.00, 200.00, 2, 3, 576, 'aerosol_can.jpg', 'g'),
(11, 'Storage Bin', 'Bin', 200.00, 5000.00, 10000.00, 1, 4, 6, 'storage_bin.jpg', 'kg');

-- --------------------------------------------------------

--
-- Table structure for table `products_warehouse`
--

CREATE TABLE `products_warehouse` (
  `productLocationID` int(11) NOT NULL,
  `productWarehouse` varchar(45) NOT NULL,
  `Section` varchar(45) NOT NULL,
  `Capacity` int(11) NOT NULL,
  `warehouse_weight_unit` varchar(32) NOT NULL,
  `current_usage` decimal(10,2) DEFAULT 0.00,
  `remaining_capacity` decimal(10,2) GENERATED ALWAYS AS (`Capacity` - `current_usage`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `products_warehouse`
--

INSERT INTO `products_warehouse` (`productLocationID`, `productWarehouse`, `Section`, `Capacity`, `warehouse_weight_unit`, `current_usage`) VALUES
(1, 'San Pedro City', 'Metal Storage', 20000, 'kg', 14000.00),
(2, 'Taguig City', 'Metal Storage', 20000, 'kg', 10020.00),
(3, 'Laguna City', 'Metal Storage', 50000, 'kg', 0.00),
(4, 'Quezon City', 'Metal Storage', 50000, 'kg', 0.00),
(5, 'Pasay City', 'Material Storage', 50000, 'kg', 0.00);

--
-- Triggers `products_warehouse`
--
DELIMITER $$
CREATE TRIGGER `update_remaining_capacity` BEFORE UPDATE ON `products_warehouse` FOR EACH ROW BEGIN
    SET NEW.remaining_capacity = NEW.Capacity - NEW.current_usage;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `rawmaterials`
--

CREATE TABLE `rawmaterials` (
  `MaterialID` int(11) NOT NULL,
  `MaterialName` varchar(100) NOT NULL,
  `SupplierID` int(11) DEFAULT NULL,
  `QuantityInStock` int(11) DEFAULT NULL,
  `UnitCost` decimal(10,2) DEFAULT NULL,
  `LastRestockedDate` date DEFAULT NULL,
  `MinimumStock` int(11) DEFAULT NULL,
  `raw_warehouse` varchar(255) NOT NULL,
  `raw_material_img` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `rawmaterials`
--

INSERT INTO `rawmaterials` (`MaterialID`, `MaterialName`, `SupplierID`, `QuantityInStock`, `UnitCost`, `LastRestockedDate`, `MinimumStock`, `raw_warehouse`, `raw_material_img`) VALUES
(1, 'TinPlate', 1, 9726, 1500.00, '2025-03-09', 5000, 'Paranaque City', 'tinplate.jpg'),
(2, 'Steel', 2, 10000, 1500.00, '2025-02-28', 5000, 'Makati City', 'steel.jpg'),
(3, 'Aluminum', 3, 9987, 1500.00, '2025-02-18', 5000, 'Caloocan City', 'aluminum.jpg'),
(4, 'Stainless Steel', 4, 9796, 1500.00, '2025-02-18', 5000, 'Quezon City', 'stainlesssteel.jpg'),
(5, 'Bronze', 4, 10000, 50.00, '2025-03-03', 5000, 'Quezon City', 'material_1741013499.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `ShipmentID` int(11) NOT NULL,
  `CustomerOrderID` int(11) NOT NULL,
  `CarrierID` int(11) NOT NULL,
  `ShipmentDate` datetime DEFAULT NULL,
  `TrackingNumber` varchar(100) DEFAULT NULL,
  `Status` enum('Pending','In Transit','Delivered','Failed') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storage_transfers`
--

CREATE TABLE `storage_transfers` (
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `from_warehouse` int(11) NOT NULL,
  `to_warehouse` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','in_transit','transferred','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `storage_transfers`
--

INSERT INTO `storage_transfers` (`transfer_id`, `product_id`, `quantity`, `from_warehouse`, `to_warehouse`, `requested_by`, `requested_at`, `status`, `approved_by`, `approved_at`, `completed_at`, `notes`) VALUES
(1, 11, 70, 4, 1, 1, '2025-03-12 00:40:32', 'transferred', 1, '2025-03-12 00:42:36', '2025-03-12 00:42:43', 'Test'),
(2, 11, 50, 4, 2, 1, '2025-03-12 00:43:02', 'transferred', 1, '2025-03-12 00:43:07', '2025-03-12 00:43:17', 'Test');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `SupplierID` int(11) NOT NULL,
  `SupplierName` varchar(100) NOT NULL,
  `ContactPerson` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`SupplierID`, `SupplierName`, `ContactPerson`, `Phone`, `Email`, `Address`) VALUES
(1, 'TinPlate Supplier', 'Christian Earl Tapit', '09123456789', 'christianearltapit@gmail.com', 'Block 1 Lot 2 Normal City'),
(2, 'Steel Plate Supplier', 'Kim Jensen Yebes', '09123456789', 'kimjensenyebes@gmail.com', 'Block 1 Lot 2 Normal Street'),
(3, 'Aluminum Supplier', 'Axel Jilian Bumatay', '09123456789', 'axeljilianbumatay@gmail.com', 'Block 1 Lot 2 Normal Steet'),
(4, 'Stainless Steel', 'Aly Sacay', '09123456789', 'alysacay@gmail.com', 'Block 1 Lot 2 Normal Street'),
(5, 'Bronze Supplier', 'Suisei Hoshimachi', '09123456789', 'suiseihoshimachi@gmail.com', 'Block 1 Lot 2 Normal Street');

-- --------------------------------------------------------

--
-- Table structure for table `transfer_history`
--

CREATE TABLE `transfer_history` (
  `history_id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `from_warehouse` int(11) NOT NULL,
  `to_warehouse` int(11) NOT NULL,
  `transfer_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `transfer_history`
--

INSERT INTO `transfer_history` (`history_id`, `transfer_id`, `product_id`, `quantity`, `from_warehouse`, `to_warehouse`, `transfer_date`, `status`) VALUES
(1, 1, 11, 70, 4, 1, '2025-03-12 00:42:36', 'approved'),
(2, 2, 11, 50, 4, 2, '2025-03-12 00:43:07', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_inventory`
--

CREATE TABLE `warehouse_inventory` (
  `inventory_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `warehouse_weight_unit` varchar(32) DEFAULT 'kg',
  `current_usage` decimal(10,2) DEFAULT 0.00,
  `capacity` decimal(10,2) DEFAULT 0.00,
  `remaining_capacity` decimal(10,2) GENERATED ALWAYS AS (`capacity` - `current_usage`) STORED,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `warehouse_inventory`
--

INSERT INTO `warehouse_inventory` (`inventory_id`, `warehouse_id`, `product_id`, `quantity`, `warehouse_weight_unit`, `current_usage`, `capacity`, `last_updated`) VALUES
(1, 2, 1, 9000, 'kg', 20.00, 20000.00, '2025-03-11 02:26:52'),
(8, 2, 11, 62, 'kg', 10000.00, 20000.00, '2025-03-12 00:43:07'),
(9, 4, 11, 0, 'kg', 0.00, 50000.00, '2025-03-12 00:43:07'),
(11, 3, 2, 0, 'kg', 0.00, 50000.00, '2025-03-11 02:45:44'),
(12, 1, 11, 70, 'kg', 14000.00, 20000.00, '2025-03-12 00:42:36');

--
-- Triggers `warehouse_inventory`
--
DELIMITER $$
CREATE TRIGGER `before_inventory_update` BEFORE UPDATE ON `warehouse_inventory` FOR EACH ROW BEGIN
    IF NEW.quantity < 0 THEN
        SET NEW.quantity = 0;
    END IF;
    
    IF NEW.current_usage < 0 THEN
        SET NEW.current_usage = 0;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_inventory_remaining_capacity` BEFORE UPDATE ON `warehouse_inventory` FOR EACH ROW BEGIN
    SET NEW.remaining_capacity = NEW.capacity - NEW.current_usage;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billofmaterials`
--
ALTER TABLE `billofmaterials`
  ADD PRIMARY KEY (`BOMID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `MaterialID` (`MaterialID`);

--
-- Indexes for table `carriers`
--
ALTER TABLE `carriers`
  ADD PRIMARY KEY (`CarrierID`);

--
-- Indexes for table `customerorders`
--
ALTER TABLE `customerorders`
  ADD PRIMARY KEY (`CustomerOrderID`),
  ADD KEY `CustomerID` (`CustomerID`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`CustomerID`);

--
-- Indexes for table `customerticket`
--
ALTER TABLE `customerticket`
  ADD PRIMARY KEY (`TicketID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `idx_order_detail` (`order_detail_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`EmployeeID`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`LocationID`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`messageID`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `orderdetails`
--
ALTER TABLE `orderdetails`
  ADD PRIMARY KEY (`OrderDetailID`),
  ADD KEY `CustomerOrderID` (`CustomerOrderID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `productionorders`
--
ALTER TABLE `productionorders`
  ADD PRIMARY KEY (`OrderID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `fk_warehouse` (`warehouseID`);

--
-- Indexes for table `production_delivery_tracking`
--
ALTER TABLE `production_delivery_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `fk_production_order` (`production_order_id`),
  ADD KEY `fk_order_detail` (`order_detail_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`ProductID`),
  ADD KEY `fk_product_location` (`LocationID`),
  ADD KEY `fk_product_material` (`MaterialID`);

--
-- Indexes for table `products_warehouse`
--
ALTER TABLE `products_warehouse`
  ADD PRIMARY KEY (`productLocationID`);

--
-- Indexes for table `rawmaterials`
--
ALTER TABLE `rawmaterials`
  ADD PRIMARY KEY (`MaterialID`),
  ADD KEY `idx_supplier` (`SupplierID`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`ShipmentID`),
  ADD KEY `CustomerOrderID` (`CustomerOrderID`);

--
-- Indexes for table `storage_transfers`
--
ALTER TABLE `storage_transfers`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `from_warehouse` (`from_warehouse`),
  ADD KEY `to_warehouse` (`to_warehouse`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`SupplierID`);

--
-- Indexes for table `transfer_history`
--
ALTER TABLE `transfer_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `warehouse_product` (`warehouse_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billofmaterials`
--
ALTER TABLE `billofmaterials`
  MODIFY `BOMID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `carriers`
--
ALTER TABLE `carriers`
  MODIFY `CarrierID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customerorders`
--
ALTER TABLE `customerorders`
  MODIFY `CustomerOrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `CustomerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customerticket`
--
ALTER TABLE `customerticket`
  MODIFY `TicketID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `LocationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `messageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orderdetails`
--
ALTER TABLE `orderdetails`
  MODIFY `OrderDetailID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `productionorders`
--
ALTER TABLE `productionorders`
  MODIFY `OrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `production_delivery_tracking`
--
ALTER TABLE `production_delivery_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `ProductID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `products_warehouse`
--
ALTER TABLE `products_warehouse`
  MODIFY `productLocationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rawmaterials`
--
ALTER TABLE `rawmaterials`
  MODIFY `MaterialID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `ShipmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storage_transfers`
--
ALTER TABLE `storage_transfers`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `SupplierID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transfer_history`
--
ALTER TABLE `transfer_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billofmaterials`
--
ALTER TABLE `billofmaterials`
  ADD CONSTRAINT `billofmaterials_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`),
  ADD CONSTRAINT `billofmaterials_ibfk_2` FOREIGN KEY (`MaterialID`) REFERENCES `rawmaterials` (`MaterialID`);

--
-- Constraints for table `customerticket`
--
ALTER TABLE `customerticket`
  ADD CONSTRAINT `customerticket_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD CONSTRAINT `delivery_tracking_ibfk_1` FOREIGN KEY (`order_detail_id`) REFERENCES `orderdetails` (`OrderDetailID`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`EmployeeID`);

--
-- Constraints for table `orderdetails`
--
ALTER TABLE `orderdetails`
  ADD CONSTRAINT `orderdetails_ibfk_1` FOREIGN KEY (`CustomerOrderID`) REFERENCES `customerorders` (`CustomerOrderID`),
  ADD CONSTRAINT `orderdetails_ibfk_2` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`);

--
-- Constraints for table `productionorders`
--
ALTER TABLE `productionorders`
  ADD CONSTRAINT `fk_warehouse` FOREIGN KEY (`warehouseID`) REFERENCES `products_warehouse` (`productLocationID`);

--
-- Constraints for table `production_delivery_tracking`
--
ALTER TABLE `production_delivery_tracking`
  ADD CONSTRAINT `fk_order_detail` FOREIGN KEY (`order_detail_id`) REFERENCES `orderdetails` (`OrderDetailID`),
  ADD CONSTRAINT `fk_production_order` FOREIGN KEY (`production_order_id`) REFERENCES `productionorders` (`OrderID`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_location` FOREIGN KEY (`LocationID`) REFERENCES `products_warehouse` (`productLocationID`),
  ADD CONSTRAINT `fk_product_material` FOREIGN KEY (`MaterialID`) REFERENCES `rawmaterials` (`MaterialID`);

--
-- Constraints for table `warehouse_inventory`
--
ALTER TABLE `warehouse_inventory`
  ADD CONSTRAINT `warehouse_inventory_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `products_warehouse` (`productLocationID`),
  ADD CONSTRAINT `warehouse_inventory_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`ProductID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
