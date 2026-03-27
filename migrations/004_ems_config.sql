-- Terrassière - Table configuration générale de l'établissement
-- Table clé-valeur flexible: chaque EMS peut stocker ses propres paramètres

CREATE TABLE IF NOT EXISTS `ems_config` (
  `config_key` VARCHAR(100) NOT NULL,
  `config_value` TEXT DEFAULT NULL,
  `updated_by` CHAR(36) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales Terrassière
INSERT INTO `ems_config` (`config_key`, `config_value`) VALUES
('ems_nom',           'EMS Terrassière'),
('ems_adresse',       'Chemin de la Terrassière 2'),
('ems_npa',           '1209'),
('ems_ville',         'Genève'),
('ems_canton',        'GE'),
('ems_pays',          'Suisse'),
('ems_telephone',     '+41 22 718 88 00'),
('ems_fax',           ''),
('ems_email',         'info@terrassiere.ch'),
('ems_site_web',      'https://terrassiere.ch'),
('ems_logo_url',      ''),
('ems_type',          'EMS'),
('ems_nb_lits',       '120'),
('ems_nb_etages',     '6'),
('ems_nb_modules',    '6'),
('directeur_nom',     ''),
('directeur_prenom',  ''),
('directeur_email',   ''),
('directeur_telephone', ''),
('infirmiere_chef_nom',     ''),
('infirmiere_chef_prenom',  ''),
('infirmiere_chef_email',   ''),
('infirmiere_chef_telephone', ''),
('responsable_rh_nom',     ''),
('responsable_rh_prenom',  ''),
('responsable_rh_email',   ''),
('planning_heures_semaine',   '42'),
('planning_repos_minimum',    '1'),
('planning_jours_consecutifs_max', '6'),
('planning_desirs_max_mois', '4'),
('planning_desirs_ouverture_jour', '1'),
('planning_desirs_fermeture_jour', '10')
ON DUPLICATE KEY UPDATE config_key = config_key;
