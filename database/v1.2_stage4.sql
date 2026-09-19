-- Run in phpMyAdmin → users_db → SQL (Stage 4 / v1.2)
-- Screenshot Structure after each successful Save.

ALTER TABLE `tickets`
  ADD COLUMN `category` VARCHAR(50) NULL DEFAULT NULL AFTER `description`;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `sender_id` INT(11) NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_ticket` (`ticket_id`),
  CONSTRAINT `fk_messages_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_messages_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
