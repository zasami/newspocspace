-- Périodes bloquées pour les vacances (définies par l'admin)
CREATE TABLE IF NOT EXISTS `periodes_bloquees` (
  `id` CHAR(36) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `motif` VARCHAR(255) DEFAULT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dates` (`date_debut`, `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Config vacances dans ems_config
INSERT IGNORE INTO `ems_config` (`config_key`, `config_value`) VALUES
('vacances_solde_annuel_defaut', '25'),
('vacances_max_consecutifs', '15'),
('vacances_delai_minimum_jours', '14');
