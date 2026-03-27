-- Terrassière EMS - Schema initial
-- Base: m57ort_terrassiere_db

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Fonctions (infirmière, ASSC, AS, etc.) ──
CREATE TABLE IF NOT EXISTS `fonctions` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fonctions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Modules / Étages ──
CREATE TABLE IF NOT EXISTS `modules` (
  `id` CHAR(36) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modules_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Étages (sous-divisions des modules) ──
CREATE TABLE IF NOT EXISTS `etages` (
  `id` CHAR(36) NOT NULL,
  `module_id` CHAR(36) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_etages_code` (`code`),
  KEY `idx_etages_module` (`module_id`),
  CONSTRAINT `fk_etages_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Groupes (1-a, 1-b, 2-a, 2-b, etc.) ──
CREATE TABLE IF NOT EXISTS `groupes` (
  `id` CHAR(36) NOT NULL,
  `etage_id` CHAR(36) NOT NULL,
  `nom` VARCHAR(20) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `ordre` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_groupes_code` (`code`),
  KEY `idx_groupes_etage` (`etage_id`),
  CONSTRAINT `fk_groupes_etage` FOREIGN KEY (`etage_id`) REFERENCES `etages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Types d'horaires ──
CREATE TABLE IF NOT EXISTS `horaires_types` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `heure_debut` TIME NOT NULL,
  `heure_fin` TIME NOT NULL,
  `pauses_payees` INT DEFAULT 0,
  `pauses_non_payees` INT DEFAULT 0,
  `duree_effective` DECIMAL(4,2) DEFAULT NULL COMMENT 'Heures effectives calculées',
  `couleur` VARCHAR(7) DEFAULT '#2D9CDB',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_horaires_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Utilisateurs (collaborateurs) ──
CREATE TABLE IF NOT EXISTS `users` (
  `id` CHAR(36) NOT NULL,
  `employee_id` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `fonction_id` CHAR(36) DEFAULT NULL,
  `taux` DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Taux activité en %',
  `type_contrat` ENUM('CDI','CDD','stagiaire','civiliste','interim') DEFAULT 'CDI',
  `date_entree` DATE DEFAULT NULL,
  `date_fin_contrat` DATE DEFAULT NULL,
  `solde_vacances` DECIMAL(5,2) DEFAULT 0 COMMENT 'Jours restants',
  `role` ENUM('collaborateur','responsable','admin','direction') DEFAULT 'collaborateur',
  `is_active` TINYINT(1) DEFAULT 1,
  `reset_token` VARCHAR(64) DEFAULT NULL,
  `reset_expires` DATETIME DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_fonction` (`fonction_id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`),
  CONSTRAINT `fk_users_fonction` FOREIGN KEY (`fonction_id`) REFERENCES `fonctions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Affectation utilisateurs → modules (multi possible) ──
CREATE TABLE IF NOT EXISTS `user_modules` (
  `user_id` CHAR(36) NOT NULL,
  `module_id` CHAR(36) NOT NULL,
  `is_principal` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`user_id`, `module_id`),
  KEY `idx_um_module` (`module_id`),
  CONSTRAINT `fk_um_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_um_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Désirs (max 4/mois) ──
CREATE TABLE IF NOT EXISTS `desirs` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `date_souhaitee` DATE NOT NULL,
  `type` ENUM('jour_off','horaire_special') NOT NULL,
  `detail` TEXT DEFAULT NULL COMMENT 'Texte libre pour horaire spécial',
  `statut` ENUM('en_attente','valide','refuse') DEFAULT 'en_attente',
  `mois_cible` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM du mois concerné',
  `commentaire_chef` TEXT DEFAULT NULL,
  `valide_par` CHAR(36) DEFAULT NULL,
  `valide_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_desirs_user` (`user_id`),
  KEY `idx_desirs_mois` (`mois_cible`),
  KEY `idx_desirs_statut` (`statut`),
  CONSTRAINT `fk_desirs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Absences ──
CREATE TABLE IF NOT EXISTS `absences` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `type` ENUM('vacances','maladie','accident','conge_special','formation','autre') NOT NULL,
  `motif` TEXT DEFAULT NULL,
  `justifie` TINYINT(1) DEFAULT 0,
  `statut` ENUM('en_attente','valide','refuse') DEFAULT 'en_attente',
  `remplacement_type` ENUM('collegue','interim','entraide','vacant') DEFAULT NULL,
  `remplacement_user_id` CHAR(36) DEFAULT NULL,
  `interim_requis` TINYINT(1) DEFAULT 0,
  `entraide_notifie` TINYINT(1) DEFAULT 0,
  `commentaire` TEXT DEFAULT NULL,
  `valide_par` CHAR(36) DEFAULT NULL,
  `valide_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_absences_user` (`user_id`),
  KEY `idx_absences_dates` (`date_debut`, `date_fin`),
  KEY `idx_absences_statut` (`statut`),
  KEY `idx_absences_type` (`type`),
  CONSTRAINT `fk_absences_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Plannings (mensuels) ──
CREATE TABLE IF NOT EXISTS `plannings` (
  `id` CHAR(36) NOT NULL,
  `mois_annee` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `statut` ENUM('brouillon','provisoire','final') DEFAULT 'brouillon',
  `genere_par` CHAR(36) DEFAULT NULL,
  `genere_at` DATETIME DEFAULT NULL,
  `valide_par` CHAR(36) DEFAULT NULL,
  `valide_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plannings_mois` (`mois_annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Assignations planning (une ligne par personne par jour) ──
CREATE TABLE IF NOT EXISTS `planning_assignations` (
  `id` CHAR(36) NOT NULL,
  `planning_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `date_jour` DATE NOT NULL,
  `horaire_type_id` CHAR(36) DEFAULT NULL,
  `module_id` CHAR(36) DEFAULT NULL,
  `groupe_id` CHAR(36) DEFAULT NULL,
  `statut` ENUM('present','absent','remplace','interim','entraide','repos','vacant') DEFAULT 'present',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pa_user_date` (`planning_id`, `user_id`, `date_jour`),
  KEY `idx_pa_date` (`date_jour`),
  KEY `idx_pa_module` (`module_id`),
  CONSTRAINT `fk_pa_planning` FOREIGN KEY (`planning_id`) REFERENCES `plannings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_horaire` FOREIGN KEY (`horaire_type_id`) REFERENCES `horaires_types`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pa_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pa_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `groupes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Besoins de couverture (par module/jour/poste) ──
CREATE TABLE IF NOT EXISTS `besoins_couverture` (
  `id` CHAR(36) NOT NULL,
  `module_id` CHAR(36) NOT NULL,
  `jour_semaine` TINYINT NOT NULL COMMENT '1=lundi...7=dimanche',
  `fonction_id` CHAR(36) NOT NULL,
  `nb_requis` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bc_module` (`module_id`),
  CONSTRAINT `fk_bc_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bc_fonction` FOREIGN KEY (`fonction_id`) REFERENCES `fonctions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Messages internes ──
CREATE TABLE IF NOT EXISTS `messages` (
  `id` CHAR(36) NOT NULL,
  `from_user_id` CHAR(36) NOT NULL,
  `to_user_id` CHAR(36) DEFAULT NULL COMMENT 'NULL = message à la direction',
  `sujet` VARCHAR(255) NOT NULL,
  `contenu` TEXT NOT NULL,
  `lu` TINYINT(1) DEFAULT 0,
  `lu_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_to` (`to_user_id`),
  KEY `idx_messages_from` (`from_user_id`),
  CONSTRAINT `fk_messages_from` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rate limiting ──
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rl_ip_action` (`ip`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ══════════════════════════════════════
-- DONNÉES INITIALES
-- ══════════════════════════════════════

-- Fonctions
INSERT INTO `fonctions` (`id`, `nom`, `code`, `ordre`) VALUES
(UUID(), 'Infirmière', 'INF', 1),
(UUID(), 'ASSC', 'ASSC', 2),
(UUID(), 'Aide-soignant(e)', 'AS', 3),
(UUID(), 'Apprenti / Stagiaire', 'APP', 4),
(UUID(), 'Civiliste', 'CIV', 5),
(UUID(), 'ASE / Animateur', 'ASE', 6),
(UUID(), 'RUV / Inf. Responsable', 'RUV', 7),
(UUID(), 'Responsable des soins', 'RS', 8);

-- Modules
INSERT INTO `modules` (`id`, `nom`, `code`, `ordre`) VALUES
(UUID(), 'Module 1 — Étages 1+2', 'M1', 1),
(UUID(), 'Module 2 — Étage 3', 'M2', 2),
(UUID(), 'Module 3 — Étages 5+6', 'M3', 3),
(UUID(), 'Module 4 — Accueil de jour', 'M4', 4),
(UUID(), 'Nuit', 'NUIT', 5),
(UUID(), 'Pool', 'POOL', 6);

-- Horaires types
INSERT INTO `horaires_types` (`id`, `code`, `nom`, `heure_debut`, `heure_fin`, `pauses_payees`, `pauses_non_payees`, `duree_effective`, `couleur`) VALUES
(UUID(), 'A1', 'Matin court',    '07:00', '15:30', 1, 1, 7.50, '#2D9CDB'),
(UUID(), 'A2', 'Matin moyen',    '07:00', '16:00', 1, 1, 8.00, '#27AE60'),
(UUID(), 'A3', 'Matin long',     '08:00', '16:30', 1, 1, 7.50, '#6C5CE7'),
(UUID(), 'D1', 'Journée type 1', '07:00', '15:30', 1, 1, 7.50, '#00B894'),
(UUID(), 'D3', 'Journée type 3', '07:00', '20:30', 2, 1, 12.00, '#E17055'),
(UUID(), 'D4', 'Journée type 4', '07:00', '19:00', 1, 1, 10.50, '#FDCB6E'),
(UUID(), 'S3', 'Soirée 3',       '13:00', '20:30', 1, 0, 7.00, '#A29BFE'),
(UUID(), 'S4', 'Soirée 4',       '14:00', '20:30', 1, 0, 6.00, '#FD79A8'),
(UUID(), 'A6', 'Admin',          '09:00', '17:30', 1, 1, 7.50, '#636E72'),
(UUID(), 'PIQUET', 'Piquet',     '00:00', '23:59', 0, 0, 0.00, '#DFE6E9');

-- Compte admin par défaut (mot de passe: Admin2026!)
INSERT INTO `users` (`id`, `email`, `password`, `nom`, `prenom`, `role`, `taux`, `type_contrat`, `is_active`) VALUES
(UUID(), 'admin@terrassiere.ch', '$2y$12$rb.DvWEO2tpUNJn2GiYs.eJOKwcFdU0MOx7pAHjbsVW4YE9RlmPiK', 'Admin', 'Système', 'admin', 100.00, 'CDI', 1);
