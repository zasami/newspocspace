-- Documents management system
-- Services/categories for documents
CREATE TABLE IF NOT EXISTS document_services (
    id CHAR(36) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(50) DEFAULT 'folder',
    couleur VARCHAR(20) DEFAULT '#6c757d',
    ordre INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents table
CREATE TABLE IF NOT EXISTS documents (
    id CHAR(36) PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    service_id CHAR(36) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size INT UNSIGNED DEFAULT 0,
    uploaded_by CHAR(36) NOT NULL,
    visible TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_doc_service (service_id),
    INDEX idx_doc_visible (visible),
    INDEX idx_doc_mime (mime_type),
    INDEX idx_doc_created (created_at DESC)
);

-- Access restrictions per service (which roles/services can see)
CREATE TABLE IF NOT EXISTS document_access (
    id CHAR(36) PRIMARY KEY,
    document_id CHAR(36) DEFAULT NULL,
    service_id CHAR(36) DEFAULT NULL,
    role VARCHAR(50) DEFAULT NULL,
    acces ENUM('visible','bloque') DEFAULT 'visible',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_access_doc (document_id),
    INDEX idx_access_service (service_id),
    INDEX idx_access_role (role)
);

-- Seed default services
INSERT INTO document_services (id, nom, slug, icone, couleur, ordre) VALUES
(UUID(), 'Ressources Humaines', 'rh', 'people-fill', '#0d6efd', 1),
(UUID(), 'Informatique', 'informatique', 'pc-display', '#6610f2', 2),
(UUID(), 'Charte & Règlements', 'charte', 'journal-bookmark-fill', '#fd7e14', 3),
(UUID(), 'Qualité', 'qualite', 'award-fill', '#198754', 4),
(UUID(), 'Maintenance', 'maintenance', 'tools', '#dc3545', 5),
(UUID(), 'Direction', 'direction', 'briefcase-fill', '#1B2A4A', 6),
(UUID(), 'Formation', 'formation', 'mortarboard-fill', '#20c997', 7),
(UUID(), 'Sécurité', 'securite', 'shield-check', '#e74c3c', 8);
