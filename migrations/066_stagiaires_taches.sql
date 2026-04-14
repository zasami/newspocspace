-- ─────────────────────────────────────────────────────────────
-- Migration 066 — Catalogue tâches stagiaires + checklist reports
-- ─────────────────────────────────────────────────────────────

-- Catalogue de tâches par référentiel de formation
CREATE TABLE IF NOT EXISTS stagiaire_taches_catalogue (
    id CHAR(36) PRIMARY KEY,
    referentiel ENUM('asa_crs','ase','asfm','bachelor_inf','decouverte','civiliste','commun') NOT NULL DEFAULT 'commun',
    categorie VARCHAR(80) NOT NULL DEFAULT 'Général',
    code VARCHAR(40) DEFAULT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    ordre INT DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tcat_ref (referentiel, is_active),
    INDEX idx_tcat_cat (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens report ↔ tâche avec coches stagiaire + validation formateur
CREATE TABLE IF NOT EXISTS stagiaire_report_taches (
    id CHAR(36) PRIMARY KEY,
    report_id CHAR(36) NOT NULL,
    tache_id CHAR(36) NOT NULL,
    stagiaire_coche TINYINT(1) NOT NULL DEFAULT 1,
    nb_fois TINYINT UNSIGNED DEFAULT 1 COMMENT 'Nombre de fois réalisée dans la journée',
    commentaire_stagiaire VARCHAR(500) DEFAULT NULL,
    niveau_formateur ENUM('acquis','en_cours','non_acquis','non_evalue') NOT NULL DEFAULT 'non_evalue',
    commentaire_formateur VARCHAR(500) DEFAULT NULL,
    evalue_by CHAR(36) DEFAULT NULL,
    evalue_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rep_tache (report_id, tache_id),
    INDEX idx_rt_report (report_id),
    INDEX idx_rt_tache (tache_id),
    CONSTRAINT fk_rt_report FOREIGN KEY (report_id) REFERENCES stagiaire_reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_rt_tache FOREIGN KEY (tache_id) REFERENCES stagiaire_taches_catalogue(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mapping type stagiaire → referentiel catalogue
-- cfc_asa → asa_crs, cfc_ase → ase, cfc_asfm → asfm, bachelor_inf → bachelor_inf
-- decouverte → decouverte, civiliste → civiliste, autre → commun
