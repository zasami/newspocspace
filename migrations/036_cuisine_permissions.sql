-- ═══════════════════════════════════════════
-- 036: Cuisine system + per-user permissions
-- ═══════════════════════════════════════════

-- 1A. Nouvelles fonctions cuisine/hôtellerie
INSERT INTO fonctions (id, nom, code, ordre) VALUES
(UUID(), 'Chef cuisinier', 'CHEF', 9),
(UUID(), 'Cuisinier', 'CUIS', 10),
(UUID(), 'Hôtellerie', 'HOT', 11);

-- 1B. Permissions par utilisateur (whitelist-by-default: pas de ligne = tout accessible)
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id` CHAR(36) NOT NULL,
  `permission_key` VARCHAR(50) NOT NULL,
  `granted` TINYINT(1) DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `permission_key`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1C. Résidents de l'EMS
CREATE TABLE IF NOT EXISTS `residents` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `chambre` VARCHAR(20) DEFAULT NULL,
  `etage` VARCHAR(20) DEFAULT NULL,
  `is_vip` TINYINT(1) DEFAULT 0,
  `menu_special` TEXT DEFAULT NULL COMMENT 'régime spécial VIP',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_residents_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1D. Visiteurs (personnes externes connues)
CREATE TABLE IF NOT EXISTS `visiteurs` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `resident_id` CHAR(36) DEFAULT NULL COMMENT 'lien principal résident',
  `relation` VARCHAR(100) DEFAULT NULL COMMENT 'fille, fils, ami, etc.',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visiteurs_resident` (`resident_id`),
  KEY `idx_visiteurs_nom` (`nom`, `prenom`),
  CONSTRAINT `fk_visiteurs_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1E. Réservations repas famille/visiteurs
CREATE TABLE IF NOT EXISTS `reservations_famille` (
  `id` CHAR(36) NOT NULL,
  `date_jour` DATE NOT NULL,
  `repas` ENUM('midi','soir') DEFAULT 'midi',
  `resident_id` CHAR(36) NOT NULL,
  `visiteur_id` CHAR(36) DEFAULT NULL COMMENT 'visiteur connu',
  `visiteur_nom` VARCHAR(200) DEFAULT NULL COMMENT 'nom libre si pas dans visiteurs',
  `nb_personnes` TINYINT UNSIGNED DEFAULT 1,
  `remarques` TEXT DEFAULT NULL,
  `statut` ENUM('confirmee','annulee') DEFAULT 'confirmee',
  `created_by` CHAR(36) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rf_date` (`date_jour`),
  KEY `idx_rf_resident` (`resident_id`),
  KEY `idx_rf_visiteur` (`visiteur_id`),
  KEY `idx_rf_statut` (`statut`),
  CONSTRAINT `fk_rf_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rf_visiteur` FOREIGN KEY (`visiteur_id`) REFERENCES `visiteurs`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rf_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1F. Menus : ajouter repas midi/soir
ALTER TABLE `menus` ADD COLUMN `repas` ENUM('midi','soir') DEFAULT 'midi' AFTER `date_jour`;
ALTER TABLE `menus` DROP INDEX `uk_menu_date`;
ALTER TABLE `menus` ADD UNIQUE KEY `uk_menu_date_repas` (`date_jour`, `repas`);
