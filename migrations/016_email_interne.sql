-- Migration 016: Système d'email interne complet
-- Remplace l'ancien système messages simple par un vrai email interne

-- Table des emails
CREATE TABLE IF NOT EXISTS `emails` (
  `id` CHAR(36) NOT NULL,
  `parent_id` CHAR(36) DEFAULT NULL COMMENT 'ID du message parent (réponse)',
  `thread_id` CHAR(36) DEFAULT NULL COMMENT 'ID du premier message du fil',
  `from_user_id` CHAR(36) NOT NULL,
  `sujet` VARCHAR(255) NOT NULL,
  `contenu` TEXT NOT NULL,
  `is_draft` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emails_thread` (`thread_id`),
  KEY `idx_emails_parent` (`parent_id`),
  KEY `idx_emails_from` (`from_user_id`),
  KEY `idx_emails_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des destinataires (to, cc)
CREATE TABLE IF NOT EXISTS `email_recipients` (
  `id` CHAR(36) NOT NULL,
  `email_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `type` ENUM('to', 'cc') DEFAULT 'to',
  `lu` TINYINT(1) DEFAULT 0,
  `lu_at` DATETIME DEFAULT NULL,
  `archived` TINYINT(1) DEFAULT 0,
  `deleted` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_er_email` (`email_id`),
  KEY `idx_er_user` (`user_id`),
  KEY `idx_er_user_lu` (`user_id`, `lu`),
  KEY `idx_er_user_deleted` (`user_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des pièces jointes
CREATE TABLE IF NOT EXISTS `email_attachments` (
  `id` CHAR(36) NOT NULL,
  `email_id` CHAR(36) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ea_email` (`email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suivi suppression côté expéditeur
ALTER TABLE `emails` ADD COLUMN `sender_deleted` TINYINT(1) DEFAULT 0 AFTER `is_draft`;
ALTER TABLE `emails` ADD COLUMN `sender_archived` TINYINT(1) DEFAULT 0 AFTER `sender_deleted`;
