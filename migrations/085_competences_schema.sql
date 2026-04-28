-- ═══════════════════════════════════════════════════════════════
-- Module Compétences & Formations FEGEMS — Phase 1 (schéma)
-- ═══════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Mapping fonction → secteur FEGEMS ──────────────────────────
ALTER TABLE fonctions
  ADD COLUMN secteur_fegems
    ENUM('soins','socio_culturel','hotellerie','maintenance','administration','management')
    NULL AFTER `code`;

-- Secteurs locaux additionnels (ex: "Animation", "Intendance"), désactivés par défaut
CREATE TABLE IF NOT EXISTS secteurs_locaux (
    id CHAR(36) PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    secteur_fegems ENUM('soins','socio_culturel','hotellerie','maintenance','administration','management') NOT NULL,
    icone VARCHAR(50) NULL,
    couleur VARCHAR(20) NULL,
    ordre INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Référentiel des thématiques FEGEMS ─────────────────────────
CREATE TABLE IF NOT EXISTS competences_thematiques (
    id CHAR(36) PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(255) NOT NULL,
    categorie ENUM('fegems_base','strategique','referent','referent_pedago') NOT NULL,
    parent_thematique_id CHAR(36) NULL,
    description TEXT NULL,
    duree_validite_mois INT NULL,
    tag_affichage VARCHAR(100) NULL,
    icone VARCHAR(50) NULL,
    couleur VARCHAR(20) NULL,
    ordre INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categorie (categorie),
    CONSTRAINT fk_them_parent FOREIGN KEY (parent_thematique_id)
        REFERENCES competences_thematiques(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Profil d'équipe attendu (matrice thématique × secteur) ─────
CREATE TABLE IF NOT EXISTS competences_profil_attendu (
    id CHAR(36) PRIMARY KEY,
    thematique_id CHAR(36) NOT NULL,
    secteur ENUM('soins','socio_culturel','hotellerie','maintenance','administration','management') NOT NULL,
    requis TINYINT(1) DEFAULT 1,
    niveau_requis TINYINT NULL,
    part_a_former_pct DECIMAL(5,2) DEFAULT 0,
    type_formation_recommande ENUM('interne','continue_catalogue','superieur','vae','autodidacte','tutorat','fpp','autre') DEFAULT 'continue_catalogue',
    objectif_strategique TEXT NULL,
    notes TEXT NULL,
    updated_by CHAR(36) NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_them_sect (thematique_id, secteur),
    CONSTRAINT fk_profil_them FOREIGN KEY (thematique_id)
        REFERENCES competences_thematiques(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cartographie individuelle ──────────────────────────────────
CREATE TABLE IF NOT EXISTS competences_user (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    thematique_id CHAR(36) NOT NULL,
    niveau_actuel TINYINT NULL,
    niveau_requis TINYINT NULL,
    ecart TINYINT GENERATED ALWAYS AS (
        GREATEST(COALESCE(niveau_requis,0) - COALESCE(niveau_actuel,0), 0)
    ) STORED,
    priorite ENUM('haute','moyenne','basse','a_jour') GENERATED ALWAYS AS (
        CASE
          WHEN niveau_actuel IS NULL THEN 'haute'
          WHEN COALESCE(niveau_requis,0) - COALESCE(niveau_actuel,0) >= 2 THEN 'haute'
          WHEN COALESCE(niveau_requis,0) - COALESCE(niveau_actuel,0) = 1 THEN 'moyenne'
          WHEN niveau_actuel >= COALESCE(niveau_requis,0) THEN 'a_jour'
          ELSE 'basse'
        END
    ) STORED,
    type_action ENUM('interne','continue_catalogue','superieur','vae','autodidacte','tutorat','fpp','autre') NULL,
    formation_planifiee_id CHAR(36) NULL,
    formation_validation_id CHAR(36) NULL,
    date_evaluation DATE NULL,
    date_expiration DATE NULL,
    date_validation_manager DATE NULL,
    evaluator_id CHAR(36) NULL,
    attestation_url VARCHAR(500) NULL,
    commentaires TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_them (user_id, thematique_id),
    INDEX idx_priorite (priorite),
    INDEX idx_ecart (ecart),
    INDEX idx_expiration (date_expiration),
    CONSTRAINT fk_cu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cu_them FOREIGN KEY (thematique_id) REFERENCES competences_thematiques(id) ON DELETE CASCADE,
    CONSTRAINT fk_cu_planif FOREIGN KEY (formation_planifiee_id) REFERENCES formations(id) ON DELETE SET NULL,
    CONSTRAINT fk_cu_valid FOREIGN KEY (formation_validation_id) REFERENCES formations(id) ON DELETE SET NULL,
    CONSTRAINT fk_cu_eval FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Référents nommés ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS competences_referents (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    thematique_id CHAR(36) NOT NULL,
    depuis_le DATE NOT NULL,
    jusqu_au DATE NULL,
    has_competences_pedago TINYINT(1) DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ref_user_them (user_id, thematique_id),
    CONSTRAINT fk_ref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ref_them FOREIGN KEY (thematique_id) REFERENCES competences_thematiques(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Lien formations ↔ thématiques ──────────────────────────────
CREATE TABLE IF NOT EXISTS formation_thematiques (
    formation_id CHAR(36) NOT NULL,
    thematique_id CHAR(36) NOT NULL,
    niveau_acquis TINYINT NULL,
    PRIMARY KEY (formation_id, thematique_id),
    CONSTRAINT fk_ft_form FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    CONSTRAINT fk_ft_them FOREIGN KEY (thematique_id) REFERENCES competences_thematiques(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sessions de formation (1 formation = N sessions) ───────────
CREATE TABLE IF NOT EXISTS formation_sessions (
    id CHAR(36) PRIMARY KEY,
    formation_id CHAR(36) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NULL,
    heure_debut TIME NULL,
    heure_fin TIME NULL,
    lieu VARCHAR(255) NULL,
    modalite ENUM('presentiel','elearning','hybride') DEFAULT 'presentiel',
    capacite_max INT NULL,
    places_inscrites INT DEFAULT 0,
    cout_membre DECIMAL(10,2) DEFAULT 0,
    cout_non_membre DECIMAL(10,2) DEFAULT 0,
    contact_inscription_email VARCHAR(255) NULL,
    statut ENUM('ouverte','complete','liste_attente','annulee','passee') DEFAULT 'ouverte',
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_formation_date (formation_id, date_debut),
    INDEX idx_statut (statut),
    CONSTRAINT fk_sess_form FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Propositions d'inscription auto ────────────────────────────
CREATE TABLE IF NOT EXISTS inscription_propositions (
    id CHAR(36) PRIMARY KEY,
    session_id CHAR(36) NOT NULL,
    type_motif ENUM('renouvellement_expire','inc_nouveau','plan_cantonal','recommandation_ocs','manuel') NOT NULL,
    libelle_motif TEXT NULL,
    deadline_action DATE NULL,
    statut ENUM('proposee','en_validation','envoyee','rejetee','expiree') DEFAULT 'proposee',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    validated_by CHAR(36) NULL,
    validated_at DATETIME NULL,
    INDEX idx_ip_statut (statut),
    INDEX idx_ip_deadline (deadline_action),
    CONSTRAINT fk_ip_sess FOREIGN KEY (session_id) REFERENCES formation_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ip_validator FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription_proposition_users (
    proposition_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    motif_individuel ENUM('expiration','jamais_forme','plan_carriere','urgence') NULL,
    statut ENUM('selectionne','exclu_planning','exclu_manuel') DEFAULT 'selectionne',
    commentaire TEXT NULL,
    PRIMARY KEY (proposition_id, user_id),
    CONSTRAINT fk_ipu_prop FOREIGN KEY (proposition_id) REFERENCES inscription_propositions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ipu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Emails d'inscription envoyés à FEGEMS ──────────────────────
CREATE TABLE IF NOT EXISTS inscription_emails (
    id CHAR(36) PRIMARY KEY,
    proposition_id CHAR(36) NOT NULL,
    destinataire VARCHAR(255) NOT NULL,
    cc VARCHAR(500) NULL,
    sujet VARCHAR(500) NULL,
    corps_html LONGTEXT NULL,
    pdf_annexe_url VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    sent_by_id CHAR(36) NULL,
    reponse_recue_le DATETIME NULL,
    statut_reponse ENUM('en_attente','confirmee','partielle','refusee') DEFAULT 'en_attente',
    notes_reponse TEXT NULL,
    INDEX idx_ie_proposition (proposition_id),
    INDEX idx_ie_statut (statut_reponse),
    CONSTRAINT fk_ie_prop FOREIGN KEY (proposition_id) REFERENCES inscription_propositions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ie_sender FOREIGN KEY (sent_by_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historique des évaluations ─────────────────────────────────
CREATE TABLE IF NOT EXISTS competences_evaluations_historique (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    thematique_id CHAR(36) NOT NULL,
    niveau_avant TINYINT NULL,
    niveau_apres TINYINT NULL,
    formation_id CHAR(36) NULL,
    evaluator_id CHAR(36) NULL,
    date_evaluation DATE NOT NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ceh_user_them (user_id, thematique_id),
    INDEX idx_ceh_date (date_evaluation),
    CONSTRAINT fk_ceh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ceh_them FOREIGN KEY (thematique_id) REFERENCES competences_thematiques(id) ON DELETE CASCADE,
    CONSTRAINT fk_ceh_form FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL,
    CONSTRAINT fk_ceh_eval FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Entretiens annuels (créé avant objectifs pour FK) ──────────
CREATE TABLE IF NOT EXISTS entretiens_annuels (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    evaluator_id CHAR(36) NULL,
    annee INT NOT NULL,
    date_entretien DATE NULL,
    statut ENUM('planifie','realise','reporte','annule') DEFAULT 'planifie',
    notes_manager TEXT NULL,
    notes_collaborateur TEXT NULL,
    pdf_url VARCHAR(500) NULL,
    signed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ea_user_annee (user_id, annee),
    INDEX idx_ea_statut_date (statut, date_entretien),
    CONSTRAINT fk_ea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_evaluator FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Objectifs annuels ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS competences_objectifs_annuels (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    annee INT NOT NULL,
    trimestre_cible ENUM('Q1','Q2','Q3','Q4','annuel') DEFAULT 'annuel',
    libelle VARCHAR(500) NOT NULL,
    description TEXT NULL,
    thematique_id_liee CHAR(36) NULL,
    statut ENUM('a_definir','en_cours','atteint','reporte','abandonne') DEFAULT 'a_definir',
    ordre INT DEFAULT 0,
    date_atteint DATE NULL,
    entretien_origine_id CHAR(36) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_obj_user_annee (user_id, annee),
    INDEX idx_obj_statut (statut),
    CONSTRAINT fk_obj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_obj_them FOREIGN KEY (thematique_id_liee) REFERENCES competences_thematiques(id) ON DELETE SET NULL,
    CONSTRAINT fk_obj_entretien FOREIGN KEY (entretien_origine_id) REFERENCES entretiens_annuels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Paramètres formation (clé/valeur typée) ────────────────────
CREATE TABLE IF NOT EXISTS parametres_formation (
    cle VARCHAR(100) PRIMARY KEY,
    valeur TEXT NULL,
    type ENUM('bool','int','decimal','string','json','date') NOT NULL,
    categorie VARCHAR(50) NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    description TEXT NULL,
    valeur_defaut TEXT NULL,
    min_val DECIMAL(10,2) NULL,
    max_val DECIMAL(10,2) NULL,
    options_json JSON NULL,
    visible TINYINT(1) DEFAULT 1,
    ordre INT DEFAULT 0,
    updated_by CHAR(36) NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pf_categorie (categorie, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Extensions formations existantes (budget) ──────────────────
ALTER TABLE formations
  ADD COLUMN cout_formation DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN cout_remplacement DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN frais_annexes DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN budget_alloue DECIMAL(10,2) DEFAULT 0;

-- ── Extensions formation_participants ──────────────────────────
ALTER TABLE formation_participants
  ADD COLUMN evaluation_manager ENUM('satisfaisant','insatisfaisant','en_attente') NULL,
  ADD COLUMN cout_individuel DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN date_realisation DATE NULL,
  ADD COLUMN heures_realisees DECIMAL(5,2) DEFAULT 0,
  ADD COLUMN session_id CHAR(36) NULL,
  ADD COLUMN proposition_id CHAR(36) NULL,
  ADD CONSTRAINT fk_fp_session FOREIGN KEY (session_id) REFERENCES formation_sessions(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_fp_proposition FOREIGN KEY (proposition_id) REFERENCES inscription_propositions(id) ON DELETE SET NULL;

-- ── Extensions users (champs métier maquette fiche employé) ────
ALTER TABLE users
  ADD COLUMN n_plus_un_id CHAR(36) NULL AFTER fonction_id,
  ADD COLUMN experience_fonction_annees DECIMAL(4,1) DEFAULT 0 AFTER taux,
  ADD COLUMN cct VARCHAR(50) NULL AFTER type_contrat,
  ADD COLUMN diplome_principal VARCHAR(255) NULL AFTER cct,
  ADD COLUMN reconnaissance_crs_annee YEAR NULL AFTER diplome_principal,
  ADD COLUMN prochain_entretien_date DATE NULL AFTER date_fin_contrat,
  ADD COLUMN disponibilite_formation TEXT NULL AFTER prochain_entretien_date,
  ADD CONSTRAINT fk_users_nplus1 FOREIGN KEY (n_plus_un_id) REFERENCES users(id) ON DELETE SET NULL;
