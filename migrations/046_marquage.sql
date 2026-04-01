-- Marquage lingerie / Ubiquid tracking
CREATE TABLE IF NOT EXISTS `marquages` (
  `id` CHAR(36) NOT NULL,
  `resident_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `action` ENUM('marquer','laver','repasser','reparer','autre') NOT NULL DEFAULT 'marquer',
  `statut` ENUM('en_cours','marqué','terminé') NOT NULL DEFAULT 'en_cours',
  `quantite` INT NOT NULL DEFAULT 1,
  `description` TEXT DEFAULT NULL,
  `photo_path` VARCHAR(500) DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `completed_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_marquage_resident` (`resident_id`),
  KEY `idx_marquage_statut` (`statut`),
  KEY `idx_marquage_created` (`created_at`),
  CONSTRAINT `fk_marquage_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marquage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
