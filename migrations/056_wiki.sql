-- Wiki / Base de connaissances pour SpocCare
-- Categories
CREATE TABLE IF NOT EXISTS wiki_categories (
    id CHAR(36) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    icone VARCHAR(50) DEFAULT 'book',
    couleur VARCHAR(20) DEFAULT '#6c757d',
    ordre INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wiki_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pages
CREATE TABLE IF NOT EXISTS wiki_pages (
    id CHAR(36) PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    contenu LONGTEXT,
    description TEXT,
    categorie_id CHAR(36),
    version INT DEFAULT 1,
    created_by CHAR(36) NOT NULL,
    updated_by CHAR(36),
    visible TINYINT(1) DEFAULT 1,
    epingle TINYINT(1) DEFAULT 0,
    archived_at DATETIME DEFAULT NULL,
    archived_by CHAR(36) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wiki_categorie (categorie_id),
    INDEX idx_wiki_visible (visible),
    INDEX idx_wiki_archived (archived_at),
    INDEX idx_wiki_created (created_at DESC),
    UNIQUE KEY uk_wiki_slug (slug),
    FOREIGN KEY (categorie_id) REFERENCES wiki_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des versions
CREATE TABLE IF NOT EXISTS wiki_versions (
    id CHAR(36) PRIMARY KEY,
    page_id CHAR(36) NOT NULL,
    version INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    contenu LONGTEXT NOT NULL,
    edited_by CHAR(36) NOT NULL,
    note VARCHAR(500) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wv_page (page_id),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default categories for EMS
INSERT INTO wiki_categories (id, nom, slug, icone, couleur, ordre) VALUES
(UUID(), 'Protocoles de soins', 'protocoles-soins', 'heart-pulse', '#dc3545', 1),
(UUID(), 'Hygiène & Sécurité', 'hygiene-securite', 'shield-check', '#fd7e14', 2),
(UUID(), 'RH & Administratif', 'rh-administratif', 'person-badge', '#0d6efd', 3),
(UUID(), 'Cuisine & Nutrition', 'cuisine-nutrition', 'egg-fried', '#198754', 4),
(UUID(), 'Vie quotidienne', 'vie-quotidienne', 'house-heart', '#6610f2', 5),
(UUID(), 'Formations', 'formations', 'mortarboard', '#20c997', 6);
