
ALTER TABLE `tickets` ADD COLUMN `urgency` TINYINT NULL DEFAULT NULL AFTER `ai_method`;
ALTER TABLE `tickets` ADD COLUMN `impact_level` TINYINT NULL DEFAULT NULL AFTER `urgency`;
ALTER TABLE `tickets` ADD COLUMN `severity_score` INT NULL DEFAULT NULL AFTER `impact_level`;
