-- ═══════════════════════════════════════════════════════════════════════════════
-- Actualités / Fil d'actualité du site vitrine EMS
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `website_actualites` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `type` ENUM('photo','video','affiche','texte','galerie') NOT NULL DEFAULT 'texte',
  `extrait` TEXT DEFAULT NULL,
  `contenu` LONGTEXT DEFAULT NULL,
  `cover_url` VARCHAR(500) DEFAULT NULL,
  `video_url` VARCHAR(500) DEFAULT NULL,
  `video_poster` VARCHAR(500) DEFAULT NULL,
  `images` LONGTEXT DEFAULT NULL,
  `epingle` TINYINT(1) NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` DATETIME DEFAULT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `updated_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_act_slug` (`slug`),
  KEY `idx_act_visible` (`is_visible`),
  KEY `idx_act_published` (`published_at` DESC),
  KEY `idx_act_epingle` (`epingle`),
  KEY `idx_act_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `website_activites_venir` (
  `id` CHAR(36) NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `date_activite` DATE NOT NULL,
  `heure_debut` TIME DEFAULT NULL,
  `heure_fin` TIME DEFAULT NULL,
  `lieu` VARCHAR(255) DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `icone` VARCHAR(50) DEFAULT 'bi-calendar-event',
  `couleur` VARCHAR(20) DEFAULT '#2E7D32',
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_av_date` (`date_activite`),
  KEY `idx_av_visible` (`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature toggle
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES
('feature_actualites', '1');
