-- Menu du jour (midi, 7j/7) + réservations repas
CREATE TABLE IF NOT EXISTS `menus` (
  `id` CHAR(36) NOT NULL,
  `date_jour` DATE NOT NULL,
  `entree` VARCHAR(500) DEFAULT NULL,
  `plat` VARCHAR(500) NOT NULL,
  `salade` VARCHAR(500) DEFAULT NULL COMMENT 'option salade du jour',
  `accompagnement` VARCHAR(500) DEFAULT NULL,
  `dessert` VARCHAR(500) DEFAULT NULL,
  `remarques` TEXT DEFAULT NULL,
  `created_by` CHAR(36) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_date` (`date_jour`),
  KEY `idx_menu_date` (`date_jour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_reservations` (
  `id` CHAR(36) NOT NULL,
  `menu_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `choix` ENUM('menu','salade') DEFAULT 'menu' COMMENT 'menu complet ou salade',
  `nb_personnes` TINYINT UNSIGNED DEFAULT 1,
  `remarques` VARCHAR(500) DEFAULT NULL COMMENT 'sans viande, sans huile, allergie, etc.',
  `paiement` ENUM('salaire','caisse','carte') DEFAULT 'salaire' COMMENT 'retenue sur salaire, cash caisse, carte',
  `statut` ENUM('confirmee','annulee') DEFAULT 'confirmee',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reservation_user_menu` (`menu_id`, `user_id`),
  KEY `idx_reservation_user` (`user_id`),
  KEY `idx_reservation_menu` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
