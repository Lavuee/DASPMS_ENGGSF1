-- ------------------------------------------------------------------
-- Authentication hardening, password reset, rate limiting and
-- PayMongo (GCash) online payments. Safe to run more than once on MariaDB.
-- ------------------------------------------------------------------

-- Email verification now stores a SHA-256 hash of the token plus an expiry.
ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `verification_expires_at` datetime DEFAULT NULL AFTER `verification_token`,
  ADD COLUMN IF NOT EXISTS `email_verified_at` datetime DEFAULT NULL AFTER `verification_expires_at`,
  -- Sessions that started before this moment are ended (e.g. after a password reset)
  ADD COLUMN IF NOT EXISTS `password_changed_at` datetime DEFAULT NULL AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `last_login_at` datetime DEFAULT NULL;

-- Old raw (unhashed, non-expiring) verification tokens can no longer be used;
-- affected users request a new link from the login page.
UPDATE `user`
SET `verification_token` = NULL
WHERE `is_verified` = 0 AND `verification_expires_at` IS NULL AND `verification_token` IS NOT NULL;

UPDATE `user` SET `email_verified_at` = `created_at` WHERE `is_verified` = 1 AND `email_verified_at` IS NULL;

-- Single-use, expiring password reset tokens (only the hash is stored).
CREATE TABLE IF NOT EXISTS `password_reset` (
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

-- Attempt counters for login / reset / resend / registration throttling.
CREATE TABLE IF NOT EXISTS `rate_limit` (
  `rl_key` varchar(191) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`rl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per PayMongo checkout session created for a part order.
-- No GCash credentials or account details are stored: only gateway ids,
-- the amount and the status confirmed by PayMongo's API.
CREATE TABLE IF NOT EXISTS `payment_transaction` (
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
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`part_order_id`) REFERENCES `part_order` (`order_id`),
  CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Processed webhook event ids (replay/duplicate protection).
CREATE TABLE IF NOT EXISTS `payment_webhook_event` (
  `event_id` varchar(100) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `result` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
