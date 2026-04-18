-- Migration 079 : Admissions (demande d'inscription EMS en ligne)
-- Étape 1 du dossier d'admission : formulaire public de demande

CREATE TABLE IF NOT EXISTS admissions_candidats (
  id CHAR(36) PRIMARY KEY,
  token_acces CHAR(36) NOT NULL UNIQUE,

  type_demande ENUM('urgente','preventive') NOT NULL,
  date_demande DATE NOT NULL,

  -- Personne concernée
  nom_prenom VARCHAR(200) NOT NULL,
  date_naissance DATE NULL,
  adresse_postale TEXT NULL,
  email VARCHAR(150) NULL,
  telephone VARCHAR(30) NULL,

  -- Situation actuelle (un seul choix)
  situation ENUM('domicile','trois_chenes','hug','autre') NOT NULL,
  situation_autre VARCHAR(200) NULL,

  -- Personne de référence
  ref_nom_prenom VARCHAR(200) NOT NULL,
  ref_aspect_administratifs TINYINT(1) DEFAULT 0,
  ref_aspect_soins TINYINT(1) DEFAULT 0,
  ref_lien_parente VARCHAR(100) NULL,
  ref_curateur TINYINT(1) DEFAULT 0,
  ref_autre VARCHAR(200) NULL,
  ref_adresse_postale TEXT NULL,
  ref_email VARCHAR(150) NOT NULL,
  ref_telephone VARCHAR(30) NULL,

  -- Médecin traitant
  med_nom VARCHAR(200) NULL,
  med_adresse_postale TEXT NULL,
  med_email VARCHAR(150) NULL,
  med_telephone VARCHAR(30) NULL,

  -- Workflow direction
  statut ENUM(
    'demande_envoyee',
    'en_examen',
    'etape1_validee',
    'info_manquante',
    'refuse',
    'acceptee_liste_attente'
  ) DEFAULT 'demande_envoyee',
  note_interne TEXT NULL,

  ip_soumission VARCHAR(45) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_adm_statut (statut),
  INDEX idx_adm_ref_email (ref_email),
  INDEX idx_adm_token (token_acces),
  INDEX idx_adm_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admissions_historique (
  id CHAR(36) PRIMARY KEY,
  candidat_id CHAR(36) NOT NULL,
  action VARCHAR(50) NOT NULL,
  from_status VARCHAR(50) NULL,
  to_status VARCHAR(50) NULL,
  commentaire TEXT NULL,
  by_admin_id CHAR(36) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_hist_candidat (candidat_id),
  INDEX idx_hist_created (created_at),
  CONSTRAINT fk_hist_candidat FOREIGN KEY (candidat_id)
    REFERENCES admissions_candidats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
