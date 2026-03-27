-- Migration 018: Ajout d'index manquants pour la performance
-- Identifiés lors de l'audit sécurité/performance

-- Index composite sur planning_assignations (très fréquemment requêté)
ALTER TABLE `planning_assignations`
  ADD INDEX IF NOT EXISTS `idx_pa_planning_user_date` (`planning_id`, `user_id`, `date_jour`),
  ADD INDEX IF NOT EXISTS `idx_pa_date_module` (`date_jour`, `module_id`);

-- Index sur desirs pour les requêtes par user + mois + statut
ALTER TABLE `desirs`
  ADD INDEX IF NOT EXISTS `idx_desirs_user_mois` (`user_id`, `mois_cible`);

-- Index sur pv_comments pour les requêtes par pv_id
ALTER TABLE `pv_comments`
  ADD INDEX IF NOT EXISTS `idx_pv_comments_pv` (`pv_id`);

-- Nettoyage régulier des rate_limits périmés
DELETE FROM `rate_limits` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR);
