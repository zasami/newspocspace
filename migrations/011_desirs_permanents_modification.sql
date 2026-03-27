-- P3: Modification de désirs permanents via proposition
-- Ajoute statut + replaces_id pour gérer le workflow de modification
ALTER TABLE `desirs_permanents`
  ADD COLUMN `statut` ENUM('en_attente','valide','refuse') NOT NULL DEFAULT 'valide' AFTER `is_active`,
  ADD COLUMN `replaces_id` CHAR(36) DEFAULT NULL AFTER `statut`,
  ADD COLUMN `valide_par` CHAR(36) DEFAULT NULL AFTER `replaces_id`,
  ADD COLUMN `valide_at` DATETIME DEFAULT NULL AFTER `valide_par`,
  ADD COLUMN `commentaire_chef` TEXT DEFAULT NULL AFTER `valide_at`;

ALTER TABLE `desirs_permanents`
  ADD KEY `idx_dp_replaces` (`replaces_id`),
  ADD KEY `idx_dp_statut` (`statut`);
