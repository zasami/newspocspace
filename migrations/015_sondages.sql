-- Sondages (surveys) tables
CREATE TABLE IF NOT EXISTS sondages (
    id CHAR(36) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT NULL,
    statut ENUM('brouillon','ouvert','ferme') NOT NULL DEFAULT 'brouillon',
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sondages_created_by (created_by),
    KEY idx_sondages_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sondage_questions (
    id CHAR(36) NOT NULL,
    sondage_id CHAR(36) NOT NULL,
    question TEXT NOT NULL,
    type ENUM('choix_unique','choix_multiple','texte_libre') NOT NULL DEFAULT 'choix_unique',
    options JSON NULL,
    ordre INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_sq_sondage (sondage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sondage_reponses (
    id CHAR(36) NOT NULL,
    sondage_id CHAR(36) NOT NULL,
    question_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    reponse TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_vote (question_id, user_id),
    KEY idx_sr_sondage (sondage_id),
    KEY idx_sr_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
