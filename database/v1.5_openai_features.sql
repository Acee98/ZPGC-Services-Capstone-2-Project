-- Stage 7+ OpenAI features (phpMyAdmin → users_db → SQL)
-- Run once. Skip any ALTER that errors with "Duplicate column".

ALTER TABLE `tickets`
  ADD COLUMN `ai_guidance` TEXT NULL DEFAULT NULL AFTER `priority`;

ALTER TABLE `tickets`
  ADD COLUMN `ai_method` VARCHAR(32) NULL DEFAULT NULL AFTER `ai_guidance`;

ALTER TABLE `tickets`
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `ai_method`;
