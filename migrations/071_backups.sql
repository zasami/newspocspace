-- Migration 071: table backups pour le module sauvegarde & restauration
CREATE TABLE IF NOT EXISTS backups (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) DEFAULT NULL COMMENT 'NULL = sauvegarde globale',
    type ENUM('user','global') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    tables_included JSON NOT NULL,
    row_counts JSON NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by CHAR(36) NOT NULL,
    INDEX idx_backup_user (user_id, created_at),
    INDEX idx_backup_type (type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Code d'acces special pour restauration globale (vide = pas encore configure)
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('backup_global_access_code', '');
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('backup_max_per_user', '5');
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('backup_global_retention_days', '14');
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('backup_global_retention_weeks', '8');
