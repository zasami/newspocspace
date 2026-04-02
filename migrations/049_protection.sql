-- Protection products catalog
CREATE TABLE IF NOT EXISTS `protection_produits` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(200) NOT NULL,
  `taille` VARCHAR(50) DEFAULT NULL,
  `marque` VARCHAR(100) DEFAULT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `couleur` VARCHAR(7) DEFAULT '#2d4a43',
  `is_active` TINYINT(1) DEFAULT 1,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attribution: which resident gets which products + weekly quota
CREATE TABLE IF NOT EXISTS `protection_attributions` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `produit_id` CHAR(36) NOT NULL,
  `quantite_hebdo` INT NOT NULL DEFAULT 0,
  `notes` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prot_attrib` (`resident_id`, `produit_id`),
  KEY `idx_prot_attrib_res` (`resident_id`),
  CONSTRAINT `fk_prot_attrib_res` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prot_attrib_prod` FOREIGN KEY (`produit_id`) REFERENCES `protection_produits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly counting rounds
CREATE TABLE IF NOT EXISTS `protection_comptages` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `produit_id` CHAR(36) NOT NULL,
  `quantite_restante` INT NOT NULL DEFAULT 0,
  `quantite_a_livrer` INT DEFAULT NULL,
  `compteur_id` CHAR(36) NOT NULL,
  `statut` ENUM('compté','validé','livré') DEFAULT 'compté',
  `validated_by` CHAR(36) DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  `delivered_by` CHAR(36) DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `semaine` DATE NOT NULL,
  `notes` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prot_compt_res` (`resident_id`),
  KEY `idx_prot_compt_sem` (`semaine`),
  KEY `idx_prot_compt_stat` (`statut`),
  CONSTRAINT `fk_prot_compt_res` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prot_compt_prod` FOREIGN KEY (`produit_id`) REFERENCES `protection_produits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Config: counting day
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('protection_jour_comptage', 'mardi');
