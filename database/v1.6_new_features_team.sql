-- ZPGC Services CP2_V1.6 — new features for teammate databases
-- Database: users_db
-- In phpMyAdmin: select users_db → SQL tab → paste → Go
-- If a line says "Duplicate column" or "already exists", skip that line and continue.

-- ========== Email verification + Forgot Password ==========
ALTER TABLE `users`
  ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

UPDATE `users` SET `email_verified` = 1 WHERE `status` = 'active';

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `purpose` VARCHAR(32) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auth_token_hash` (`token_hash`),
  KEY `idx_auth_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Profile / contact preferences ==========
-- No AFTER clause: safer if email_verified already sits after status.
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `email_notify` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `users` ADD COLUMN `sms_notify` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `users` ADD COLUMN `preferred_language` VARCHAR(30) NOT NULL DEFAULT 'English';

-- ========== Ticket confirmation status (V1.4+) ==========
ALTER TABLE `tickets`
  MODIFY `status` ENUM(
    'pending',
    'ongoing',
    'processing',
    'awaiting_confirmation',
    'resolved'
  ) NOT NULL DEFAULT 'pending';

-- ========== AI / classification columns (V1.5) ==========
ALTER TABLE `tickets` ADD COLUMN `ai_guidance` TEXT NULL DEFAULT NULL;
ALTER TABLE `tickets` ADD COLUMN `ai_method` VARCHAR(32) NULL DEFAULT NULL;

-- ========== Severity matrix (V1.6) ==========
ALTER TABLE `tickets` ADD COLUMN `urgency` TINYINT NULL DEFAULT NULL;
ALTER TABLE `tickets` ADD COLUMN `impact_level` TINYINT NULL DEFAULT NULL;
ALTER TABLE `tickets` ADD COLUMN `severity_score` INT NULL DEFAULT NULL;

-- ========== Performance clocks (V1.6) ==========
ALTER TABLE `tickets` ADD COLUMN `responded_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `tickets` ADD COLUMN `resolved_at` DATETIME NULL DEFAULT NULL;

-- ========== Satisfaction rating (user overlay after Solved) ==========
ALTER TABLE `tickets` ADD COLUMN `satisfaction` TINYINT NULL DEFAULT NULL;

-- ========== Mailbox attachments + admin audit ==========
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `uploaded_by` INT(11) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attach_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_audit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `actor_id` INT(11) NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `target_id` INT(11) DEFAULT NULL,
  `detail` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Technician replacement requests ==========
CREATE TABLE IF NOT EXISTS `replacement_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `techn_id` INT(11) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_replace_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== Mailbox messages (if missing) ==========
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `sender_id` INT(11) NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
