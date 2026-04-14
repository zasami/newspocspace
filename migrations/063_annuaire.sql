-- Annuaire téléphonique EMS
-- Internes (employés, services), externes (médecins, pharmacies), urgences (police, pompiers, etc.)

CREATE TABLE IF NOT EXISTS annuaire (
    id CHAR(36) PRIMARY KEY,
    type ENUM('interne', 'externe', 'urgence') NOT NULL DEFAULT 'externe',
    categorie VARCHAR(50) DEFAULT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(50) DEFAULT NULL,
    fonction VARCHAR(100) DEFAULT NULL,
    service VARCHAR(100) DEFAULT NULL,
    telephone_1 VARCHAR(30) DEFAULT NULL,
    telephone_2 VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    adresse TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    ordre INT NOT NULL DEFAULT 100,
    is_favori TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_categorie (categorie),
    INDEX idx_nom (nom),
    INDEX idx_favori (is_favori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed numéros d'urgence Suisse
INSERT IGNORE INTO annuaire (id, type, categorie, nom, telephone_1, ordre, is_favori, is_active, created_at, updated_at) VALUES
(UUID(), 'urgence', 'urgence_generale', 'Appel d''urgence européen', '112', 1, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'police', 'Police', '117', 2, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'pompiers', 'Pompiers', '118', 3, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'ambulance', 'Ambulance (urgence sanitaire)', '144', 4, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'garde_medicale', 'Médecins de garde', '145', 5, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'tox', 'Tox Info Suisse (empoisonnement)', '145', 6, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'rega', 'REGA (garde aérienne)', '1414', 7, 1, 1, NOW(), NOW()),
(UUID(), 'urgence', 'main_tendue', 'La Main Tendue', '143', 8, 1, 1, NOW(), NOW());
