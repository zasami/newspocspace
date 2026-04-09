-- Wiki Phase 2 : Permissions par rôle + Suggestions IA

-- Permissions par rôle (visibilité d'une page)
-- Logique : si aucune ligne → visible par tous. Si des lignes existent → seuls les rôles listés voient la page.
CREATE TABLE IF NOT EXISTS wiki_page_permissions (
    page_id CHAR(36) NOT NULL,
    role ENUM('collaborateur','responsable','direction','admin') NOT NULL,
    PRIMARY KEY (page_id, role),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suggestions IA log (pour ne pas re-suggérer les mêmes)
CREATE TABLE IF NOT EXISTS wiki_suggestions_log (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    page_id CHAR(36) NOT NULL,
    context_page VARCHAR(50),
    dismissed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ws_user (user_id),
    INDEX idx_ws_page (page_id),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
