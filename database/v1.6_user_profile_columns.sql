
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `users` ADD COLUMN `email_notify` TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone`;
ALTER TABLE `users` ADD COLUMN `sms_notify` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_notify`;
ALTER TABLE `users` ADD COLUMN `preferred_language` VARCHAR(30) NOT NULL DEFAULT 'English' AFTER `sms_notify`;
