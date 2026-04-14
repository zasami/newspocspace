-- ─────────────────────────────────────────────────────────────
-- Migration 065 — Gestion des stagiaires
-- ─────────────────────────────────────────────────────────────

-- 1. Étendre le rôle users pour accepter "stagiaire"
ALTER TABLE users
    MODIFY COLUMN role ENUM('collaborateur','responsable','admin','direction','stagiaire')
    NOT NULL DEFAULT 'collaborateur';

-- 2. Table stagiaires — profil d'un stagiaire
CREATE TABLE IF NOT EXISTS stagiaires (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    type ENUM('decouverte','cfc_asa','cfc_ase','cfc_asfm','bachelor_inf','civiliste','autre') NOT NULL DEFAULT 'autre',
    etablissement_origine VARCHAR(200) DEFAULT NULL,
    niveau VARCHAR(80) DEFAULT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    etage_id CHAR(36) DEFAULT NULL,
    ruv_id CHAR(36) DEFAULT NULL COMMENT 'RUV responsable formation',
    formateur_principal_id CHAR(36) DEFAULT NULL,
    objectifs_generaux TEXT DEFAULT NULL,
    statut ENUM('prevu','actif','termine','interrompu') NOT NULL DEFAULT 'prevu',
    notes_ruv TEXT DEFAULT NULL,
    created_by CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stag_user (user_id),
    INDEX idx_stag_ruv (ruv_id),
    INDEX idx_stag_statut (statut),
    INDEX idx_stag_dates (date_debut, date_fin),
    CONSTRAINT fk_stag_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Affectations formateur (un stagiaire peut avoir plusieurs formateurs sur des périodes différentes)
CREATE TABLE IF NOT EXISTS stagiaire_affectations (
    id CHAR(36) PRIMARY KEY,
    stagiaire_id CHAR(36) NOT NULL,
    formateur_id CHAR(36) NOT NULL,
    etage_id CHAR(36) DEFAULT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    role_formateur ENUM('principal','remplacant','ponctuel') NOT NULL DEFAULT 'ponctuel',
    notes VARCHAR(255) DEFAULT NULL,
    created_by CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aff_stag (stagiaire_id),
    INDEX idx_aff_form (formateur_id),
    INDEX idx_aff_dates (date_debut, date_fin),
    CONSTRAINT fk_aff_stag FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE,
    CONSTRAINT fk_aff_form FOREIGN KEY (formateur_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Reports (journal de bord quotidien/hebdo rédigé par le stagiaire)
CREATE TABLE IF NOT EXISTS stagiaire_reports (
    id CHAR(36) PRIMARY KEY,
    stagiaire_id CHAR(36) NOT NULL,
    type ENUM('quotidien','hebdo') NOT NULL DEFAULT 'quotidien',
    date_report DATE NOT NULL,
    semaine_num TINYINT UNSIGNED DEFAULT NULL,
    titre VARCHAR(200) DEFAULT NULL,
    contenu MEDIUMTEXT NOT NULL,
    statut ENUM('brouillon','soumis','valide','a_refaire') NOT NULL DEFAULT 'brouillon',
    submitted_at DATETIME DEFAULT NULL,
    validated_by CHAR(36) DEFAULT NULL,
    validated_at DATETIME DEFAULT NULL,
    commentaire_formateur TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rep_stag (stagiaire_id),
    INDEX idx_rep_statut (statut),
    INDEX idx_rep_date (date_report),
    CONSTRAINT fk_rep_stag FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Évaluations (grille de notation par le formateur)
CREATE TABLE IF NOT EXISTS stagiaire_evaluations (
    id CHAR(36) PRIMARY KEY,
    stagiaire_id CHAR(36) NOT NULL,
    formateur_id CHAR(36) NOT NULL,
    date_eval DATE NOT NULL,
    periode ENUM('journaliere','hebdo','mi_stage','finale') NOT NULL DEFAULT 'journaliere',
    note_initiative TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5',
    note_communication TINYINT UNSIGNED DEFAULT NULL,
    note_connaissances TINYINT UNSIGNED DEFAULT NULL,
    note_autonomie TINYINT UNSIGNED DEFAULT NULL,
    note_savoir_etre TINYINT UNSIGNED DEFAULT NULL,
    note_ponctualite TINYINT UNSIGNED DEFAULT NULL,
    points_forts TEXT DEFAULT NULL,
    points_amelioration TEXT DEFAULT NULL,
    commentaire_general TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_eval_stag (stagiaire_id),
    INDEX idx_eval_form (formateur_id),
    INDEX idx_eval_date (date_eval),
    CONSTRAINT fk_eval_stag FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_form FOREIGN KEY (formateur_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Objectifs individuels (définis par la RUV)
CREATE TABLE IF NOT EXISTS stagiaire_objectifs (
    id CHAR(36) PRIMARY KEY,
    stagiaire_id CHAR(36) NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    date_cible DATE DEFAULT NULL,
    statut ENUM('en_cours','atteint','non_atteint','abandonne') NOT NULL DEFAULT 'en_cours',
    commentaire_ruv TEXT DEFAULT NULL,
    created_by CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_obj_stag (stagiaire_id),
    CONSTRAINT fk_obj_stag FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
