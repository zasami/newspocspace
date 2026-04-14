-- ─────────────────────────────────────────────────────────────
-- Migration 067 — Split fonction APP + profils de permissions par fonction
-- ─────────────────────────────────────────────────────────────

-- 1. Ajouter colonne default_denied_perms (JSON) à fonctions
ALTER TABLE fonctions
    ADD COLUMN default_denied_perms JSON DEFAULT NULL
    COMMENT 'Liste des permissions à refuser par défaut aux users de cette fonction';

-- 2. Renommer l'ancien "Apprenti / Stagiaire" (code APP) en "Apprenti"
UPDATE fonctions SET nom = 'Apprenti' WHERE code = 'APP';

-- 3. Ajouter la fonction "Stagiaire" (code STAG)
INSERT IGNORE INTO fonctions (id, nom, code, ordre, created_at)
VALUES (UUID(), 'Stagiaire', 'STAG', 4, NOW());

-- Réordonner
UPDATE fonctions SET ordre = 4 WHERE code = 'STAG';
UPDATE fonctions SET ordre = 5 WHERE code = 'APP';
UPDATE fonctions SET ordre = 6 WHERE code = 'CIV';
UPDATE fonctions SET ordre = 7 WHERE code = 'ASE';
UPDATE fonctions SET ordre = 8 WHERE code = 'RUV';
UPDATE fonctions SET ordre = 9 WHERE code = 'RS';
UPDATE fonctions SET ordre = 10 WHERE code = 'CHEF';
UPDATE fonctions SET ordre = 11 WHERE code = 'CUIS';
UPDATE fonctions SET ordre = 12 WHERE code = 'HOT';

-- 4. Profils de permissions par défaut
-- Hôtellerie : uniquement cuisine + infos
UPDATE fonctions SET default_denied_perms = JSON_ARRAY(
    'page_planning','page_repartition','page_desirs','page_vacances',
    'page_absences','page_changements','page_pv','page_sondages',
    'page_votes'
) WHERE code = 'HOT';

-- Chef cuisinier : accès cuisine complet + pas de soins
UPDATE fonctions SET default_denied_perms = JSON_ARRAY(
    'page_repartition','page_pv'
) WHERE code = 'CHEF';

-- Cuisinier : cuisine + repas uniquement
UPDATE fonctions SET default_denied_perms = JSON_ARRAY(
    'page_planning','page_repartition','page_desirs','page_vacances',
    'page_absences','page_changements','page_pv','page_sondages','page_votes',
    'cuisine_reservations_famille','cuisine_table_vip'
) WHERE code = 'CUIS';

-- Stagiaire : très restreint (juste messagerie, sondages optionnels)
UPDATE fonctions SET default_denied_perms = JSON_ARRAY(
    'page_planning','page_repartition','page_desirs','page_vacances',
    'page_absences','page_changements','page_pv','page_votes',
    'page_fiches_salaire','page_covoiturage','page_cuisine',
    'cuisine_saisie_menu','cuisine_reservations_collab','cuisine_reservations_famille','cuisine_table_vip'
) WHERE code = 'STAG';

-- Apprenti : un peu plus large que stagiaire (peut voir son planning)
UPDATE fonctions SET default_denied_perms = JSON_ARRAY(
    'page_repartition','page_pv','page_votes','page_sondages',
    'page_fiches_salaire',
    'cuisine_saisie_menu','cuisine_reservations_famille','cuisine_table_vip'
) WHERE code = 'APP';
