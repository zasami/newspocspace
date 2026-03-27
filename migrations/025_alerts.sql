-- Alerts system: high-importance broadcast messages with mandatory read
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `priority` ENUM('normale','haute') DEFAULT 'normale',
  `target` ENUM('all','module','fonction') DEFAULT 'all',
  `target_value` VARCHAR(100) DEFAULT NULL COMMENT 'module_id or fonction code when target != all',
  `created_by` CHAR(36) NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_active` (`is_active`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alert_reads` (
  `id` CHAR(36) NOT NULL,
  `alert_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `read_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_alert_user` (`alert_id`, `user_id`),
  KEY `idx_alert_reads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
