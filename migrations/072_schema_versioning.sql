-- Migration 072: suivi des versions de schema pour compatibilite des sauvegardes

CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(100) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enregistrer toutes les migrations existantes comme deja appliquees
INSERT IGNORE INTO schema_migrations (migration) VALUES
('001_initial'),('002_ems_config'),('003_seed_employees'),
('004_messages'),('005_fonctions'),('006_modules'),
('007_etages'),('008_groupes'),('009_horaires_types'),
('010_desirs'),('011_absences'),('012_besoins'),
('013_plannings'),('014_planning_assignations'),
('015_votes'),('016_rate_limits'),('017_connexions'),
('018_documents'),('019_fiches_salaire'),('020_import_export'),
('021_alerts'),('022_todos'),('023_notes'),
('024_changements'),('025_pv'),('026_sondages'),
('027_vacances'),('028_periodes_bloquees'),
('029_repartition'),('030_email_externe'),
('031_residents'),('032_marquage'),('033_menus'),
('034_famille'),('035_hygiene'),('036_protection'),
('037_agenda'),('038_roadmap'),('039_mur'),
('040_wiki'),('041_annonces'),('042_annuaire'),
('043_recrutement'),('044_stagiaires'),
('045_config_ia'),('046_global_search'),
('047_document_versions'),('048_document_access'),
('049_repartition_v2'),('050_pv_audio'),
('051_wiki_v2'),('052_wiki_analytics'),
('053_sondages_v2'),('054_securite'),
('055_care_residents'),('056_care_marquage'),
('057_care_menus'),('058_care_famille'),
('059_stagiaires_v2'),('060_stagiaires_taches'),
('061_stagiaires_evaluations'),('062_formations'),
('063_candidatures'),('064_offres_emploi'),
('065_seed_horaires'),('066_seed_besoins'),
('067_fonctions_profiles'),('068_seed_taches'),
('069_seed_historique'),('070_reseed_reports'),
('071_backups'),('072_schema_versioning');

-- Version du schema courant
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('schema_version', '072');
