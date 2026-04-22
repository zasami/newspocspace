-- 082_suggestions.sql — Suggestions & Demandes de développement (co-construction EMS)
-- 2026-04-20
-- Les collaborateurs soumettent des suggestions (tâches à informatiser, idées, bugs),
-- votent, commentent. Admin/direction suit et change les statuts.

CREATE TABLE IF NOT EXISTS suggestions (
    id               CHAR(36)        NOT NULL PRIMARY KEY,
    reference_code   VARCHAR(16)     NOT NULL UNIQUE COMMENT 'SUG-YYYY-NNN',
    auteur_id        CHAR(36)        NOT NULL,
    titre            VARCHAR(255)    NOT NULL,
    service          ENUM('aide_soignant','infirmier','infirmier_chef','animation',
                          'cuisine','technique','admin','rh','direction','qualite','autre')
                     NOT NULL DEFAULT 'autre',
    categorie        ENUM('formulaire','fonctionnalite','amelioration','alerte','bug','question')
                     NOT NULL DEFAULT 'fonctionnalite',
    urgence          ENUM('critique','eleve','moyen','faible') NOT NULL DEFAULT 'moyen',
    frequence        ENUM('multi_jour','quotidien','hebdo','mensuel','ponctuel') NULL,
    description      TEXT            NOT NULL,
    benefices        VARCHAR(255)    NULL COMMENT 'CSV: gain_temps,reduction_erreurs,tracabilite,conformite,confort_resident,securite',
    statut           ENUM('nouvelle','etudiee','planifiee','en_dev','livree','refusee')
                     NOT NULL DEFAULT 'nouvelle',
    motif_admin      TEXT            NULL COMMENT 'Motif du refus / contexte admin',
    sprint           VARCHAR(64)     NULL,
    votes_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    comments_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at      DATETIME        NULL,

    CONSTRAINT fk_sug_auteur FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sug_ref      (reference_code),
    INDEX idx_sug_statut   (statut, votes_count DESC),
    INDEX idx_sug_service  (service),
    INDEX idx_sug_categorie(categorie),
    INDEX idx_sug_auteur   (auteur_id),
    INDEX idx_sug_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suggestions_votes (
    suggestion_id  CHAR(36) NOT NULL,
    user_id        CHAR(36) NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (suggestion_id, user_id),
    CONSTRAINT fk_sugv_sug  FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sugv_user FOREIGN KEY (user_id)       REFERENCES users(id)        ON DELETE CASCADE,
    INDEX idx_sugv_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suggestions_commentaires (
    id             CHAR(36)   NOT NULL PRIMARY KEY,
    suggestion_id  CHAR(36)   NOT NULL,
    auteur_id      CHAR(36)   NULL COMMENT 'NULL si user supprimé',
    role           ENUM('user','admin') NOT NULL DEFAULT 'user',
    visibility     ENUM('public','admin_only') NOT NULL DEFAULT 'public',
    content        TEXT       NOT NULL,
    created_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sugc_sug  FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sugc_user FOREIGN KEY (auteur_id)     REFERENCES users(id)       ON DELETE SET NULL,
    INDEX idx_sugc_sug (suggestion_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suggestions_attachments (
    id             CHAR(36)        NOT NULL PRIMARY KEY,
    suggestion_id  CHAR(36)        NOT NULL,
    filename       VARCHAR(255)    NOT NULL COMMENT 'Nom sur disque',
    original_name  VARCHAR(255)    NOT NULL,
    mime_type      VARCHAR(100)    NOT NULL,
    size_bytes     BIGINT UNSIGNED NOT NULL,
    kind           ENUM('photo','audio','document','screenshot') NOT NULL DEFAULT 'document',
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_suga_sug FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,
    INDEX idx_suga_sug (suggestion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suggestions_statut_history (
    id             CHAR(36)   NOT NULL PRIMARY KEY,
    suggestion_id  CHAR(36)   NOT NULL,
    old_statut     VARCHAR(32) NULL,
    new_statut     VARCHAR(32) NOT NULL,
    changed_by     CHAR(36)   NULL,
    motif          TEXT       NULL,
    created_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sugh_sug FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sugh_by  FOREIGN KEY (changed_by)    REFERENCES users(id)        ON DELETE SET NULL,
    INDEX idx_sugh_sug (suggestion_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Config flag : active le module (visible par tous) ou le masque
INSERT INTO ems_config (config_key, config_value)
VALUES ('allow_feature_requests', '1')
ON DUPLICATE KEY UPDATE config_value = config_value;

INSERT INTO schema_migrations (migration, applied_at) VALUES ('082_suggestions', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);

UPDATE ems_config SET config_value = '082' WHERE config_key = 'schema_version';
