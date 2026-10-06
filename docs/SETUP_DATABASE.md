# ZPGC Services — database setup

Give this to a teammate who already has XAMPP. The site in `C:\xampp\htdocs\CP2_V1.6` connects to MySQL database **users_db**, user **root**, with a blank password. That is set in `logic/config.php`.

## 1. Start MySQL

1. Open the XAMPP Control Panel.
2. Start **MySQL**.
3. Start **Apache** as well if you will open the site.
4. Open phpMyAdmin: `http://localhost/phpmyadmin`

If the site says “Connection failed” and tells you to start MySQL, the database server is not running. Start it, then reload the page.

## 2. Create the database

In phpMyAdmin, open the **SQL** tab and run:

```sql
CREATE DATABASE IF NOT EXISTS users_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
```

Click **users_db** in the left sidebar before every statement below. Do not run these on a different database. One old script file names `zpgc_services_db`. Ignore that name. This project uses **users_db**.

## 3. Create the tables

If `users_db` is empty, paste this whole block into the SQL tab and click Go.

```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','techn') NOT NULL,
  `status` enum('inactive','active') NOT NULL DEFAULT 'inactive',
  `phone` varchar(30) DEFAULT NULL,
  `email_notify` tinyint(1) NOT NULL DEFAULT 1,
  `sms_notify` tinyint(1) NOT NULL DEFAULT 0,
  `preferred_language` varchar(30) NOT NULL DEFAULT 'English',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `priority` varchar(20) DEFAULT NULL,
  `status` enum('pending','ongoing','processing','awaiting_confirmation','resolved') NOT NULL DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `ai_guidance` text DEFAULT NULL,
  `ai_method` varchar(32) DEFAULT NULL,
  `urgency` tinyint DEFAULT NULL,
  `impact_level` tinyint DEFAULT NULL,
  `severity_score` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `idx_tickets_assigned` (`assigned_to`),
  CONSTRAINT `fk_tickets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_tickets_assigned`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_ticket` (`ticket_id`),
  KEY `idx_messages_sender` (`sender_id`),
  CONSTRAINT `fk_messages_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

You should see three tables: **users**, **tickets**, and **messages**.

## 4. If the database already exists

Someone who already ran an older copy of the site may already have `users` and `tickets`. Do not drop those tables. In phpMyAdmin, open **users_db**, then run only the statements for columns that are missing. If a line says “Duplicate column,” skip it and run the next one.

Confirmation status (needed from V1.4):

```sql
ALTER TABLE tickets
  MODIFY status ENUM(
    'pending',
    'ongoing',
    'processing',
    'awaiting_confirmation',
    'resolved'
  ) NOT NULL DEFAULT 'pending';
```

AI columns (V1.5), file `database/v1.5_openai_features.sql`:

```sql
ALTER TABLE `tickets`
  ADD COLUMN `ai_guidance` TEXT NULL DEFAULT NULL AFTER `priority`;
ALTER TABLE `tickets`
  ADD COLUMN `ai_method` VARCHAR(32) NULL DEFAULT NULL AFTER `ai_guidance`;
ALTER TABLE `tickets`
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `ai_method`;
```

Score columns (V1.6), file `database/v1.6_severity_matrix.sql`:

```sql
ALTER TABLE `tickets` ADD COLUMN `urgency` TINYINT NULL DEFAULT NULL AFTER `ai_method`;
ALTER TABLE `tickets` ADD COLUMN `impact_level` TINYINT NULL DEFAULT NULL AFTER `urgency`;
ALTER TABLE `tickets` ADD COLUMN `severity_score` INT NULL DEFAULT NULL AFTER `impact_level`;
```

Clock columns (V1.6), file `database/v1.6_performance_times.sql`:

```sql
ALTER TABLE `tickets` ADD COLUMN `responded_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `tickets` ADD COLUMN `resolved_at` DATETIME NULL DEFAULT NULL AFTER `responded_at`;
```

Profile columns (V1.6), file `database/v1.6_user_profile_columns.sql`:

```sql
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `users` ADD COLUMN `email_notify` TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone`;
ALTER TABLE `users` ADD COLUMN `sms_notify` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_notify`;
ALTER TABLE `users` ADD COLUMN `preferred_language` VARCHAR(30) NOT NULL DEFAULT 'English' AFTER `sms_notify`;
```

Mailbox, if `messages` is missing, is in `database/v1.2_messages.sql`. `assigned_to` and `category` on `tickets` must exist before the later scripts. Add them only if Structure does not already show them:

```sql
ALTER TABLE `tickets` ADD COLUMN `category` VARCHAR(50) NULL DEFAULT NULL AFTER `description`;
ALTER TABLE `tickets` ADD COLUMN `assigned_to` INT(11) NULL DEFAULT NULL;
```

## 5. First login

Signup on `http://localhost/CP2_V1.6/pages/login_signup.php` creates a row in `users`. New accounts start as **inactive**, so login says the account is not activated yet.

1. In phpMyAdmin, open **users_db** → **users** → **Browse**.
2. On your own row, set `status` to **active**.
3. Set `role` to **admin** if this account should open the admin pages. Use **techn** for a technician and **user** for a student or staff account.
4. Do not type a password into phpMyAdmin. The site stores a hash. Change the password through signup or the Settings page.

Create at least one **admin**, one **techn**, and one **user**, and set each of them to **active**.

## 6. Check that V1.6 can use the database

| Check | Pass if |
| --- | --- |
| `users` has phone, email_notify, sms_notify, preferred_language | My Profile opens with no “unknown column” error |
| `tickets.status` includes awaiting_confirmation | A technician can set Confirming, and the user can choose Solved |
| `tickets` has urgency, impact_level, severity_score | Submit stores a score |
| `tickets` has responded_at and resolved_at | Performance can show response and resolution time |
| `messages` exists | Mailbox can save a reply on a ticket |

The Python helper and the OpenAI key are not part of this database setup. The site can still log in and save rows when only MySQL is running.
