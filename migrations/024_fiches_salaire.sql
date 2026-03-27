-- Fiches de salaire (payslips)
CREATE TABLE IF NOT EXISTS fiches_salaire (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    annee SMALLINT NOT NULL,
    mois TINYINT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by CHAR(36) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_periode (user_id, annee, mois),
    KEY idx_user (user_id),
    KEY idx_periode (annee, mois),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
