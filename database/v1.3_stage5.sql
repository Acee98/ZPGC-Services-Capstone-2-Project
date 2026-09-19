-- Stage 5 / v1.3 — optional SQL copy of the Structure click path.
-- Prefer phpMyAdmin Structure (not SQL tab) unless Save fails.

-- tickets.priority: ENUM critical | moderate | low, NULL until assigned on the board.
-- ALTER TABLE `tickets`
--   ADD `priority` ENUM('critical','moderate','low') NULL DEFAULT NULL AFTER `status`;
