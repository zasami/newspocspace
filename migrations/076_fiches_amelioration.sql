-- 076_fiches_amelioration.sql — Fiches d'amélioration continue
-- 2026-04-18
-- Anonymat STRICT : auteur_id = NULL si is_anonymous = 1 (aucune traçabilité, même admin)

CREATE TABLE IF NOT EXISTS fiches_amelioration (
    id              CHAR(36) NOT NULL PRIMARY KEY,
    auteur_id       CHAR(36) NULL COMMENT 'NULL si fiche anonyme (anonymat strict)',
    is_anonymous    TINYINT(1) NOT NULL DEFAULT 0,
    visibility      ENUM('private','public','targeted') NOT NULL DEFAULT 'private'
                    COMMENT 'private=auteur+admin, public=tous, targeted=auteur+admin+concernes',
    titre           VARCHAR(255) NOT NULL,
    categorie       ENUM('securite','qualite_soins','organisation','materiel','communication','autre')
                    NOT NULL DEFAULT 'autre',
    criticite       ENUM('faible','moyenne','haute') NOT NULL DEFAULT 'moyenne',
    description     TEXT NOT NULL,
    suggestion      TEXT NULL,
    statut          ENUM('soumise','en_revue','en_cours','realisee','rejetee')
                    NOT NULL DEFAULT 'soumise',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at     DATETIME NULL,

    CONSTRAINT fk_fiche_auteur FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_fiche_auteur    (auteur_id),
    INDEX idx_fiche_statut    (statut),
    INDEX idx_fiche_visibility(visibility),
    INDEX idx_fiche_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiches_amelioration_commentaires (
    id            CHAR(36) NOT NULL PRIMARY KEY,
    fiche_id      CHAR(36) NOT NULL,
    auteur_id     CHAR(36) NULL COMMENT 'NULL si commentaire anonyme',
    is_anonymous  TINYINT(1) NOT NULL DEFAULT 0,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    content       TEXT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_fcom_fiche  FOREIGN KEY (fiche_id)  REFERENCES fiches_amelioration(id) ON DELETE CASCADE,
    CONSTRAINT fk_fcom_auteur FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_fcom_fiche (fiche_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiches_amelioration_concernes (
    fiche_id   CHAR(36) NOT NULL,
    user_id    CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (fiche_id, user_id),
    CONSTRAINT fk_fconc_fiche FOREIGN KEY (fiche_id) REFERENCES fiches_amelioration(id) ON DELETE CASCADE,
    CONSTRAINT fk_fconc_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_fconc_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiches_amelioration_attachments (
    id            CHAR(36) NOT NULL PRIMARY KEY,
    fiche_id      CHAR(36) NOT NULL,
    filename      VARCHAR(255) NOT NULL COMMENT 'Nom stocké sur disque',
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    size_bytes    BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_fatt_fiche FOREIGN KEY (fiche_id) REFERENCES fiches_amelioration(id) ON DELETE CASCADE,
    INDEX idx_fatt_fiche (fiche_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiches_amelioration_rdv (
    id               CHAR(36) NOT NULL PRIMARY KEY,
    fiche_id         CHAR(36) NOT NULL,
    proposed_by      CHAR(36) NOT NULL COMMENT 'Admin user id',
    date_proposed    DATETIME NOT NULL,
    lieu             VARCHAR(255) NULL,
    admin_notes      TEXT NULL,
    statut           ENUM('proposee','acceptee','refusee','effectuee','annulee')
                     NOT NULL DEFAULT 'proposee',
    user_response    TEXT NULL,
    responded_at     DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_frdv_fiche FOREIGN KEY (fiche_id) REFERENCES fiches_amelioration(id) ON DELETE CASCADE,
    CONSTRAINT fk_frdv_admin FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_frdv_fiche (fiche_id, date_proposed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration, applied_at) VALUES ('076_fiches_amelioration', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);

UPDATE ems_config SET config_value = '076' WHERE config_key = 'schema_version';
