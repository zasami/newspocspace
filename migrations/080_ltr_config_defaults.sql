-- Migration 080 : valeurs par défaut conformes à la Loi suisse sur le travail (LTr)
-- Source : LTr (RS 822.11) + OLT 1 (RS 822.111) applicables au personnel soignant / services.
-- Toutes ces valeurs sont utilisées par l'algorithme de génération de planning
-- (admin/api_modules/planning.php) et appliquées comme plafonds ABSOLUS.

-- ── Plafond hebdomadaire (LTr art. 9 al. 1 let. b) ──
-- 50 heures/semaine max pour le personnel des services, santé, vente, administration.
-- Ce plafond prime sur toute tolérance contractuelle et sur les désirs validés.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_legal_max_hours_week', '50');

-- ── Jours consécutifs max (LTr art. 21) ──
-- Un jour de repos hebdomadaire obligatoire → 6 jours consécutifs travaillés max.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_legal_max_consec_days', '6');

-- ── Tolérance contractuelle au-dessus du taux ──
-- Un employé à taux < 100% ne doit pas dépasser sa cible hebdo + tolérance.
-- 5h est un bon compromis : évite les heures sup abusives tout en permettant
-- d'absorber les besoins de couverture exceptionnels.
-- Le minimum entre (taux+tolérance) et 50h (LTr) fait le plafond effectif.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_max_over_taux_hours_week', '5');

-- ── Nuits consécutives max (LTr art. 17a al. 2) ──
-- 5 nuits consécutives max sur les travailleurs occupés régulièrement ou
-- périodiquement de nuit (plage 23h-06h).
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_consecutif_max_nuit', '5');

-- ── Jours consécutifs AS (recommandation sectorielle EMS / CCT santé) ──
-- Le métier d'aide-soignant(e) est physiquement et émotionnellement exigeant.
-- 3 jours consécutifs max est la pratique recommandée en CCT santé.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_consecutif_max_as', '3');

-- ── Jours consécutifs autres fonctions (INF, ASSC, direction) ──
-- 5 jours consécutifs max dans le flux normal (Pass 2) pour laisser 2 jours off.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_consecutif_max', '5');

-- ── Jours consécutifs couverture (Pass 1 : priorité besoins couverts) ──
-- 6 jours (= plafond LTr art. 21) pour permettre de couvrir les besoins
-- critiques quand les candidats sont rares.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_consecutif_max_besoins', '6');

-- ── Temps de travail plein temps (base contractuelle Suisse) ──
-- 21.7 jours ouvrés/mois × 8.4 heures/jour = 182h/mois pour un 100%
-- soit ≈ 42h/semaine, conforme aux CCT santé romandes (41-42h).
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_jours_ouvres', '21.7');
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_heures_jour', '8.4');

-- ── Direction/responsable : pas de travail le week-end ──
-- Pratique usuelle : les fonctions administratives restent en semaine.
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES ('ia_direction_weekend_off', '1');
