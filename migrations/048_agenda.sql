-- Agenda / Calendar system
CREATE TABLE IF NOT EXISTS `agenda_events` (
  `id` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `category` ENUM('rdv','reunion','rappel','personnel','medical','formation','autre') NOT NULL DEFAULT 'rdv',
  `color` VARCHAR(7) DEFAULT '#2d4a43',
  `all_day` TINYINT(1) DEFAULT 0,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME DEFAULT NULL,
  `recurrence` ENUM('none','daily','weekly','biweekly','monthly','yearly') DEFAULT 'none',
  `recurrence_end` DATE DEFAULT NULL,
  `reminder_minutes` INT DEFAULT 15,
  `is_private` TINYINT(1) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_by` CHAR(36) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agenda_start` (`start_at`),
  KEY `idx_agenda_creator` (`created_by`),
  KEY `idx_agenda_category` (`category`),
  CONSTRAINT `fk_agenda_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agenda_participants` (
  `id` CHAR(36) NOT NULL,
  `event_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) DEFAULT NULL,
  `external_name` VARCHAR(200) DEFAULT NULL,
  `external_email` VARCHAR(200) DEFAULT NULL,
  `status` ENUM('pending','accepted','declined','tentative') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agenda_part_event` (`event_id`),
  KEY `idx_agenda_part_user` (`user_id`),
  CONSTRAINT `fk_agenda_part_event` FOREIGN KEY (`event_id`) REFERENCES `agenda_events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
