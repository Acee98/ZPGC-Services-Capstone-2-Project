-- =====================================================================
-- ZPGC Services — User confirmation patch (v1.5)
-- =====================================================================
-- Run this ONCE in phpMyAdmin, AFTER patch_dashboard_v1_3.sql.
--
--   1. XAMPP: start Apache + MySQL
--   2. http://localhost/phpmyadmin
--   3. Select zpgc_services_db  ->  SQL tab
--   4. Paste this whole file  ->  Go
--
-- WHY
-- ---
-- Until now a technician could set a ticket to 'resolved' themselves.
-- That let a ticket be closed without the problem actually being fixed,
-- and the person who reported it had no say in it.
--
-- This patch adds one status, 'awaiting_confirmation', which sits
-- between the technician finishing and the ticket closing:
--
--   pending -> ongoing -> processing -> awaiting_confirmation -> resolved
--                                       ^                        ^
--                                       technician marks it       reporter
--                                       fixed and hands it back   confirms
--
-- A technician can no longer select 'resolved'. Only the reporter
-- confirming moves a ticket there, or an admin overriding for the case
-- where the reporter never responds.
--
-- If the reporter says it is NOT fixed, the ticket drops back to
-- 'ongoing' and their reason is posted into the ticket conversation.
--
-- Safe to run on a database that already has data. It does not drop or
-- empty anything, and it is safe to run twice.
-- =====================================================================

USE zpgc_services_db;

-- ---------------------------------------------------------------------
-- 1. Add the new status to the ENUM
-- ---------------------------------------------------------------------
-- Listed in workflow order so phpMyAdmin's dropdowns and any ORDER BY
-- on the column read sensibly. Existing rows are untouched: adding a
-- value to an ENUM does not rewrite the rows that already hold the
-- other values.
ALTER TABLE tickets
    MODIFY status ENUM(
        'pending',
        'ongoing',
        'processing',
        'awaiting_confirmation',
        'resolved'
    ) NOT NULL DEFAULT 'pending';

-- ---------------------------------------------------------------------
-- 2. Track how often a ticket bounced back
-- ---------------------------------------------------------------------
-- A ticket the reporter has rejected more than once is a signal worth
-- surfacing: either the diagnosis is wrong or the two sides are talking
-- past each other. Kept as a plain counter rather than a separate audit
-- table, since the reason for each rejection is already recorded as a
-- message in the ticket conversation.
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

-- ---------------------------------------------------------------------
-- 3. Verify
-- ---------------------------------------------------------------------
-- The first row should list awaiting_confirmation among the ENUM values.
SELECT COLUMN_TYPE AS status_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'zpgc_services_db'
  AND TABLE_NAME   = 'tickets'
  AND COLUMN_NAME  = 'status';

SELECT status, COUNT(*) AS row_count
FROM tickets
GROUP BY status;
