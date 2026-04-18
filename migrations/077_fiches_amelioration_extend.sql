-- 077_fiches_amelioration_extend.sql — Colonnes supplémentaires
-- Ajoute les champs du formulaire complet (mockup réf. FAC-YYYY-NNN)
-- 2026-04-18

ALTER TABLE fiches_amelioration
    ADD COLUMN reference_code VARCHAR(32) NULL UNIQUE AFTER id,
    ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER statut,
    ADD COLUMN type_evenement ENUM('incident','dysfonctionnement','suggestion','non_conformite','plainte','presque_accident')
        NOT NULL DEFAULT 'suggestion' AFTER visibility,
    ADD COLUMN personnes_concernees_types VARCHAR(255) NULL
        COMMENT 'CSV: resident, collaborateur, visiteur, prestataire' AFTER type_evenement,
    ADD COLUMN unite_module_id CHAR(36) NULL AFTER personnes_concernees_types,
    ADD COLUMN date_evenement DATE NULL AFTER suggestion,
    ADD COLUMN heure_evenement TIME NULL AFTER date_evenement,
    ADD COLUMN lieu_precis VARCHAR(255) NULL AFTER heure_evenement,
    ADD COLUMN mesures_immediates TEXT NULL AFTER lieu_precis,
    -- Section admin : Analyse & Suivi
    ADD COLUMN causes_identifiees VARCHAR(255) NULL
        COMMENT 'CSV: organisationnelle, facteur_humain, materiel, communication, procedure, indeterminee' AFTER mesures_immediates,
    ADD COLUMN actions_correctives TEXT NULL AFTER causes_identifiees,
    ADD COLUMN responsable_action_id CHAR(36) NULL AFTER actions_correctives,
    ADD COLUMN delai_realisation DATE NULL AFTER responsable_action_id,
    -- Section admin : Clôture
    ADD COLUMN date_cloture DATE NULL AFTER delai_realisation,
    ADD COLUMN valide_par_id CHAR(36) NULL AFTER date_cloture,
    ADD COLUMN resultat_efficacite TEXT NULL AFTER valide_par_id,
    ADD CONSTRAINT fk_fiche_unite FOREIGN KEY (unite_module_id) REFERENCES modules(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_fiche_responsable FOREIGN KEY (responsable_action_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_fiche_valide FOREIGN KEY (valide_par_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD INDEX idx_fiche_ref (reference_code),
    ADD INDEX idx_fiche_type (type_evenement),
    ADD INDEX idx_fiche_draft (is_draft);

INSERT INTO schema_migrations (migration, applied_at) VALUES ('077_fiches_amelioration_extend', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);

UPDATE ems_config SET config_value = '077' WHERE config_key = 'schema_version';
