-- Customer satisfaction (CSAT) on resolved tickets.
-- Safe to re-run: skips if column already exists.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tickets'
    AND COLUMN_NAME = 'satisfaction'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `tickets` ADD COLUMN `satisfaction` TINYINT NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
