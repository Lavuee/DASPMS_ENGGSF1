-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 01, 2026 at 08:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `daspms`
--

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(20) NOT NULL,
  `middle_name` varchar(20) DEFAULT NULL,
  `last_name` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `credit_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credit_due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive','Archived') NOT NULL DEFAULT 'Active',
  `deactivated_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `first_name`, `middle_name`, `last_name`, `email`, `contact_number`, `address`, `credit_balance`, `credit_due_date`, `created_at`, `user_id`, `status`, `deactivated_at`, `archived_at`) VALUES
(1, 'John', NULL, 'Doe', 'customer@norilys.com', '09123456789', 'no address provided', 0.00, NULL, '2026-04-25 12:52:33', 4, 'Active', NULL, NULL),
(2, 'Jennie', NULL, 'Batumbakal', 'jenjen@gmail.com', '09562372832', 'Ambiong', 0.00, NULL, '2026-04-30 00:04:19', NULL, 'Active', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `job_order_id` int(11) DEFAULT NULL,
  `part_order_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `invoice_type` enum('Service','Part') NOT NULL,
  `subtotal_services` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal_parts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Partial','Not Paid') NOT NULL DEFAULT 'Not Paid',
  `date_issued` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `is_voided` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice`
--

INSERT INTO `invoice` (`invoice_id`, `customer_id`, `job_order_id`, `part_order_id`, `created_by`, `invoice_number`, `invoice_type`, `subtotal_services`, `subtotal_parts`, `total_amount`, `amount_paid`, `balance_due`, `payment_status`, `date_issued`, `due_date`, `is_voided`) VALUES
(1, 1, 1, NULL, 1, 'INV-20260428-155447-', 'Service', 0.00, 0.00, 5000.00, 5000.00, 0.00, 'Paid', '2026-04-28 21:54:47', NULL, 0),
(2, 1, NULL, 2, 1, 'INV-PART-20260428-16', 'Part', 0.00, 200.00, 200.00, 200.00, 0.00, 'Paid', '2026-04-28 22:10:21', NULL, 0),
(3, 1, 2, NULL, 1, 'INV-JOB-20260428-163', 'Service', 0.00, 0.00, 3000.00, 3000.00, 0.00, 'Paid', '2026-04-28 22:30:42', NULL, 0),
(4, 1, 3, NULL, 1, 'INV-JOB-20260428-175', 'Service', 0.00, 0.00, 10000.00, 10000.00, 0.00, 'Paid', '2026-04-28 23:53:54', NULL, 0),
(5, 1, 4, NULL, 1, 'INV-JOB-20260428-175', 'Service', 0.00, 0.00, 2333.00, 2333.00, 0.00, 'Paid', '2026-04-28 23:56:29', NULL, 0),
(6, 1, 5, NULL, 1, 'INV-JOB-20260428-175', 'Service', 0.00, 0.00, 4444.00, 0.00, 4444.00, 'Not Paid', '2026-04-28 23:57:29', NULL, 0),
(7, 1, 6, NULL, 1, 'INV-JOB-20260429-143', 'Service', 0.00, 0.00, 5000.00, 0.00, 5000.00, 'Not Paid', '2026-04-29 20:30:13', NULL, 0),
(8, 1, 7, NULL, 1, 'INV-JOB-20260429-143', 'Service', 0.00, 0.00, 3000.00, 3000.00, 0.00, 'Paid', '2026-04-29 20:31:38', NULL, 0),
(9, 1, NULL, 3, 1, 'INV-PART-20260429-18', 'Part', 0.00, 600.00, 600.00, 0.00, 600.00, 'Not Paid', '2026-04-30 00:27:00', NULL, 0),
(10, 1, NULL, 4, 1, 'INV-PART-20260429-18', 'Part', 0.00, 500.00, 500.00, 500.00, 0.00, 'Paid', '2026-04-30 00:33:28', NULL, 0),
(11, 1, NULL, 6, 1, 'INV-PART-20260429-20', 'Part', 0.00, 300.00, 300.00, 300.00, 0.00, 'Paid', '2026-04-30 02:47:32', NULL, 0),
(12, 1, NULL, 5, 1, 'INV-PART-20260429-20', 'Part', 0.00, 200.00, 200.00, 200.00, 0.00, 'Paid', '2026-04-30 02:48:03', NULL, 0),
(13, 1, NULL, 7, 1, 'INV-PART-20260430-12', 'Part', 0.00, 700.00, 700.00, 700.00, 0.00, 'Paid', '2026-04-30 18:27:32', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `job_order`
--

CREATE TABLE `job_order` (
  `job_order_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `job_order_number` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','In Progress','Ready for Pickup','Completed') NOT NULL DEFAULT 'Pending',
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_completed` datetime DEFAULT NULL,
  `requires_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `down_payment_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order`
--

INSERT INTO `job_order` (`job_order_id`, `vehicle_id`, `customer_id`, `created_by`, `job_order_number`, `description`, `status`, `estimated_cost`, `final_cost`, `date_created`, `date_completed`, `requires_down_payment`, `down_payment_amount`) VALUES
(1, 1, 1, 1, 'JO-79F1A', 'broken battery', 'Completed', 5000.00, 0.00, '2026-04-28 21:54:38', '2026-04-28 23:09:37', 0, 0.00),
(2, 1, 1, 1, 'JO-ED959', 'leak', 'Completed', 3000.00, 0.00, '2026-04-28 21:55:34', '2026-04-28 23:09:37', 0, 0.00),
(3, 1, 1, 1, 'JO-E9E72', 'dsdsdss', 'Completed', 10000.00, 0.00, '2026-04-28 23:47:15', '2026-04-28 23:53:36', 0, 0.00),
(4, 1, 1, 1, 'JO-8FA59', 'dsdsdsd', 'Completed', 2333.00, 0.00, '2026-04-28 23:55:03', '2026-04-28 23:55:07', 0, 0.00),
(5, 1, 1, 1, 'JO-E15AD', 'dsadsd', 'Completed', 4444.00, 0.00, '2026-04-28 23:56:47', '2026-04-28 23:56:51', 0, 0.00),
(6, 1, 1, 1, 'JO-D52F4', 'asasa', 'Completed', 5000.00, 0.00, '2026-04-29 20:27:46', '2026-04-29 20:29:42', 0, 0.00),
(7, 1, 1, 1, 'JO-3BA6F', 'akaks', 'Completed', 3000.00, 0.00, '2026-04-29 20:30:38', '2026-04-29 20:31:11', 0, 0.00),
(8, 1, 1, 1, 'JO-6CE64', 'leaking', 'Pending', 5500.00, 0.00, '2026-04-30 12:23:31', NULL, 0, 0.00),
(9, 1, 1, 1, 'JO-4D199', 'flat tire', 'Pending', 1000.00, 0.00, '2026-04-30 13:32:30', NULL, 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `job_order_part`
--

CREATE TABLE `job_order_part` (
  `job_part_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity_used` smallint(6) NOT NULL,
  `unit_price_at_use` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_order_service`
--

CREATE TABLE `job_order_service` (
  `job_service_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` smallint(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `part`
--

CREATE TABLE `part` (
  `part_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `part_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_on_hand` smallint(6) NOT NULL DEFAULT 0,
  `low_stock_threshold` smallint(6) NOT NULL DEFAULT 3,
  `is_low_stock` tinyint(1) NOT NULL DEFAULT 0,
  `supplier_reference` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `full_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part`
--

INSERT INTO `part` (`part_id`, `category`, `part_name`, `description`, `unit_price`, `quantity_on_hand`, `low_stock_threshold`, `is_low_stock`, `supplier_reference`, `is_active`, `cost_price`, `image`, `full_description`) VALUES
(1, 'Capacitor', 'flux capacitor', '', 4651.00, 456, 0, 0, '', 0, NULL, NULL, NULL),
(2, '', 'yig', 'Battery part', 876.00, 3, 5, 0, NULL, 0, 613.20, NULL, NULL),
(3, 'Electrical Parts & Supplies', 'Capacitors', 'Common electrical supply used for motor, wiring, and repair support.', 500.00, 5, 5, 0, 'Initial catalog item', 1, 300.00, 'part-1777312816-ea5e6d58.png', 'Capacitors are electrical components commonly used to store and release electrical energy. In auto repair and electrical work, they may support motor operation, wiring repair, and other electrical maintenance needs.'),
(4, 'Electrical Parts & Supplies', 'Switches', 'Selected switches used for automotive and electrical repair needs.', 200.00, 11, 5, 0, 'Initial catalog item', 1, 100.00, 'part-1777312876-eba2dd31.png', 'Automotive switches are used to control electrical circuits in vehicles. They may be used for lights, accessories, wipers, auxiliary controls, and other repair or replacement needs.'),
(5, 'Electrical Parts & Supplies', 'Magnetic Wires', 'Supply item used for rewinding and related electrical repair work.', 300.00, 7, 5, 0, 'Initial catalog item', 1, 150.00, 'part-1777312956-8a3f9b73.png', 'Magnetic wires are commonly used for rewinding motors, alternators, starters, and other electrical components. They support repair work that requires replacement or restoration of electrical coils.'),
(6, 'Starter & Motor Parts', 'Starter Armatures', 'Starter-related component used for starter motor repair and replacement support.', 250.00, 8, 5, 0, 'Initial catalog item', 1, 100.00, 'part-1777313026-afdb8678.png', 'Starter armatures are internal starter motor components that help produce rotational force during engine starting. They are used when repairing or replacing damaged starter motor parts.');

-- --------------------------------------------------------

--
-- Table structure for table `part_order`
--

CREATE TABLE `part_order` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Approved','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_order`
--

INSERT INTO `part_order` (`order_id`, `customer_id`, `order_date`, `total_amount`, `status`) VALUES
(1, 1, '2026-04-28 21:49:41', 500.00, 'Completed'),
(2, 1, '2026-04-28 22:09:25', 200.00, 'Completed'),
(3, 1, '2026-04-30 00:24:47', 600.00, 'Cancelled'),
(4, 1, '2026-04-30 00:33:11', 500.00, 'Completed'),
(5, 1, '2026-04-30 02:41:00', 200.00, 'Completed'),
(6, 1, '2026-04-30 02:45:01', 300.00, 'Completed'),
(7, 1, '2026-04-30 02:52:50', 700.00, 'Completed'),
(8, 1, '2026-04-30 03:14:26', 550.00, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `part_order_item`
--

CREATE TABLE `part_order_item` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity` smallint(6) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_order_item`
--

INSERT INTO `part_order_item` (`order_item_id`, `order_id`, `part_id`, `quantity`, `unit_price`) VALUES
(1, 1, 3, 1, 500.00),
(2, 2, 4, 1, 200.00),
(3, 3, 5, 2, 300.00),
(4, 4, 6, 2, 250.00),
(5, 5, 4, 1, 200.00),
(6, 6, 5, 1, 300.00),
(7, 7, 4, 1, 200.00),
(8, 7, 3, 1, 500.00),
(9, 8, 5, 1, 300.00),
(10, 8, 6, 1, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Bank','Cheque','GCash') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `is_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `invoice_id`, `received_by`, `amount`, `payment_method`, `reference_number`, `is_down_payment`, `payment_date`, `notes`) VALUES
(1, 1, 1, 5000.00, 'Cash', '', 0, '2026-04-28 21:54:50', NULL),
(2, 2, 1, 200.00, 'Cash', '', 0, '2026-04-28 22:28:09', NULL),
(3, 3, 1, 3000.00, 'Cash', '', 0, '2026-04-28 22:48:59', NULL),
(4, 4, 1, 10000.00, 'GCash', '32324243', 0, '2026-04-28 23:54:20', NULL),
(5, 5, 1, 2333.00, 'Cheque', '46454', 0, '2026-04-29 00:03:00', NULL),
(6, 10, 1, 500.00, 'Cash', '0023232', 0, '2026-04-30 00:36:56', NULL),
(7, 11, 1, 300.00, 'Cash', '', 0, '2026-04-30 02:48:16', NULL),
(8, 12, 1, 200.00, 'Cash', '', 0, '2026-04-30 02:48:38', NULL),
(9, 13, 1, 700.00, 'Cash', '', 0, '2026-04-30 18:27:59', NULL),
(10, 8, 1, 3000.00, 'Cash', '', 0, '2026-04-30 22:33:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pos_item`
--

CREATE TABLE `pos_item` (
  `pos_item_id` int(11) NOT NULL,
  `pos_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity_sold` smallint(6) NOT NULL,
  `unit_price_at_sale` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_item`
--

INSERT INTO `pos_item` (`pos_item_id`, `pos_id`, `part_id`, `quantity_sold`, `unit_price_at_sale`, `subtotal`) VALUES
(4, 1, 2, 1, 876.00, 876.00),
(5, 2, 3, 1, 500.00, 500.00),
(6, 3, 3, 1, 500.00, 500.00),
(7, 4, 3, 1, 500.00, 500.00),
(8, 5, 4, 1, 200.00, 200.00);

-- --------------------------------------------------------

--
-- Table structure for table `pos_transaction`
--

CREATE TABLE `pos_transaction` (
  `pos_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `mileage` int(10) UNSIGNED DEFAULT NULL,
  `processed_by` int(11) NOT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Completed',
  `payment_method` enum('Cash','Bank','Cheque','GCash') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_transaction`
--

INSERT INTO `pos_transaction` (`pos_id`, `invoice_id`, `customer_id`, `vehicle_id`, `mileage`, `processed_by`, `transaction_date`, `total_amount`, `status`, `payment_method`, `reference_number`) VALUES
(1, NULL, 1, NULL, NULL, 1, '2026-04-25 14:53:17', 876.00, 'Completed', 'GCash', ''),
(2, NULL, 1, NULL, NULL, 1, '2026-04-28 22:55:47', 500.00, 'Completed', 'Cash', ''),
(3, NULL, 1, NULL, NULL, 1, '2026-04-29 00:03:20', 500.00, 'Completed', 'Cash', ''),
(4, NULL, 1, NULL, NULL, 1, '2026-04-29 20:47:32', 500.00, 'Completed', 'Cash', '22038028329'),
(5, NULL, 2, NULL, NULL, 1, '2026-04-30 09:59:25', 200.00, 'Completed', 'Cheque', '99838434');

-- --------------------------------------------------------

--
-- Table structure for table `service`
--

CREATE TABLE `service` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requires_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `warranty_days` smallint(6) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `features` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service`
--

INSERT INTO `service` (`service_id`, `service_name`, `category`, `base_price`, `requires_down_payment`, `warranty_days`, `description`, `is_active`, `image`, `full_description`, `features`) VALUES
(1, 'Alternator Repair', 'Rewinding Services', 900.00, 0, 0, 'Checks and repairs alternators to help ensure the vehicle battery charges properly while the engine is running.', 1, 'service-1777313156-beede1ed.png', 'Alternator repair helps restore proper charging performance for the vehicle battery and electrical system. This service may include inspection, diagnosis, component repair, and testing to ensure the alternator is working correctly.', 'Inspection\r\nAlternator component repair\r\nPost-maintenance testing'),
(2, 'Starter Repair', 'Rewinding Services', 1500.00, 0, 0, 'Resolves starter-related problems that prevent the engine from starting properly.', 1, 'service-1777313219-75673d7f.png', 'Starter repair focuses on diagnosing and fixing starter motor problems that may prevent the engine from starting. The service may include inspection, replacement of faulty parts, cleaning, repair, and final function testing.', 'Starter inspection\r\nRepair or replacement of faulty parts\r\nFunction testing'),
(3, 'Radiator Repair', 'Rewinding Services', 3000.00, 0, 0, 'Handles radiator leaks and overheating issues to help maintain stable engine temperature.', 1, 'service-1777313287-e1a38bf9.png', 'Radiator repair helps address cooling system issues such as leaks, overheating, and reduced cooling performance. The service may include leak detection, repair, and cooling performance checks.', 'Leak detection\r\nRadiator repair\r\nCooling performance check'),
(4, 'Engine Overhaul', 'Other Services', 3000.00, 0, 0, 'Checks and repairs major engine-related issues to restore performance and reliability.', 1, 'service-1777313350-5f9ca8af.png', 'Engine overhaul involves inspecting and repairing major engine components to help restore performance, reliability, and safe operation. Actual recommendations depend on the vehicle condition after inspection.\r\nFeatures:', 'Engine inspection\r\nRepair recommendations\r\nMechanical checking');

-- --------------------------------------------------------

--
-- Table structure for table `stock_in`
--

CREATE TABLE `stock_in` (
  `stock_in_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `quantity_added` smallint(6) NOT NULL,
  `cost_per_unit` decimal(10,2) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `supplier_document_ref` varchar(50) NOT NULL,
  `date_received` date NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(20) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `username` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Owner','Cashier','Head Mechanic','Customer') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `first_name`, `middle_name`, `last_name`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `email`) VALUES
(1, 'Admin', NULL, 'Owner', 'owner', 'password123', 'Owner', 1, '2026-04-25 12:45:00', 'owner@norilys.com'),
(2, 'Jane', NULL, 'Cashier', 'cashier', 'password123', 'Cashier', 1, '2026-04-25 12:45:00', 'cashier@norilys.com'),
(3, 'Mike', NULL, 'Mechanic', 'mechanic', 'password123', 'Head Mechanic', 1, '2026-04-25 12:45:00', 'mechanic@norilys.com'),
(4, 'John', NULL, 'Doe', 'customer', 'password123', 'Customer', 1, '2026-04-25 12:45:00', 'customer@norilys.com'),
(5, 'Jennie', NULL, 'Batumbakal', 'jenjen@gmail.com', '$2y$10$8sBOpPTeqDxRJ.9ST8vfJOWXWnLvkuSTAFxL10On5XhMrIKcYL4.W', 'Customer', 1, '2026-04-30 00:53:51', 'jenjen@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle`
--

CREATE TABLE `vehicle` (
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `vin` varchar(50) DEFAULT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` year(4) NOT NULL,
  `color` varchar(30) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Active','Inactive','Archived') NOT NULL DEFAULT 'Active',
  `deactivated_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle`
--

INSERT INTO `vehicle` (`vehicle_id`, `customer_id`, `plate_number`, `vin`, `make`, `model`, `year`, `color`, `notes`, `status`, `deactivated_at`, `archived_at`, `created_at`) VALUES
(1, 1, '564948', NULL, 'jhdjshdjsd', 'sdsds', '2001', 'red', '', 'Active', NULL, NULL, '2026-04-30 11:04:19'),
(2, 2, '162712', NULL, 'Porsche', 'Taycan', '2020', 'Blue', NULL, 'Active', NULL, NULL, '2026-04-30 14:20:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `fk_customer_user` (`user_id`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `unique_part_order_invoice` (`part_order_id`),
  ADD KEY `fk_customer_id_iv` (`customer_id`),
  ADD KEY `fk_job_order_id_iv` (`job_order_id`),
  ADD KEY `fk_created_by_iv` (`created_by`),
  ADD KEY `idx_invoice_status_due` (`payment_status`,`due_date`),
  ADD KEY `invoice_number` (`invoice_number`);

--
-- Indexes for table `job_order`
--
ALTER TABLE `job_order`
  ADD PRIMARY KEY (`job_order_id`),
  ADD KEY `fk_vehicle_id_jo` (`vehicle_id`),
  ADD KEY `fk_customer_id_jo` (`customer_id`),
  ADD KEY `fk_created_by_jo` (`created_by`),
  ADD KEY `idx_job_order_customer_status` (`customer_id`,`status`);

--
-- Indexes for table `job_order_part`
--
ALTER TABLE `job_order_part`
  ADD PRIMARY KEY (`job_part_id`),
  ADD KEY `fk_job_order_id_jop` (`job_order_id`),
  ADD KEY `fk_part_id_jop` (`part_id`);

--
-- Indexes for table `job_order_service`
--
ALTER TABLE `job_order_service`
  ADD PRIMARY KEY (`job_service_id`),
  ADD KEY `fk_job_order_id_jos` (`job_order_id`),
  ADD KEY `fk_service_id_jos` (`service_id`);

--
-- Indexes for table `part`
--
ALTER TABLE `part`
  ADD PRIMARY KEY (`part_id`);

--
-- Indexes for table `part_order`
--
ALTER TABLE `part_order`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `part_order_item`
--
ALTER TABLE `part_order_item`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `part_id` (`part_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_invoice_id_pm` (`invoice_id`),
  ADD KEY `fk_received_by_pm` (`received_by`);

--
-- Indexes for table `pos_item`
--
ALTER TABLE `pos_item`
  ADD PRIMARY KEY (`pos_item_id`),
  ADD KEY `fk_pos_id_pi` (`pos_id`),
  ADD KEY `fk_part_id_pi` (`part_id`);

--
-- Indexes for table `pos_transaction`
--
ALTER TABLE `pos_transaction`
  ADD PRIMARY KEY (`pos_id`),
  ADD KEY `fk_invoice_id_pt` (`invoice_id`),
  ADD KEY `fk_customer_id_pt` (`customer_id`),
  ADD KEY `fk_processed_by_pt` (`processed_by`),
  ADD KEY `fk_vehicle_id_pt` (`vehicle_id`);

--
-- Indexes for table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `idx_service_category_active` (`category`,`is_active`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`stock_in_id`),
  ADD KEY `fk_part_id_si` (`part_id`),
  ADD KEY `fk_recorded_by_si` (`recorded_by`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `idx_user_username` (`username`);

--
-- Indexes for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `idx_vehicle_plate` (`plate_number`),
  ADD UNIQUE KEY `unique_plate_number` (`plate_number`),
  ADD UNIQUE KEY `idx_vehicle_vin` (`vin`),
  ADD KEY `fk_customer_id` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `job_order`
--
ALTER TABLE `job_order`
  MODIFY `job_order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `job_order_part`
--
ALTER TABLE `job_order_part`
  MODIFY `job_part_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_order_service`
--
ALTER TABLE `job_order_service`
  MODIFY `job_service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `part`
--
ALTER TABLE `part`
  MODIFY `part_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `part_order`
--
ALTER TABLE `part_order`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `part_order_item`
--
ALTER TABLE `part_order_item`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `pos_item`
--
ALTER TABLE `pos_item`
  MODIFY `pos_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pos_transaction`
--
ALTER TABLE `pos_transaction`
  MODIFY `pos_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service`
--
ALTER TABLE `service`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `stock_in_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice`
--
ALTER TABLE `invoice`
  ADD CONSTRAINT `fk_created_by_iv` FOREIGN KEY (`created_by`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_customer_id_iv` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `fk_job_order_id_iv` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`);

--
-- Constraints for table `job_order`
--
ALTER TABLE `job_order`
  ADD CONSTRAINT `fk_created_by_jo` FOREIGN KEY (`created_by`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_customer_id_jo` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `fk_vehicle_id_jo` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`);

--
-- Constraints for table `job_order_part`
--
ALTER TABLE `job_order_part`
  ADD CONSTRAINT `fk_job_order_id_jop` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_part_id_jop` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`);

--
-- Constraints for table `job_order_service`
--
ALTER TABLE `job_order_service`
  ADD CONSTRAINT `fk_job_order_id_jos` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_id_jos` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`);

--
-- Constraints for table `part_order`
--
ALTER TABLE `part_order`
  ADD CONSTRAINT `part_order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`);

--
-- Constraints for table `part_order_item`
--
ALTER TABLE `part_order_item`
  ADD CONSTRAINT `part_order_item_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `part_order` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `part_order_item_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `fk_invoice_id_pm` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`),
  ADD CONSTRAINT `fk_received_by_pm` FOREIGN KEY (`received_by`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `pos_item`
--
ALTER TABLE `pos_item`
  ADD CONSTRAINT `fk_part_id_pi` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`),
  ADD CONSTRAINT `fk_pos_id_pi` FOREIGN KEY (`pos_id`) REFERENCES `pos_transaction` (`pos_id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_transaction`
--
ALTER TABLE `pos_transaction`
  ADD CONSTRAINT `fk_customer_id_pt` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `fk_invoice_id_pt` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`),
  ADD CONSTRAINT `fk_processed_by_pt` FOREIGN KEY (`processed_by`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_vehicle_id_pt` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`);

--
-- Constraints for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD CONSTRAINT `fk_part_id_si` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`),
  ADD CONSTRAINT `fk_recorded_by_si` FOREIGN KEY (`recorded_by`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD CONSTRAINT `fk_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
