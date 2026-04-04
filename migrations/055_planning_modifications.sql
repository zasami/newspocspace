-- Track modifications made from the répartition view
-- Provides audit trail: who changed what, when, old vs new value

CREATE TABLE IF NOT EXISTS planning_modifications (
    id CHAR(36) PRIMARY KEY,
    planning_assignation_id CHAR(36) NOT NULL,
    user_id_modified_by CHAR(36) NOT NULL COMMENT 'Admin/responsable who made the change',
    champ VARCHAR(50) NOT NULL COMMENT 'Field changed: horaire_type_id, module_id, groupe_id, statut, notes',
    ancienne_valeur VARCHAR(255) DEFAULT NULL,
    nouvelle_valeur VARCHAR(255) DEFAULT NULL,
    source ENUM('planning','repartition') DEFAULT 'repartition',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pm_assignation (planning_assignation_id),
    INDEX idx_pm_modified_by (user_id_modified_by),
    INDEX idx_pm_created (created_at),
    FOREIGN KEY (planning_assignation_id) REFERENCES planning_assignations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_modified_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
