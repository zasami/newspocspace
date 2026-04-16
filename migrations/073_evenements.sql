-- ─── Événements EMS ─────────────────────────────────────────────────────────
-- Système de gestion d'événements avec champs personnalisables

CREATE TABLE IF NOT EXISTS evenements (
    id CHAR(36) NOT NULL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NULL,
    heure_debut TIME NULL,
    heure_fin TIME NULL,
    lieu VARCHAR(255) NULL,
    image_url VARCHAR(500) NULL,
    max_participants INT UNSIGNED NULL COMMENT 'NULL = illimité',
    statut ENUM('brouillon','ouvert','ferme','annule') NOT NULL DEFAULT 'brouillon',
    inscription_obligatoire TINYINT(1) NOT NULL DEFAULT 1,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_statut (statut),
    INDEX idx_date_debut (date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evenement_champs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    evenement_id CHAR(36) NOT NULL,
    label VARCHAR(255) NOT NULL,
    type ENUM('texte','textarea','nombre','checkbox','radio','select') NOT NULL DEFAULT 'texte',
    options JSON NULL COMMENT 'Pour radio/select/checkbox: ["Option A","Option B"]',
    obligatoire TINYINT(1) NOT NULL DEFAULT 0,
    ordre INT NOT NULL DEFAULT 0,
    INDEX idx_evenement (evenement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evenement_inscriptions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    evenement_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    statut ENUM('inscrit','annule') NOT NULL DEFAULT 'inscrit',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_event_user (evenement_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evenement_inscription_valeurs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    inscription_id CHAR(36) NOT NULL,
    champ_id CHAR(36) NOT NULL,
    valeur TEXT NULL,
    UNIQUE KEY uk_inscription_champ (inscription_id, champ_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register migration
INSERT IGNORE INTO schema_migrations (migration) VALUES ('073_evenements');
UPDATE ems_config SET config_value = '073' WHERE config_key = 'schema_version';
