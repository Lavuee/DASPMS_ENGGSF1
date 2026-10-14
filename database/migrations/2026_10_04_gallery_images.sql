-- ------------------------------------------------------------------
-- Extra photos per part and per service for the details page galleries.
-- part.image / service.image stay the main photo; these rows are the
-- additional angles, in sort_order. Safe to run more than once.
-- daspms.sql already includes these tables for fresh installs.
-- ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `part_image` (
  `part_image_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`part_image_id`),
  KEY `idx_part_image_part` (`part_id`, `sort_order`),
  CONSTRAINT `fk_part_image_part` FOREIGN KEY (`part_id`) REFERENCES `part` (`part_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `service_image` (
  `service_image_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_image_id`),
  KEY `idx_service_image_service` (`service_id`, `sort_order`),
  CONSTRAINT `fk_service_image_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
