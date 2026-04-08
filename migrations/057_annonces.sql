-- Annonces officielles + feature toggles mur/annonces

-- Feature toggles
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES
('feature_mur_social', '1'),
('feature_annonces', '1');

-- Annonces table
CREATE TABLE IF NOT EXISTS annonces (
    id CHAR(36) PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    contenu LONGTEXT,
    description TEXT,
    image_url VARCHAR(500) DEFAULT NULL,
    categorie ENUM('direction','rh','vie_sociale','cuisine','protocoles','securite','divers') DEFAULT 'direction',
    epingle TINYINT(1) DEFAULT 0,
    visible TINYINT(1) DEFAULT 1,
    published_at DATETIME DEFAULT NULL,
    created_by CHAR(36) NOT NULL,
    updated_by CHAR(36),
    archived_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ann_visible (visible),
    INDEX idx_ann_published (published_at DESC),
    INDEX idx_ann_archived (archived_at),
    INDEX idx_ann_categorie (categorie),
    UNIQUE KEY uk_ann_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
