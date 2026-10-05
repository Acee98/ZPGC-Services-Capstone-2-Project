
ALTER TABLE `tickets` ADD COLUMN `responded_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `tickets` ADD COLUMN `resolved_at` DATETIME NULL DEFAULT NULL AFTER `responded_at`;
