-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 04:06 PM
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
  `contact_number` varchar(20) NOT NULL,
  `address` varchar(200) NOT NULL,
  `credit_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credit_due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `job_order_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `invoice_type` enum('Service','Part') NOT NULL,
  `subtotal_services` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal_parts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Partial','Pending') NOT NULL DEFAULT 'Pending',
  `date_issued` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `is_voided` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `status` enum('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_completed` datetime DEFAULT NULL,
  `requires_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `down_payment_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `category` enum('Capacitor','Switch','AVR') NOT NULL,
  `part_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_on_hand` smallint(6) NOT NULL DEFAULT 0,
  `low_stock_threshold` smallint(6) NOT NULL DEFAULT 3,
  `is_low_stock` tinyint(1) NOT NULL DEFAULT 0,
  `supplier_reference` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `first_name` varchar(20) NOT NULL,
  `middle_name` varchar(20) DEFAULT NULL,
  `last_name` varchar(20) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Owner','Cashier','Head Mechanic') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `first_name`, `middle_name`, `last_name`, `username`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'Norily', 'B', 'Dagohoy', 'admin', '$2y$10$i.q/vw2eLMudYI5Oe1dcDO4V8679zf3fy56hXdX.qEwwO7LHjl4y6', 'Owner', 1, '2026-04-19 21:43:12');

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
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`invoice_id`),
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
  ADD UNIQUE KEY `idx_vehicle_vin` (`vin`),
  ADD KEY `fk_customer_id` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_order`
--
ALTER TABLE `job_order`
  MODIFY `job_order_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `part_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_item`
--
ALTER TABLE `pos_item`
  MODIFY `pos_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_transaction`
--
ALTER TABLE `pos_transaction`
  MODIFY `pos_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service`
--
ALTER TABLE `service`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `stock_in_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

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
