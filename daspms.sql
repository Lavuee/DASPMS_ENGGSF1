-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2026 at 03:55 PM
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
(2, 'Jennie', NULL, 'Batumbakal', 'jenjen@gmail.com', '09562372832', 'Ambiong', 0.00, NULL, '2026-04-30 00:04:19', NULL, 'Active', NULL, NULL),
(4, 'Lisa', NULL, 'Manoban', 'lisamanoban@gmail.com', '09927373821', 'no address provided', 0.00, NULL, '2026-05-07 21:44:50', 6, 'Active', NULL, NULL),
(5, 'Juan', NULL, 'Pedro', NULL, '09828328282', 'no address provided', 0.00, NULL, '2026-05-07 21:47:45', NULL, 'Active', NULL, NULL),
(14, 'Jani', NULL, 'afe', 'leela090104@gmail.com', '09260432900', 'baguio', 0.00, NULL, '2026-05-16 16:37:29', NULL, 'Active', NULL, NULL),
(15, 'avi', NULL, 'ien', 'leelavinagustin@gmail.com', '33333333333', 'no address provided', 0.00, NULL, '2026-05-16 17:16:42', 15, 'Active', NULL, NULL),
(16, 'Nicole', NULL, 'Atienza', 'nixatienza04@gmail.com', '09682742142', 'no address provided', 0.00, NULL, '2026-05-16 20:10:40', 16, 'Active', NULL, NULL),
(17, 'ALaf', NULL, 'aer', NULL, '09876543212', 'baguio', 0.00, NULL, '2026-05-16 21:09:33', NULL, 'Active', NULL, NULL);

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
(7, 1, 6, NULL, 1, 'INV-JOB-20260429-143', 'Service', 0.00, 0.00, 5000.00, 5000.00, 0.00, 'Paid', '2026-04-29 20:30:13', NULL, 0),
(8, 1, 7, NULL, 1, 'INV-JOB-20260429-143', 'Service', 0.00, 0.00, 3000.00, 3000.00, 0.00, 'Paid', '2026-04-29 20:31:38', NULL, 0),
(9, 1, NULL, 3, 1, 'INV-PART-20260429-18', 'Part', 0.00, 600.00, 600.00, 0.00, 600.00, 'Not Paid', '2026-04-30 00:27:00', NULL, 0),
(10, 1, NULL, 4, 1, 'INV-PART-20260429-18', 'Part', 0.00, 500.00, 500.00, 500.00, 0.00, 'Paid', '2026-04-30 00:33:28', NULL, 0),
(11, 1, NULL, 6, 1, 'INV-PART-20260429-20', 'Part', 0.00, 300.00, 300.00, 300.00, 0.00, 'Paid', '2026-04-30 02:47:32', NULL, 0),
(12, 1, NULL, 5, 1, 'INV-PART-20260429-20', 'Part', 0.00, 200.00, 200.00, 200.00, 0.00, 'Paid', '2026-04-30 02:48:03', NULL, 0),
(13, 1, NULL, 7, 1, 'INV-PART-20260430-12', 'Part', 0.00, 700.00, 700.00, 700.00, 0.00, 'Paid', '2026-04-30 18:27:32', NULL, 0),
(14, 4, NULL, 11, 1, 'INV-PART-20260508-06', 'Part', 0.00, 500.00, 500.00, 0.00, 500.00, 'Not Paid', '2026-05-08 12:27:50', NULL, 0),
(15, 4, NULL, 10, 1, 'INV-PART-20260508-06', 'Part', 0.00, 200.00, 200.00, 0.00, 200.00, 'Not Paid', '2026-05-08 12:31:08', NULL, 0),
(16, 4, NULL, 13, 1, 'INV-PART-20260508-08', 'Part', 0.00, 200.00, 200.00, 200.00, 0.00, 'Paid', '2026-05-08 14:07:59', NULL, 0),
(17, 4, NULL, 14, 1, 'INV-PART-20260508-08', 'Part', 0.00, 200.00, 200.00, 150.00, 50.00, 'Partial', '2026-05-08 14:24:56', NULL, 0),
(18, 4, NULL, 12, 1, 'INV-PART-20260508-08', 'Part', 0.00, 200.00, 200.00, 100.00, 100.00, 'Partial', '2026-05-08 14:27:21', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `job_order`
--

CREATE TABLE `job_order` (
  `job_order_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_mechanic_id` int(11) DEFAULT NULL,
  `job_order_number` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `request_source` enum('Walk-in','Online') NOT NULL DEFAULT 'Walk-in',
  `status` enum('Pending','In Progress','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `expected_completion_date` date DEFAULT NULL,
  `date_completed` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `requires_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `down_payment_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order`
--

INSERT INTO `job_order` (`job_order_id`, `vehicle_id`, `customer_id`, `created_by`, `assigned_mechanic_id`, `job_order_number`, `description`, `request_source`, `status`, `estimated_cost`, `final_cost`, `date_created`, `expected_completion_date`, `date_completed`, `completed_by`, `cancellation_reason`, `cancelled_at`, `requires_down_payment`, `down_payment_amount`) VALUES
(1, 1, 1, 1, NULL, 'JO-79F1A', 'broken battery', 'Walk-in', 'Completed', 5000.00, 0.00, '2026-04-28 21:54:38', NULL, '2026-04-28 23:09:37', NULL, NULL, NULL, 0, 0.00),
(2, 1, 1, 1, NULL, 'JO-ED959', 'leak', 'Walk-in', 'Completed', 3000.00, 0.00, '2026-04-28 21:55:34', NULL, '2026-04-28 23:09:37', NULL, NULL, NULL, 0, 0.00),
(3, 1, 1, 1, NULL, 'JO-E9E72', 'dsdsdss', 'Walk-in', 'Completed', 10000.00, 0.00, '2026-04-28 23:47:15', NULL, '2026-04-28 23:53:36', NULL, NULL, NULL, 0, 0.00),
(4, 1, 1, 1, NULL, 'JO-8FA59', 'dsdsdsd', 'Walk-in', 'Completed', 2333.00, 0.00, '2026-04-28 23:55:03', NULL, '2026-04-28 23:55:07', NULL, NULL, NULL, 0, 0.00),
(5, 1, 1, 1, NULL, 'JO-E15AD', 'dsadsd', 'Walk-in', 'Completed', 4444.00, 0.00, '2026-04-28 23:56:47', NULL, '2026-04-28 23:56:51', NULL, NULL, NULL, 0, 0.00),
(6, 1, 1, 1, NULL, 'JO-D52F4', 'asasa', 'Walk-in', 'Completed', 5000.00, 0.00, '2026-04-29 20:27:46', NULL, '2026-04-29 20:29:42', NULL, NULL, NULL, 0, 0.00),
(7, 1, 1, 1, NULL, 'JO-3BA6F', 'akaks', 'Walk-in', 'Completed', 3000.00, 0.00, '2026-04-29 20:30:38', NULL, '2026-04-29 20:31:11', NULL, NULL, NULL, 0, 0.00),
(8, 1, 1, 1, NULL, 'JO-6CE64', 'leaking', 'Walk-in', 'Pending', 5500.00, 0.00, '2026-04-30 12:23:31', NULL, NULL, NULL, NULL, NULL, 0, 0.00),
(9, 1, 1, 1, NULL, 'JO-4D199', 'flat tire', 'Walk-in', 'Pending', 1000.00, 0.00, '2026-04-30 13:32:30', NULL, NULL, NULL, NULL, NULL, 0, 0.00),
(10, 2, 2, 1, NULL, 'JO-87872', 'Worn out brake pads', 'Walk-in', 'Pending', 3300.00, 0.00, '2026-05-02 00:43:16', NULL, NULL, NULL, NULL, NULL, 0, 0.00),
(11, 3, 4, 1, 3, 'JO-025D9', 'Online Service Request: Engine Overhaul\r\n\r\nCustomer Concern: leaking oil\r\n\r\nPreferred Appointment: 2026-05-10 09:00:00', 'Online', 'Cancelled', 3000.00, 0.00, '2026-05-07 23:57:05', NULL, NULL, NULL, NULL, '2026-05-08 00:06:39', 1, 500.00),
(12, 3, 4, 1, 3, 'JO-3959A', 'Request Source: Online Service Appointment\n\nRequested Service: Alternator Repair\n\nCustomer Concern / Symptoms: change battery\n\nPreferred Appointment: May 15, 2026 10:00 AM', 'Online', 'Pending', 900.00, 0.00, '2026-05-08 00:40:23', NULL, NULL, NULL, NULL, NULL, 0, 0.00),
(13, 4, 14, 1, NULL, 'JO-3693E', 'dh5t', 'Walk-in', 'In Progress', 5700.00, 0.00, '2026-05-16 16:37:29', NULL, NULL, NULL, NULL, NULL, 0, 0.00),
(14, 5, 17, 1, NULL, 'JO-90B6E', 'fase', 'Walk-in', 'Completed', 7950.00, 0.00, '2026-05-16 21:09:33', NULL, '2026-05-16 21:12:09', 1, NULL, NULL, 0, 0.00);

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

--
-- Dumping data for table `job_order_part`
--

INSERT INTO `job_order_part` (`job_part_id`, `job_order_id`, `part_id`, `quantity_used`, `unit_price_at_use`, `subtotal`) VALUES
(1, 13, 5, 2, 300.00, 600.00),
(2, 13, 5, 2, 300.00, 600.00),
(3, 14, 7, 3, 1850.00, 5550.00);

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

--
-- Dumping data for table `job_order_service`
--

INSERT INTO `job_order_service` (`job_service_id`, `job_order_id`, `service_id`, `quantity`, `unit_price`, `subtotal`, `notes`) VALUES
(1, 11, 4, 1, 3000.00, 3000.00, 'Created from online service request SR-846974'),
(2, 12, 1, 1, 900.00, 900.00, 'Created from online service request SR-B4F8B6'),
(3, 13, 3, 1, 3000.00, 3000.00, NULL),
(4, 13, 2, 1, 1500.00, 1500.00, NULL),
(5, 14, 1, 1, 900.00, 900.00, NULL),
(6, 14, 2, 1, 1500.00, 1500.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `part`
--

CREATE TABLE `part` (
  `part_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `part_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `specification` text DEFAULT NULL,
  `compatibility` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'piece',
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_on_hand` smallint(6) NOT NULL DEFAULT 0,
  `low_stock_threshold` smallint(6) NOT NULL DEFAULT 3,
  `supplier_id` int(11) DEFAULT 1,
  `is_low_stock` tinyint(1) NOT NULL DEFAULT 0,
  `primary_supplier_id` int(11) DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `full_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part`
--

INSERT INTO `part` (`part_id`, `category`, `brand`, `part_name`, `description`, `specification`, `compatibility`, `unit`, `unit_price`, `quantity_on_hand`, `low_stock_threshold`, `supplier_id`, `is_low_stock`, `primary_supplier_id`, `is_active`, `cost_price`, `image`, `full_description`) VALUES
(2, '', NULL, 'yig', 'Battery part', NULL, NULL, 'piece', 876.00, 3, 5, 1, 0, 1, 0, 613.20, NULL, NULL),
(3, 'Electrical Parts & Supplies', 'Motolite', 'Capacitors', 'Common electrical supply used for motor, wiring, and repair support.', '12V', 'Toyota', 'piece', 500.00, 3, 4, 3, 0, 1, 1, 300.00, 'part-1777312816-ea5e6d58.png', 'Capacitors are electrical components commonly used to store and release electrical energy. In auto repair and electrical work, they may support motor operation, wiring repair, and other electrical maintenance needs.'),
(4, 'Electrical Parts & Supplies', 'N/A', 'Switches', 'Selected switches used for automotive and electrical repair needs.', 'N/A', 'N/A', 'piece', 200.00, 3, 4, 3, 0, 1, 1, 100.00, 'part-1777312876-eba2dd31.png', 'Automotive switches are used to control electrical circuits in vehicles. They may be used for lights, accessories, wipers, auxiliary controls, and other repair or replacement needs.'),
(5, 'Electrical Parts &amp; Supplies', 'Motolite', 'Magnetic Wires', 'Supply item used for rewinding and related electrical repair work.', '12V', 'Universal', 'piece', 300.00, 3, 4, 1, 0, 1, 1, 150.00, 'part-1777312956-8a3f9b73.png', 'Magnetic wires are commonly used for rewinding motors, alternators, starters, and other electrical components. They support repair work that requires replacement or restoration of electrical coils.'),
(6, 'Starter & Motor Parts', 'N/A', 'Starter Armatures', 'Starter-related component used for starter motor repair and replacement support.', 'N/A', 'N/A', 'piece', 250.00, 2, 4, 1, 0, 1, 1, 100.00, 'part-1777313026-afdb8678.png', 'Starter armatures are internal starter motor components that help produce rotational force during engine starting. They are used when repairing or replacing damaged starter motor parts.'),
(7, 'Steering & Suspension', '555', 'Tie Rod End', 'Heavy-duty forged steel outer tie rod end replacement for Honda Civic 2006-2011. Restores steering precision.', 'Heavy duty steel', 'Honda Civic 2006-2011', 'pair', 1850.00, 3, 4, 3, 0, 1, 1, 1295.00, 'part-1778936839-053e2e1a.webp', 'Premium aftermarket outer tie rod end manufactured by 555. Engineered with heavy-duty forged steel to restore precise steering response, eliminate steering wheel play, and prevent uneven tire wear. Designed to withstand harsh road conditions and heavy impacts. This assembly includes a pre-greased ball joint, durable weather-resistant dust boot, castle nut, and cotter pin for a complete, direct-fit OEM replacement. Highly recommended for vehicles frequently driven on rough or uneven terrain.');

-- --------------------------------------------------------

--
-- Table structure for table `part_order`
--

CREATE TABLE `part_order` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `preferred_pickup_date` date DEFAULT NULL,
  `preferred_pickup_time` time DEFAULT NULL,
  `pickup_notes` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `gcash_reference` varchar(100) DEFAULT NULL,
  `gcash_payment_amount` decimal(10,2) DEFAULT NULL,
  `gcash_verification_status` varchar(50) DEFAULT NULL,
  `gcash_verified_at` datetime DEFAULT NULL,
  `gcash_verified_by` int(11) DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Approved','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_order`
--

INSERT INTO `part_order` (`order_id`, `customer_id`, `order_date`, `preferred_pickup_date`, `preferred_pickup_time`, `pickup_notes`, `payment_method`, `gcash_reference`, `gcash_payment_amount`, `gcash_verification_status`, `gcash_verified_at`, `gcash_verified_by`, `payment_notes`, `total_amount`, `status`) VALUES
(1, 1, '2026-04-28 21:49:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 500.00, 'Completed'),
(2, 1, '2026-04-28 22:09:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 200.00, 'Completed'),
(3, 1, '2026-04-30 00:24:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 600.00, 'Cancelled'),
(4, 1, '2026-04-30 00:33:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 500.00, 'Completed'),
(5, 1, '2026-04-30 02:41:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 200.00, 'Completed'),
(6, 1, '2026-04-30 02:45:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 300.00, 'Completed'),
(7, 1, '2026-04-30 02:52:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 700.00, 'Completed'),
(8, 1, '2026-04-30 03:14:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 550.00, 'Pending'),
(9, 4, '2026-05-08 11:30:10', '2026-05-17', '12:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 250.00, 'Pending'),
(10, 4, '2026-05-08 11:48:45', '2026-05-18', '10:00:00', 'will pick up after work', 'GCash Reservation', '84824249234', NULL, 'Verified', '2026-05-08 12:16:35', 1, NULL, 200.00, 'Ready for Pickup'),
(11, 4, '2026-05-08 12:14:24', '2026-05-20', '11:00:00', NULL, 'GCash Reservation', '3283023092', NULL, 'Verified', '2026-05-08 12:28:15', 1, NULL, 500.00, 'Ready for Pickup'),
(12, 4, '2026-05-08 13:54:20', '2026-05-21', '09:00:00', NULL, 'GCash Down Payment', '404834399932', 100.00, 'Verified', '2026-05-08 14:27:11', 1, NULL, 200.00, 'Ready for Pickup'),
(13, 4, '2026-05-08 13:57:55', '2026-05-25', '13:00:00', NULL, 'GCash Down Payment', '030232083283', 149.98, 'Verified', '2026-05-08 14:07:26', 1, NULL, 200.00, 'Completed'),
(14, 4, '2026-05-08 14:24:06', '2026-06-01', '08:00:00', NULL, 'GCash Down Payment', '93284294374', 150.00, 'Verified', '2026-05-08 14:24:30', 1, NULL, 200.00, 'Ready for Pickup');

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
(10, 8, 6, 1, 250.00),
(11, 9, 6, 1, 250.00),
(12, 10, 4, 1, 200.00),
(13, 11, 3, 1, 500.00),
(14, 12, 4, 1, 200.00),
(15, 13, 4, 1, 200.00),
(16, 14, 4, 1, 200.00);

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
(10, 8, 1, 3000.00, 'Cash', '', 0, '2026-04-30 22:33:45', NULL),
(11, 7, 1, 5000.00, 'Cash', '', 0, '2026-05-03 20:23:30', NULL),
(12, 16, 1, 149.98, 'GCash', '030232083283', 0, '2026-05-08 14:07:59', NULL),
(13, 16, 1, 50.02, 'Cash', '', 0, '2026-05-08 14:18:56', NULL),
(14, 17, 1, 150.00, 'GCash', '93284294374', 0, '2026-05-08 14:24:56', NULL),
(15, 18, 1, 100.00, 'GCash', '404834399932', 0, '2026-05-08 14:27:21', NULL);

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
(8, 5, 4, 1, 200.00, 200.00),
(9, 6, 3, 1, 500.00, 500.00);

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
(5, NULL, 2, NULL, NULL, 1, '2026-04-30 09:59:25', 200.00, 'Completed', 'Cheque', '99838434'),
(6, NULL, 5, NULL, NULL, 1, '2026-05-07 21:48:08', 500.00, 'Completed', 'Cash', '');

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
(4, 'Engine Overhaul', 'Other Services', 3000.00, 0, 0, 'Checks and repairs major engine-related issues to restore performance and reliability.', 1, 'service-1777313350-5f9ca8af.png', 'Engine overhaul involves inspecting and repairing major engine components to help restore performance, reliability, and safe operation. Actual recommendations depend on the vehicle condition after inspection.\r\nFeatures:', 'Engine inspection\r\nRepair recommendations\r\nMechanical checking'),
(5, 'rtj', 'Rewinding Serivces', 3462.00, 1, 2, 'vsr', 1, '', 'sgr', 'srg');

-- --------------------------------------------------------

--
-- Table structure for table `service_request`
--

CREATE TABLE `service_request` (
  `service_request_id` int(11) NOT NULL,
  `request_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `concern_description` text NOT NULL,
  `preferred_appointment_date` date NOT NULL,
  `preferred_appointment_time` time DEFAULT NULL,
  `status` enum('Pending','Converted','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `assigned_mechanic_id` int(11) DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `converted_job_order_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_request`
--

INSERT INTO `service_request` (`service_request_id`, `request_number`, `customer_id`, `vehicle_id`, `service_id`, `concern_description`, `preferred_appointment_date`, `preferred_appointment_time`, `status`, `estimated_cost`, `assigned_mechanic_id`, `expected_completion_date`, `reviewed_by`, `reviewed_at`, `approved_at`, `rejected_at`, `rejection_reason`, `cancelled_at`, `converted_job_order_id`, `created_at`, `updated_at`) VALUES
(1, 'SR-846974', 4, 3, 4, 'leaking oil', '2026-05-10', '09:00:00', 'Converted', 3000.00, 3, NULL, 1, '2026-05-07 23:57:05', '2026-05-07 23:57:05', NULL, NULL, NULL, 11, '2026-05-07 23:47:04', '2026-05-07 23:57:05'),
(2, 'SR-B4F8B6', 4, 3, 1, 'change battery', '2026-05-15', '10:00:00', 'Converted', 900.00, 3, NULL, 1, '2026-05-08 00:40:23', '2026-05-08 00:40:23', NULL, NULL, NULL, 12, '2026-05-08 00:16:43', '2026-05-08 00:40:23');

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
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `is_active`) VALUES
(1, 'test', 'Lav.lee', 'leelav.viin@gmail.com', '91435544', '', 1),
(2, 'dalig', 'Lav ash Ash', 'leela090104@gmail.com', '12341234', 'baguio', 1),
(3, 'Castroil Car Workshop', 'Lav ash Ash', '20236455@s.ubaguio.edu', '09260432900', 'La Union', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(20) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Owner','Cashier','Head Mechanic','Customer') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `first_name`, `middle_name`, `last_name`, `username`, `password_hash`, `role`, `is_active`, `is_verified`, `verification_token`, `created_at`, `email`) VALUES
(1, 'Admin', NULL, 'Owner', 'owner', 'password123', 'Owner', 1, 1, NULL, '2026-04-25 12:45:00', 'owner@norilys.com'),
(2, 'Jane', NULL, 'Cashier', 'cashier', 'password123', 'Cashier', 1, 1, NULL, '2026-04-25 12:45:00', 'cashier@norilys.com'),
(3, 'Mike', NULL, 'Mechanic', 'mechanic', 'password123', 'Head Mechanic', 1, 1, NULL, '2026-04-25 12:45:00', 'mechanic@norilys.com'),
(4, 'John', NULL, 'Doe', 'customer', 'password123', 'Customer', 1, 1, NULL, '2026-04-25 12:45:00', 'customer@norilys.com'),
(5, 'Jennie', NULL, 'Batumbakal', 'jenjen@gmail.com', '$2y$10$8sBOpPTeqDxRJ.9ST8vfJOWXWnLvkuSTAFxL10On5XhMrIKcYL4.W', 'Customer', 1, 1, NULL, '2026-04-30 00:53:51', 'jenjen@gmail.com'),
(6, 'Lisa', NULL, 'Manoban', 'lisamanoban@gmail.co', '$2y$10$vuqV5LRcDPNyisFzDmwPIOcqTJbedUO6QY286aDdJzDDDnlZKrHl.', 'Customer', 1, 1, NULL, '2026-05-07 21:44:50', 'lisamanoban@gmail.com'),
(15, 'avi', NULL, 'ien', 'leelavinagustin@gmail.com', '$2y$10$U1p6mNBgWvDbGDzbhExrYeJvLC3o3GyVqSCNJkexevPa4dD4sVHgW', 'Customer', 1, 1, NULL, '2026-05-16 17:16:42', 'leelavinagustin@gmail.com'),
(16, 'Nicole', NULL, 'Atienza', 'nixatienza04@gmail.com', '$2y$10$.r520oBaRJbxQCMm6SbQ7ePPpGZJQ7OkO0jKGL8pDdVkzuYWmfPOm', 'Customer', 1, 0, '12d21350719ed8ed385538f2b570d71d', '2026-05-16 20:10:40', 'nixatienza04@gmail.com');

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
(2, 2, '162712', NULL, 'Porsche', 'Taycan', '2020', 'Blue', NULL, 'Active', NULL, NULL, '2026-04-30 14:20:23'),
(3, 4, '160527', NULL, 'Toyota', 'Taycan', '2018', 'Green', NULL, 'Active', NULL, NULL, '2026-05-07 21:51:06'),
(4, 14, 'VAYUDRD', NULL, 'Rolls Royce', 'Dawn', '2021', 'Red', NULL, 'Active', NULL, NULL, '2026-05-16 16:37:29'),
(5, 17, 'VAYUDRDAWE', NULL, 'Rolls Royce', 'Dawn', '2021', 'Red', NULL, 'Active', NULL, NULL, '2026-05-16 21:09:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `idx_customer_contact` (`contact_number`),
  ADD KEY `fk_customer_user` (`user_id`),
  ADD KEY `idx_customer_user_status` (`user_id`,`status`);

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
  ADD KEY `idx_job_order_customer_status` (`customer_id`,`status`),
  ADD KEY `fk_assigned_mechanic_jo` (`assigned_mechanic_id`),
  ADD KEY `fk_completed_by_jo` (`completed_by`),
  ADD KEY `idx_job_order_source_status` (`request_source`,`status`),
  ADD KEY `idx_job_order_mechanic_status` (`assigned_mechanic_id`,`status`);

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
  ADD PRIMARY KEY (`part_id`),
  ADD KEY `fk_part_supplier` (`primary_supplier_id`);

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
-- Indexes for table `service_request`
--
ALTER TABLE `service_request`
  ADD PRIMARY KEY (`service_request_id`),
  ADD UNIQUE KEY `idx_service_request_number` (`request_number`),
  ADD KEY `idx_service_request_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_service_request_status_date` (`status`,`created_at`),
  ADD KEY `idx_service_request_vehicle` (`vehicle_id`),
  ADD KEY `idx_service_request_service` (`service_id`),
  ADD KEY `idx_service_request_mechanic` (`assigned_mechanic_id`),
  ADD KEY `idx_service_request_reviewer` (`reviewed_by`),
  ADD KEY `idx_service_request_job_order` (`converted_job_order_id`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`stock_in_id`),
  ADD KEY `fk_part_id_si` (`part_id`),
  ADD KEY `fk_recorded_by_si` (`recorded_by`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `idx_user_username` (`username`),
  ADD UNIQUE KEY `idx_user_email` (`email`);

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
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `job_order`
--
ALTER TABLE `job_order`
  MODIFY `job_order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `job_order_part`
--
ALTER TABLE `job_order_part`
  MODIFY `job_part_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `job_order_service`
--
ALTER TABLE `job_order_service`
  MODIFY `job_service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `part`
--
ALTER TABLE `part`
  MODIFY `part_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `part_order`
--
ALTER TABLE `part_order`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `part_order_item`
--
ALTER TABLE `part_order_item`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pos_item`
--
ALTER TABLE `pos_item`
  MODIFY `pos_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pos_transaction`
--
ALTER TABLE `pos_transaction`
  MODIFY `pos_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service`
--
ALTER TABLE `service`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_request`
--
ALTER TABLE `service_request`
  MODIFY `service_request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `stock_in_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  ADD CONSTRAINT `fk_assigned_mechanic_jo` FOREIGN KEY (`assigned_mechanic_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_completed_by_jo` FOREIGN KEY (`completed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL,
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
-- Constraints for table `part`
--
ALTER TABLE `part`
  ADD CONSTRAINT `fk_part_supplier` FOREIGN KEY (`primary_supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE SET NULL;

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
-- Constraints for table `service_request`
--
ALTER TABLE `service_request`
  ADD CONSTRAINT `fk_sr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sr_job_order` FOREIGN KEY (`converted_job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sr_mechanic` FOREIGN KEY (`assigned_mechanic_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sr_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sr_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sr_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`) ON UPDATE CASCADE;

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
