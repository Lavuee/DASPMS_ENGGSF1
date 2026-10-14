-- ------------------------------------------------------------------
-- Saved customer cart. The cart used to live only in the PHP session,
-- so logging out emptied it. These rows keep it per customer account:
-- written on every cart change (includes/cart_store.php) and loaded
-- back into the session when the customer logs in.
-- Safe to run more than once. daspms.sql includes this table for fresh installs.
-- ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cart_item` (
  `customer_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity` smallint(6) NOT NULL DEFAULT 1,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`, `part_id`),
  KEY `idx_cart_item_part` (`part_id`),
  CONSTRAINT `fk_cart_item_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_item_part` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
