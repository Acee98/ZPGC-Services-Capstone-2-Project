

USE zpgc_services_db;

ALTER TABLE tickets
    MODIFY status ENUM(
        'pending',
        'ongoing',
        'processing',
        'awaiting_confirmation',
        'resolved'
    ) NOT NULL DEFAULT 'pending';

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'zpgc_services_db'
      AND TABLE_NAME   = 'tickets'
      AND COLUMN_NAME  = 'reopen_count'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tickets ADD COLUMN reopen_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER resolved_at',
    'SELECT "reopen_count already exists — skipped" AS note'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COLUMN_TYPE AS status_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'zpgc_services_db'
  AND TABLE_NAME   = 'tickets'
  AND COLUMN_NAME  = 'status';

SELECT status, COUNT(*) AS row_count
FROM tickets
GROUP BY status;
