-- ═══════════════════════════════════════════════════════════════════════════════
-- Espace Famille — Tables pour activités, suivi médical, galerie avec E2EE
-- ═══════════════════════════════════════════════════════════════════════════════

-- Sessions famille (token-based, pas de $_SESSION)
CREATE TABLE IF NOT EXISTS `famille_sessions` (
  `id` CHAR(36) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `correspondant_email` VARCHAR(200) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_famille_sessions_token` (`token`),
  KEY `idx_famille_sessions_resident` (`resident_id`),
  KEY `idx_famille_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_famille_sessions_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clés de chiffrement E2EE par résident (clé AES wrappée par le code d'accès)
CREATE TABLE IF NOT EXISTS `famille_encryption_keys` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `encrypted_key` TEXT NOT NULL COMMENT 'AES-256 key chiffrée par PBKDF2(code_acces)',
  `salt` VARCHAR(128) NOT NULL,
  `iv` VARCHAR(64) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_famille_ek_resident` (`resident_id`),
  CONSTRAINT `fk_famille_ek_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activités
CREATE TABLE IF NOT EXISTS `famille_activites` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `titre` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `date_activite` DATE NOT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_factivites_resident` (`resident_id`),
  KEY `idx_factivites_date` (`date_activite`),
  CONSTRAINT `fk_factivites_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_factivites_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos d'activités (chiffrées)
CREATE TABLE IF NOT EXISTS `famille_activite_photos` (
  `id` CHAR(36) NOT NULL,
  `activite_id` CHAR(36) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `encrypted_iv` VARCHAR(64) NOT NULL COMMENT 'IV utilisé pour chiffrer ce fichier',
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faphotos_activite` (`activite_id`),
  CONSTRAINT `fk_faphotos_activite` FOREIGN KEY (`activite_id`) REFERENCES `famille_activites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suivi médical
CREATE TABLE IF NOT EXISTS `famille_medical` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `titre` VARCHAR(200) NOT NULL,
  `contenu_chiffre` LONGTEXT DEFAULT NULL COMMENT 'Contenu texte chiffré côté client',
  `content_iv` VARCHAR(64) DEFAULT NULL,
  `date_avis` DATE NOT NULL,
  `type` ENUM('avis','rapport','ordonnance','autre') NOT NULL DEFAULT 'avis',
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fmedical_resident` (`resident_id`),
  KEY `idx_fmedical_date` (`date_avis`),
  CONSTRAINT `fk_fmedical_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fmedical_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fichiers médicaux (chiffrés)
CREATE TABLE IF NOT EXISTS `famille_medical_fichiers` (
  `id` CHAR(36) NOT NULL,
  `medical_id` CHAR(36) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(50) NOT NULL COMMENT 'pdf, docx, xlsx, jpg, png...',
  `encrypted_iv` VARCHAR(64) NOT NULL,
  `size` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fmfichiers_medical` (`medical_id`),
  CONSTRAINT `fk_fmfichiers_medical` FOREIGN KEY (`medical_id`) REFERENCES `famille_medical`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Albums galerie
CREATE TABLE IF NOT EXISTS `famille_galerie` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `titre` VARCHAR(200) NOT NULL,
  `date_galerie` DATE NOT NULL,
  `annee` SMALLINT UNSIGNED NOT NULL,
  `cover_photo_id` CHAR(36) DEFAULT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fgalerie_resident` (`resident_id`),
  KEY `idx_fgalerie_annee` (`annee`),
  KEY `idx_fgalerie_date` (`date_galerie`),
  CONSTRAINT `fk_fgalerie_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fgalerie_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos galerie (chiffrées)
CREATE TABLE IF NOT EXISTS `famille_galerie_photos` (
  `id` CHAR(36) NOT NULL,
  `galerie_id` CHAR(36) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `encrypted_iv` VARCHAR(64) NOT NULL,
  `legende` VARCHAR(500) DEFAULT NULL,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fgphotos_galerie` (`galerie_id`),
  CONSTRAINT `fk_fgphotos_galerie` FOREIGN KEY (`galerie_id`) REFERENCES `famille_galerie`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting pour login famille
CREATE TABLE IF NOT EXISTS `famille_rate_limits` (
  `ip` VARCHAR(45) NOT NULL,
  `attempts` INT DEFAULT 1,
  `last_attempt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
