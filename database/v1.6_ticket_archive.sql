-- Soft-archive resolved tickets (kept for Performance until retention disposal).
-- Safe to re-run: skips if column already exists.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tickets'
    AND COLUMN_NAME = 'archived_at'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `tickets` ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `resolved_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: existing resolved tickets leave active technician queues.
UPDATE `tickets`
SET `archived_at` = IFNULL(`resolved_at`, NOW())
WHERE `status` = 'resolved'
  AND `archived_at` IS NULL;

-- Retention (enforced in PHP logic/ticket_retention.php):
--   rated archived resolved  >= 30 days  -> eligible for batch hard-delete
--   unrated archived resolved >= 60 days -> eligible for batch hard-delete
--   batch size <= 50 per run
