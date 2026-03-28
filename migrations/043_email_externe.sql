-- ═══════════════════════════════════════════════════════════════════════════════
-- Email externe — Configuration IMAP/SMTP + cache + contacts
-- ═══════════════════════════════════════════════════════════════════════════════

-- Configuration IMAP/SMTP par user
CREATE TABLE IF NOT EXISTS `email_externe_config` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'custom' COMMENT 'infomaniak, gmail, outlook, ovh, gandi, custom',
  `email_address` VARCHAR(200) NOT NULL,
  `display_name` VARCHAR(200) DEFAULT NULL,
  `imap_host` VARCHAR(200) NOT NULL,
  `imap_port` SMALLINT UNSIGNED NOT NULL DEFAULT 993,
  `imap_encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
  `smtp_host` VARCHAR(200) NOT NULL,
  `smtp_port` SMALLINT UNSIGNED NOT NULL DEFAULT 587,
  `smtp_encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'tls',
  `username` VARCHAR(200) NOT NULL,
  `encrypted_password` TEXT NOT NULL COMMENT 'AES encrypted',
  `password_iv` VARCHAR(64) NOT NULL,
  `signature` TEXT DEFAULT NULL COMMENT 'HTML signature',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_sync` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email_ext_user` (`user_id`),
  CONSTRAINT `fk_email_ext_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache des headers email (sync IMAP)
CREATE TABLE IF NOT EXISTS `email_externe_cache` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `message_uid` INT UNSIGNED NOT NULL COMMENT 'UID IMAP du message',
  `folder` VARCHAR(100) NOT NULL DEFAULT 'INBOX',
  `from_email` VARCHAR(200) DEFAULT NULL,
  `from_name` VARCHAR(200) DEFAULT NULL,
  `to_emails` TEXT DEFAULT NULL COMMENT 'JSON array',
  `cc_emails` TEXT DEFAULT NULL COMMENT 'JSON array',
  `subject` VARCHAR(500) DEFAULT NULL,
  `date_sent` DATETIME DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `is_flagged` TINYINT(1) DEFAULT 0,
  `has_attachments` TINYINT(1) DEFAULT 0,
  `snippet` VARCHAR(255) DEFAULT NULL COMMENT 'Preview first 255 chars',
  `size` INT UNSIGNED DEFAULT 0,
  `synced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email_ext_uid` (`user_id`, `message_uid`, `folder`),
  KEY `idx_email_ext_user_folder` (`user_id`, `folder`, `date_sent`),
  KEY `idx_email_ext_date` (`date_sent`),
  CONSTRAINT `fk_email_ext_cache_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts externes (carnet d'adresses)
CREATE TABLE IF NOT EXISTS `email_externe_contacts` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(100) DEFAULT NULL,
  `prenom` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(200) NOT NULL,
  `entreprise` VARCHAR(200) DEFAULT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `is_shared` TINYINT(1) DEFAULT 0 COMMENT '1=visible par tous, 0=privé au créateur',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ext_contacts_email` (`email`),
  KEY `idx_ext_contacts_creator` (`created_by`),
  CONSTRAINT `fk_ext_contacts_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
