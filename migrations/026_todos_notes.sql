-- Migration 026: Todos & Notes modules
-- zerdaTime admin

-- ── TODOS ──
CREATE TABLE IF NOT EXISTS admin_todos (
    id CHAR(36) NOT NULL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    priorite ENUM('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
    statut ENUM('a_faire','en_cours','termine','annule') NOT NULL DEFAULT 'a_faire',
    date_echeance DATE DEFAULT NULL,
    assigned_to CHAR(36) DEFAULT NULL,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    INDEX idx_todos_statut (statut),
    INDEX idx_todos_priorite (priorite),
    INDEX idx_todos_date (date_echeance),
    INDEX idx_todos_assigned (assigned_to),
    INDEX idx_todos_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTES ──
CREATE TABLE IF NOT EXISTS admin_notes (
    id CHAR(36) NOT NULL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    contenu TEXT DEFAULT NULL,
    categorie ENUM('idee','probleme','decision','rappel','observation','autre') NOT NULL DEFAULT 'autre',
    couleur VARCHAR(7) DEFAULT '#F7F5F2',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notes_categorie (categorie),
    INDEX idx_notes_pinned (is_pinned),
    INDEX idx_notes_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
