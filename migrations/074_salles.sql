-- 074_salles.sql — Tables pour le système de réservation de salles
-- 2026-04-16

CREATE TABLE IF NOT EXISTS salles (
    id          CHAR(36) NOT NULL PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    description TEXT NULL,
    capacite    INT UNSIGNED NOT NULL DEFAULT 0,
    equipements VARCHAR(500) NULL COMMENT 'projecteur, tableau blanc, etc.',
    couleur     VARCHAR(7) NOT NULL DEFAULT '#2D9CDB',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    ordre       INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations_salles (
    id            CHAR(36) NOT NULL PRIMARY KEY,
    salle_id      CHAR(36) NOT NULL,
    user_id       CHAR(36) NOT NULL,
    titre         VARCHAR(200) NOT NULL,
    description   TEXT NULL,
    date_jour     DATE NOT NULL,
    heure_debut   TIME NOT NULL,
    heure_fin     TIME NOT NULL,
    recurrence    ENUM('aucune','quotidien','hebdomadaire','mensuel') NOT NULL DEFAULT 'aucune',
    statut        ENUM('confirmee','annulee') NOT NULL DEFAULT 'confirmee',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_resa_salle  FOREIGN KEY (salle_id) REFERENCES salles(id) ON DELETE CASCADE,
    CONSTRAINT fk_resa_user   FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_resa_date  (date_jour, salle_id),
    INDEX idx_resa_user  (user_id),
    INDEX idx_resa_salle (salle_id, date_jour, heure_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: deux salles par défaut
INSERT INTO salles (id, nom, description, capacite, equipements, couleur, ordre) VALUES
(UUID(), 'Salle 1', 'Salle de réunion principale', 12, 'Projecteur, Tableau blanc, Vidéoconférence', '#2D9CDB', 1),
(UUID(), 'Salle 8ème', 'Salle de réunion 8ème étage', 8, 'Tableau blanc, TV', '#27AE60', 2);
