-- ------------------------------------------------------------------
-- Brings an existing `daspms` database in line with what the PHP code
-- writes. Safe to run more than once on MariaDB (XAMPP default), which
-- supports ADD COLUMN IF NOT EXISTS. daspms.sql already includes these
-- changes for fresh installs.
-- ------------------------------------------------------------------

-- POS walk-in customer details (models/POS.php, views/pos.php, views/print_pos_receipt.php)
ALTER TABLE `pos_transaction`
  ADD COLUMN IF NOT EXISTS `customer_type` enum('Walk-in','Registered') NOT NULL DEFAULT 'Walk-in' AFTER `customer_id`,
  ADD COLUMN IF NOT EXISTS `walkin_customer_name` varchar(150) DEFAULT NULL AFTER `customer_type`,
  ADD COLUMN IF NOT EXISTS `walkin_contact_number` varchar(30) DEFAULT NULL AFTER `walkin_customer_name`,
  ADD COLUMN IF NOT EXISTS `walkin_address` varchar(255) DEFAULT NULL AFTER `walkin_contact_number`;

UPDATE `pos_transaction` SET `customer_type` = 'Registered' WHERE `customer_id` IS NOT NULL;

-- "Bank Transfer" is offered in POS and Billing but was missing from the enums,
-- so those payments were rejected or saved with an empty method.
ALTER TABLE `pos_transaction`
  MODIFY `payment_method` enum('Cash','Bank','Bank Transfer','Cheque','GCash') NOT NULL;

ALTER TABLE `payment`
  MODIFY `payment_method` enum('Cash','Bank','Bank Transfer','Cheque','GCash') NOT NULL;

-- Who recorded a part used on a job order, and when (models/JobOrder.php, views/job_orders.php)
ALTER TABLE `job_order_part`
  ADD COLUMN IF NOT EXISTS `used_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `used_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL;

-- Generated numbers such as INV-PART-20260508-142721-14 are longer than 20
-- characters and were being truncated (producing duplicate invoice numbers).
ALTER TABLE `invoice`
  MODIFY `invoice_number` varchar(40) NOT NULL;
