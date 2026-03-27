CREATE TABLE IF NOT EXISTS ia_usage_log (
    id CHAR(36) PRIMARY KEY,
    planning_id CHAR(36) NOT NULL,
    mois_annee VARCHAR(7) NOT NULL,
    provider VARCHAR(20) NOT NULL DEFAULT 'local',
    model VARCHAR(50) DEFAULT NULL,
    tokens_in INT UNSIGNED DEFAULT 0,
    tokens_out INT UNSIGNED DEFAULT 0,
    cost_usd DECIMAL(10,6) DEFAULT 0.000000,
    nb_assignations INT UNSIGNED DEFAULT 0,
    nb_conflicts INT UNSIGNED DEFAULT 0,
    duration_ms INT UNSIGNED DEFAULT 0,
    admin_id CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_usage_mois (mois_annee),
    INDEX idx_ia_usage_created (created_at),
    INDEX idx_ia_usage_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
