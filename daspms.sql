-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: daspms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL = done automatically by the system',
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `entity_ref` varchar(60) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_entity` (`entity_type`,`entity_id`),
  KEY `idx_activity_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_item`
--

DROP TABLE IF EXISTS `cart_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_item` (
  `customer_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity` smallint(6) NOT NULL DEFAULT 1,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`,`part_id`),
  KEY `idx_cart_item_part` (`part_id`),
  CONSTRAINT `fk_cart_item_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_item_part` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_item`
--

LOCK TABLES `cart_item` WRITE;
/*!40000 ALTER TABLE `cart_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `idx_customer_contact` (`contact_number`),
  KEY `fk_customer_user` (`user_id`),
  KEY `idx_customer_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer`
--

LOCK TABLES `customer` WRITE;
/*!40000 ALTER TABLE `customer` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice`
--

DROP TABLE IF EXISTS `invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `job_order_id` int(11) DEFAULT NULL,
  `part_order_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `invoice_number` varchar(40) NOT NULL,
  `invoice_type` enum('Service','Part') NOT NULL,
  `subtotal_services` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal_parts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Partial','Not Paid') NOT NULL DEFAULT 'Not Paid',
  `date_issued` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `is_voided` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `unique_part_order_invoice` (`part_order_id`),
  KEY `fk_customer_id_iv` (`customer_id`),
  KEY `fk_job_order_id_iv` (`job_order_id`),
  KEY `fk_created_by_iv` (`created_by`),
  KEY `idx_invoice_status_due` (`payment_status`,`due_date`),
  KEY `invoice_number` (`invoice_number`),
  CONSTRAINT `fk_created_by_iv` FOREIGN KEY (`created_by`) REFERENCES `user` (`user_id`),
  CONSTRAINT `fk_customer_id_iv` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `fk_job_order_id_iv` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice`
--

LOCK TABLES `invoice` WRITE;
/*!40000 ALTER TABLE `invoice` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_order`
--

DROP TABLE IF EXISTS `job_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_order` (
  `job_order_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `cancelled_by` int(11) DEFAULT NULL,
  `requires_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `down_payment_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`job_order_id`),
  KEY `fk_vehicle_id_jo` (`vehicle_id`),
  KEY `fk_customer_id_jo` (`customer_id`),
  KEY `fk_created_by_jo` (`created_by`),
  KEY `idx_job_order_customer_status` (`customer_id`,`status`),
  KEY `fk_assigned_mechanic_jo` (`assigned_mechanic_id`),
  KEY `fk_completed_by_jo` (`completed_by`),
  KEY `idx_job_order_source_status` (`request_source`,`status`),
  KEY `idx_job_order_mechanic_status` (`assigned_mechanic_id`,`status`),
  CONSTRAINT `fk_assigned_mechanic_jo` FOREIGN KEY (`assigned_mechanic_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_completed_by_jo` FOREIGN KEY (`completed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_created_by_jo` FOREIGN KEY (`created_by`) REFERENCES `user` (`user_id`),
  CONSTRAINT `fk_customer_id_jo` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `fk_vehicle_id_jo` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_order`
--

LOCK TABLES `job_order` WRITE;
/*!40000 ALTER TABLE `job_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_order_part`
--

DROP TABLE IF EXISTS `job_order_part`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_order_part` (
  `job_part_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_order_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity_used` smallint(6) NOT NULL,
  `unit_price_at_use` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `used_by` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`job_part_id`),
  KEY `fk_job_order_id_jop` (`job_order_id`),
  KEY `fk_part_id_jop` (`part_id`),
  CONSTRAINT `fk_job_order_id_jop` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_part_id_jop` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_order_part`
--

LOCK TABLES `job_order_part` WRITE;
/*!40000 ALTER TABLE `job_order_part` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_order_part` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_order_service`
--

DROP TABLE IF EXISTS `job_order_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_order_service` (
  `job_service_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_order_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` smallint(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`job_service_id`),
  KEY `fk_job_order_id_jos` (`job_order_id`),
  KEY `fk_service_id_jos` (`service_id`),
  CONSTRAINT `fk_job_order_id_jos` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_service_id_jos` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_order_service`
--

LOCK TABLES `job_order_service` WRITE;
/*!40000 ALTER TABLE `job_order_service` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_order_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `part`
--

DROP TABLE IF EXISTS `part`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part` (
  `part_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `full_description` text DEFAULT NULL,
  PRIMARY KEY (`part_id`),
  KEY `fk_part_supplier` (`primary_supplier_id`),
  CONSTRAINT `fk_part_supplier` FOREIGN KEY (`primary_supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `part`
--

LOCK TABLES `part` WRITE;
/*!40000 ALTER TABLE `part` DISABLE KEYS */;
INSERT INTO `part` VALUES (3,'Electrical Parts & Supplies','Generic','Capacitors','Common electrical supply used for motor, wiring, and repair support.','Motor-start / run capacitor, 12V DC','Starter, alternator and blower motor repairs, most vehicles','piece',500.00,12,4,3,0,3,1,300.00,'part-capacitors.png','Capacitors are electrical components commonly used to store and release electrical energy. In auto repair and electrical work, they may support motor operation, wiring repair, and other electrical maintenance needs.');
INSERT INTO `part` VALUES (4,'Electrical Parts & Supplies','Generic','Switches','Selected switches used for automotive and electrical repair needs.','12V DC rocker / toggle switch, 2-pin or 3-pin','Lights, horn, fog lamps and accessories, most vehicles','piece',200.00,15,5,3,0,3,1,100.00,'part-switches.png','Automotive switches are used to control electrical circuits in vehicles. They may be used for lights, accessories, wipers, auxiliary controls, and other repair or replacement needs.');
INSERT INTO `part` VALUES (5,'Electrical Parts & Supplies','Generic','Magnetic Wires','Supply item used for rewinding and related electrical repair work.','Enamel-coated copper wire, sold by gauge (SWG)','Rewinding of starters, alternators and electric motors','roll',300.00,3,4,1,0,1,1,150.00,'part-magnetic-wires.png','Magnetic wires are commonly used for rewinding motors, alternators, starters, and other electrical components. They support repair work that requires replacement or restoration of electrical coils.');
INSERT INTO `part` VALUES (6,'Starter & Motor Parts','Generic','Starter Armatures','Starter-related component used for starter motor repair and replacement support.','Replacement starter armature, sized per starter model','Toyota, Mitsubishi, Isuzu and similar starter motors; confirm the model at the shop','piece',250.00,2,3,1,0,1,1,100.00,'part-starter-armatures.png','Starter armatures are internal starter motor components that help produce rotational force during engine starting. They are used when repairing or replacing damaged starter motor parts. Bring the old armature or the vehicle model so the shop can match the size.');
INSERT INTO `part` VALUES (7,'Steering & Suspension','555','Tie Rod End','Heavy-duty forged steel outer tie rod end replacement for Honda Civic 2006-2011. Restores steering precision.','Heavy duty steel','Honda Civic 2006-2011','pair',1850.00,6,2,2,0,2,1,1295.00,'part-tie-rod-end.png','Premium aftermarket outer tie rod end manufactured by 555. Engineered with heavy-duty forged steel to restore precise steering response, eliminate steering wheel play, and prevent uneven tire wear. Designed to withstand harsh road conditions and heavy impacts. This assembly includes a pre-greased ball joint, durable weather-resistant dust boot, castle nut, and cotter pin for a complete, direct-fit OEM replacement. Highly recommended for vehicles frequently driven on rough or uneven terrain.');
/*!40000 ALTER TABLE `part` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `part_image`
--

DROP TABLE IF EXISTS `part_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part_image` (
  `part_image_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`part_image_id`),
  KEY `idx_part_image_part` (`part_id`,`sort_order`),
  CONSTRAINT `fk_part_image_part` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `part_image`
--

LOCK TABLES `part_image` WRITE;
/*!40000 ALTER TABLE `part_image` DISABLE KEYS */;
INSERT INTO `part_image` VALUES (1,3,'part-capacitors-2.png',1,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (2,3,'part-capacitors-3.png',2,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (3,4,'part-switches-2.png',1,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (4,4,'part-switches-3.png',2,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (5,5,'part-magnetic-wires-2.png',1,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (6,6,'part-starter-armatures-2.png',1,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (7,6,'part-starter-armatures-3.png',2,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (8,7,'part-tie-rod-end-2.png',1,'2026-10-05 12:00:00');
INSERT INTO `part_image` VALUES (9,7,'part-tie-rod-end-3.png',2,'2026-10-05 12:00:00');
/*!40000 ALTER TABLE `part_image` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `part_order`
--

DROP TABLE IF EXISTS `part_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part_order` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `status` enum('Pending','Approved','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `part_order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `part_order`
--

LOCK TABLES `part_order` WRITE;
/*!40000 ALTER TABLE `part_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `part_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `part_order_item`
--

DROP TABLE IF EXISTS `part_order_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part_order_item` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity` smallint(6) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `part_id` (`part_id`),
  CONSTRAINT `part_order_item_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `part_order` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `part_order_item_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `part_order_item`
--

LOCK TABLES `part_order_item` WRITE;
/*!40000 ALTER TABLE `part_order_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `part_order_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset`
--

DROP TABLE IF EXISTS `password_reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `idx_password_reset_token` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset`
--

LOCK TABLES `password_reset` WRITE;
/*!40000 ALTER TABLE `password_reset` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Bank','Bank Transfer','Cheque','GCash') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `is_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `fk_invoice_id_pm` (`invoice_id`),
  KEY `fk_received_by_pm` (`received_by`),
  CONSTRAINT `fk_invoice_id_pm` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`),
  CONSTRAINT `fk_received_by_pm` FOREIGN KEY (`received_by`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transaction`
--

DROP TABLE IF EXISTS `payment_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_transaction` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `gateway` varchar(30) NOT NULL DEFAULT 'paymongo',
  `purpose` enum('Down Payment','Full Payment') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PHP',
  `checkout_session_id` varchar(100) DEFAULT NULL,
  `payment_intent_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `checkout_url` text DEFAULT NULL,
  `status` enum('Pending','Paid','Failed','Expired','Cancelled') NOT NULL DEFAULT 'Pending',
  `livemode` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `idx_payment_checkout_session` (`checkout_session_id`),
  KEY `idx_payment_order` (`part_order_id`),
  KEY `idx_payment_intent` (`payment_intent_id`),
  KEY `fk_payment_customer` (`customer_id`),
  CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`part_order_id`) REFERENCES `part_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transaction`
--

LOCK TABLES `payment_transaction` WRITE;
/*!40000 ALTER TABLE `payment_transaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_transaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_webhook_event`
--

DROP TABLE IF EXISTS `payment_webhook_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_webhook_event` (
  `event_id` varchar(100) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `result` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_webhook_event`
--

LOCK TABLES `payment_webhook_event` WRITE;
/*!40000 ALTER TABLE `payment_webhook_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_webhook_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_item`
--

DROP TABLE IF EXISTS `pos_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_item` (
  `pos_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `pos_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity_sold` smallint(6) NOT NULL,
  `unit_price_at_sale` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`pos_item_id`),
  KEY `fk_pos_id_pi` (`pos_id`),
  KEY `fk_part_id_pi` (`part_id`),
  CONSTRAINT `fk_part_id_pi` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`),
  CONSTRAINT `fk_pos_id_pi` FOREIGN KEY (`pos_id`) REFERENCES `pos_transaction` (`pos_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_item`
--

LOCK TABLES `pos_item` WRITE;
/*!40000 ALTER TABLE `pos_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `pos_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_transaction`
--

DROP TABLE IF EXISTS `pos_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_transaction` (
  `pos_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_type` enum('Walk-in','Registered') NOT NULL DEFAULT 'Walk-in',
  `walkin_customer_name` varchar(150) DEFAULT NULL,
  `walkin_contact_number` varchar(30) DEFAULT NULL,
  `walkin_address` varchar(255) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `mileage` int(10) unsigned DEFAULT NULL,
  `processed_by` int(11) NOT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Completed',
  `payment_method` enum('Cash','Bank','Bank Transfer','Cheque','GCash') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`pos_id`),
  KEY `fk_invoice_id_pt` (`invoice_id`),
  KEY `fk_customer_id_pt` (`customer_id`),
  KEY `fk_processed_by_pt` (`processed_by`),
  KEY `fk_vehicle_id_pt` (`vehicle_id`),
  CONSTRAINT `fk_customer_id_pt` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `fk_invoice_id_pt` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`),
  CONSTRAINT `fk_processed_by_pt` FOREIGN KEY (`processed_by`) REFERENCES `user` (`user_id`),
  CONSTRAINT `fk_vehicle_id_pt` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_transaction`
--

LOCK TABLES `pos_transaction` WRITE;
/*!40000 ALTER TABLE `pos_transaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `pos_transaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit`
--

DROP TABLE IF EXISTS `rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit` (
  `rl_key` varchar(191) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`rl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit`
--

LOCK TABLES `rate_limit` WRITE;
/*!40000 ALTER TABLE `rate_limit` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service`
--

DROP TABLE IF EXISTS `service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requires_down_payment` tinyint(1) NOT NULL DEFAULT 0,
  `warranty_days` smallint(6) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  PRIMARY KEY (`service_id`),
  KEY `idx_service_category_active` (`category`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service`
--

LOCK TABLES `service` WRITE;
/*!40000 ALTER TABLE `service` DISABLE KEYS */;
INSERT INTO `service` VALUES (1,'Alternator Repair','Rewinding Services',900.00,0,0,'Checks and repairs alternators to help ensure the vehicle battery charges properly while the engine is running.',1,'service-alternator-repair.jpg','Alternator repair helps restore proper charging performance for the vehicle battery and electrical system. This service may include inspection, diagnosis, component repair, and testing to ensure the alternator is working correctly.','Inspection\r\nAlternator component repair\r\nPost-maintenance testing');
INSERT INTO `service` VALUES (2,'Starter Repair','Rewinding Services',1500.00,0,0,'Resolves starter-related problems that prevent the engine from starting properly.',1,'service-starter-repair.jpg','Starter repair focuses on diagnosing and fixing starter motor problems that may prevent the engine from starting. The service may include inspection, replacement of faulty parts, cleaning, repair, and final function testing.','Starter inspection\r\nRepair or replacement of faulty parts\r\nFunction testing');
INSERT INTO `service` VALUES (3,'Radiator Repair','Rewinding Services',3000.00,0,0,'Handles radiator leaks and overheating issues to help maintain stable engine temperature.',1,'service-radiator-repair.jpg','Radiator repair helps address cooling system issues such as leaks, overheating, and reduced cooling performance. The service may include leak detection, repair, and cooling performance checks.','Leak detection\r\nRadiator repair\r\nCooling performance check');
INSERT INTO `service` VALUES (4,'Engine Overhaul','Other Services',3000.00,0,0,'Checks and repairs major engine-related issues to restore performance and reliability.',1,'service-engine-overhaul.jpg','Engine overhaul involves inspecting and repairing major engine components to help restore performance, reliability, and safe operation. Actual recommendations depend on the vehicle condition after inspection.\r\nFeatures:','Engine inspection\r\nRepair recommendations\r\nMechanical checking');
/*!40000 ALTER TABLE `service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_image`
--

DROP TABLE IF EXISTS `service_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_image` (
  `service_image_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_image_id`),
  KEY `idx_service_image_service` (`service_id`,`sort_order`),
  CONSTRAINT `fk_service_image_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_image`
--

LOCK TABLES `service_image` WRITE;
/*!40000 ALTER TABLE `service_image` DISABLE KEYS */;
INSERT INTO `service_image` VALUES (1,1,'service-alternator-repair-2.jpg',1,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (2,1,'service-alternator-repair-3.jpg',2,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (3,2,'service-starter-repair-2.jpg',1,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (4,2,'service-starter-repair-3.jpg',2,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (5,3,'service-radiator-repair-2.jpg',1,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (6,3,'service-radiator-repair-3.jpg',2,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (7,4,'service-engine-overhaul-2.jpg',1,'2026-10-05 12:00:00');
INSERT INTO `service_image` VALUES (8,4,'service-engine-overhaul-3.jpg',2,'2026-10-05 12:00:00');
/*!40000 ALTER TABLE `service_image` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_request`
--

DROP TABLE IF EXISTS `service_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_request` (
  `service_request_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`service_request_id`),
  UNIQUE KEY `idx_service_request_number` (`request_number`),
  KEY `idx_service_request_customer_status` (`customer_id`,`status`),
  KEY `idx_service_request_status_date` (`status`,`created_at`),
  KEY `idx_service_request_vehicle` (`vehicle_id`),
  KEY `idx_service_request_service` (`service_id`),
  KEY `idx_service_request_mechanic` (`assigned_mechanic_id`),
  KEY `idx_service_request_reviewer` (`reviewed_by`),
  KEY `idx_service_request_job_order` (`converted_job_order_id`),
  CONSTRAINT `fk_sr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_job_order` FOREIGN KEY (`converted_job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_mechanic` FOREIGN KEY (`assigned_mechanic_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicle` (`vehicle_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_request`
--

LOCK TABLES `service_request` WRITE;
/*!40000 ALTER TABLE `service_request` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_request` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_in`
--

DROP TABLE IF EXISTS `stock_in`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_in` (
  `stock_in_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `quantity_added` smallint(6) NOT NULL,
  `cost_per_unit` decimal(10,2) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `supplier_document_ref` varchar(50) NOT NULL,
  `date_received` date NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`stock_in_id`),
  KEY `fk_part_id_si` (`part_id`),
  KEY `fk_recorded_by_si` (`recorded_by`),
  CONSTRAINT `fk_part_id_si` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`),
  CONSTRAINT `fk_recorded_by_si` FOREIGN KEY (`recorded_by`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_in`
--

LOCK TABLES `stock_in` WRITE;
/*!40000 ALTER TABLE `stock_in` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_in` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier`
--

LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
INSERT INTO `supplier` VALUES (1,'Ilocos Auto Electrical Supply','Lorna Agustin','leelav.viin@gmail.com','09171230001','San Fernando City, La Union',1);
INSERT INTO `supplier` VALUES (2,'Northern Luzon Motor Parts','Danilo Cruz','leela090104@gmail.com','09281230002','Baguio City, Benguet',1);
INSERT INTO `supplier` VALUES (3,'La Union Auto Parts Trading','Celia Ramos','20236455@s.ubaguio.edu','09391230003','Agoo, La Union',1);
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(20) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `role` enum('Owner','Cashier','Head Mechanic','Customer') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_code` varchar(64) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `idx_user_username` (`username`),
  UNIQUE KEY `idx_user_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'Admin',NULL,'Owner','owner','$2y$10$t60Q5eTSQrJh0468lRZWXup38MgYxRQSILv5K27ZOk3BH/jN5oDw2','2026-10-03 00:11:56','Owner',1,1,NULL,NULL,'2026-04-25 12:45:00','2026-04-25 12:45:00','leelav.viin@gmail.com','2026-10-03 00:12:56');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle`
--

DROP TABLE IF EXISTS `vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `idx_vehicle_plate` (`plate_number`),
  UNIQUE KEY `unique_plate_number` (`plate_number`),
  UNIQUE KEY `idx_vehicle_vin` (`vin`),
  KEY `fk_customer_id` (`customer_id`),
  CONSTRAINT `fk_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle`
--

LOCK TABLES `vehicle` WRITE;
/*!40000 ALTER TABLE `vehicle` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
