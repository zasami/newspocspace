-- Hygiene products catalog
CREATE TABLE IF NOT EXISTS `hygiene_produits` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(200) NOT NULL,
  `categorie` ENUM('savon','rasoir','parfum','gel_douche','apres_rasage','dentifrice','shampooing','creme','deodorant','autre') DEFAULT 'autre',
  `marque` VARCHAR(100) DEFAULT NULL,
  `couleur` VARCHAR(7) DEFAULT '#3B4F6B',
  `is_active` TINYINT(1) DEFAULT 1,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily orders by aides
CREATE TABLE IF NOT EXISTS `hygiene_commandes` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `produit_id` CHAR(36) NOT NULL,
  `quantite` INT NOT NULL DEFAULT 1,
  `urgence` TINYINT(1) DEFAULT 0,
  `notes` VARCHAR(500) DEFAULT NULL,
  `commandeur_id` CHAR(36) NOT NULL,
  `statut` ENUM('commandé','préparé','distribué') DEFAULT 'commandé',
  `prepared_by` CHAR(36) DEFAULT NULL,
  `prepared_at` DATETIME DEFAULT NULL,
  `delivered_by` CHAR(36) DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `jour` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hyg_cmd_jour` (`jour`),
  KEY `idx_hyg_cmd_statut` (`statut`),
  KEY `idx_hyg_cmd_res` (`resident_id`),
  CONSTRAINT `fk_hyg_cmd_res` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hyg_cmd_prod` FOREIGN KEY (`produit_id`) REFERENCES `hygiene_produits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
