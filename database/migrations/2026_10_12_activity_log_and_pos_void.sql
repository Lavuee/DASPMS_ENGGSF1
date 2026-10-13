-- ------------------------------------------------------------------
-- SRS fixes 7 and 8.
-- 7: activity_log keeps a record of important actions (cancellations, voids,
--    payments, archiving, reservation expiry): who, when, what.
-- 8: an Owner can void a POS sale; the sale keeps its number and stores
--    who voided it, when and why.
-- Safe to run more than once. daspms.sql includes these for fresh installs.
-- ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `activity_log` (
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
  KEY `idx_activity_entity` (`entity_type`, `entity_id`),
  KEY `idx_activity_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `pos_transaction`
  ADD COLUMN IF NOT EXISTS `void_reason` text DEFAULT NULL AFTER `reference_number`,
  ADD COLUMN IF NOT EXISTS `voided_at` datetime DEFAULT NULL AFTER `void_reason`,
  ADD COLUMN IF NOT EXISTS `voided_by` int(11) DEFAULT NULL AFTER `voided_at`;
