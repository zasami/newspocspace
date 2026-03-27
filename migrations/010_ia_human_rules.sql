CREATE TABLE IF NOT EXISTS ia_human_rules (
    id CHAR(36) PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    importance VARCHAR(20) NOT NULL DEFAULT 'moyen' COMMENT 'important, moyen, supprime',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by CHAR(36),
    INDEX idx_ia_rules_actif (actif),
    INDEX idx_ia_rules_importance (importance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
