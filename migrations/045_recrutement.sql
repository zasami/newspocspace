CREATE TABLE IF NOT EXISTS offres_emploi (
    id CHAR(36) PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type_contrat VARCHAR(50) DEFAULT 'CDI',
    taux_activite VARCHAR(50) DEFAULT '100%',
    departement VARCHAR(100),
    lieu VARCHAR(100) DEFAULT 'Genève',
    date_debut DATE,
    date_limite DATE,
    exigences TEXT,
    avantages TEXT,
    salaire_indication VARCHAR(255),
    contact_email VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    ordre INT DEFAULT 0,
    created_by CHAR(36),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidatures (
    id CHAR(36) PRIMARY KEY,
    offre_id CHAR(36) NOT NULL,
    code_suivi CHAR(8) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telephone VARCHAR(50),
    date_naissance DATE,
    adresse TEXT,
    nationalite VARCHAR(100),
    permis_travail VARCHAR(50),
    disponibilite VARCHAR(255),
    motivation TEXT,
    experience TEXT,
    statut ENUM('recue','en_cours','entretien','acceptee','refusee','archivee') DEFAULT 'recue',
    notes_admin TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_code_suivi (code_suivi),
    INDEX idx_offre (offre_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidature_documents (
    id CHAR(36) PRIMARY KEY,
    candidature_id CHAR(36) NOT NULL,
    type_document VARCHAR(50) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    size INT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_candidature (candidature_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
