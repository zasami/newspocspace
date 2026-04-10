-- ═══════════════════════════════════════════════════════════════════════════════
-- Livre d'or — Témoignages publics des familles
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `livre_or` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(120) NOT NULL,
  `email` VARCHAR(200) DEFAULT NULL,
  `lien_resident` VARCHAR(200) DEFAULT NULL,
  `note` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `titre` VARCHAR(200) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `cible` ENUM('ems','personnel','prise_en_charge','vie','autre') NOT NULL DEFAULT 'ems',
  `statut` ENUM('en_attente','approuve','rejete') NOT NULL DEFAULT 'en_attente',
  `epingle` TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `moderated_by` CHAR(36) DEFAULT NULL,
  `moderated_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lo_statut` (`statut`),
  KEY `idx_lo_created` (`created_at` DESC),
  KEY `idx_lo_epingle` (`epingle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature toggle
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES
('feature_livre_or', '1');
