-- ------------------------------------------------------------------
-- Email verification by 6-digit code.
-- The verification email now carries a code as well as the link, so a user
-- who reads the email on another device (where a localhost link cannot open)
-- can type the code on the site instead. Only a hash of the code is stored.
-- Safe to run more than once. daspms.sql includes this column for fresh installs.
-- ------------------------------------------------------------------

ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `verification_code` varchar(64) DEFAULT NULL AFTER `verification_token`;
