-- Wiki Phase 1 : Tags, Favoris, Vérification/Expert, FULLTEXT

-- Tags
CREATE TABLE IF NOT EXISTS wiki_tags (
    id CHAR(36) PRIMARY KEY,
    nom VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    couleur VARCHAR(20) DEFAULT '#6c757d',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wiki_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page ↔ Tags (many-to-many)
CREATE TABLE IF NOT EXISTS wiki_page_tags (
    page_id CHAR(36) NOT NULL,
    tag_id CHAR(36) NOT NULL,
    PRIMARY KEY (page_id, tag_id),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES wiki_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favoris personnels
CREATE TABLE IF NOT EXISTS wiki_favoris (
    user_id CHAR(36) NOT NULL,
    page_id CHAR(36) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, page_id),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vérification / Expert : colonnes sur wiki_pages
ALTER TABLE wiki_pages
    ADD COLUMN expert_id CHAR(36) DEFAULT NULL AFTER updated_by,
    ADD COLUMN verified_at DATETIME DEFAULT NULL AFTER expert_id,
    ADD COLUMN verified_by CHAR(36) DEFAULT NULL AFTER verified_at,
    ADD COLUMN verify_interval_days INT DEFAULT 90 AFTER verified_by,
    ADD COLUMN verify_next DATETIME DEFAULT NULL AFTER verify_interval_days,
    ADD INDEX idx_wiki_expert (expert_id),
    ADD INDEX idx_wiki_verify_next (verify_next);

-- FULLTEXT index pour recherche rapide
ALTER TABLE wiki_pages ADD FULLTEXT INDEX ft_wiki_search (titre, description, contenu);

-- Seed default tags pour EMS
INSERT INTO wiki_tags (id, nom, slug, couleur) VALUES
(UUID(), 'Urgence', 'urgence', '#dc3545'),
(UUID(), 'Protocole', 'protocole', '#fd7e14'),
(UUID(), 'Médicament', 'medicament', '#0d6efd'),
(UUID(), 'Hygiène', 'hygiene', '#20c997'),
(UUID(), 'Formation', 'formation', '#6610f2'),
(UUID(), 'Sécurité', 'securite', '#e83e8c'),
(UUID(), 'Nutrition', 'nutrition', '#198754'),
(UUID(), 'Administratif', 'administratif', '#6c757d');
