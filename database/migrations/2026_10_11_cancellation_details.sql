-- ------------------------------------------------------------------
-- SRS UC16: every cancellation keeps its reason, time and the user who did it.
-- job_order already had cancellation_reason / cancelled_at; this adds who
-- cancelled it, and the same three fields for web (part) orders.
-- Safe to run more than once. daspms.sql includes these columns for fresh installs.
-- ------------------------------------------------------------------

ALTER TABLE `job_order`
  ADD COLUMN IF NOT EXISTS `cancelled_by` int(11) DEFAULT NULL AFTER `cancelled_at`;

ALTER TABLE `part_order`
  ADD COLUMN IF NOT EXISTS `cancellation_reason` text DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `cancelled_at` datetime DEFAULT NULL AFTER `cancellation_reason`,
  ADD COLUMN IF NOT EXISTS `cancelled_by` int(11) DEFAULT NULL AFTER `cancelled_at`;
