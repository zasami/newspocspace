-- ── Changements d'horaire : support échange croisé multi-jours ──
-- Avant : une seule date_jour + un planning_id (échange même jour)
-- Après : date_demandeur + date_destinataire, potentiellement 2 mois/plannings différents

-- 1. Renommer date_jour → date_demandeur
ALTER TABLE `changements_horaire`
  CHANGE COLUMN `date_jour` `date_demandeur` DATE NOT NULL COMMENT 'Date que le demandeur cède';

-- 2. Ajouter date_destinataire
ALTER TABLE `changements_horaire`
  ADD COLUMN `date_destinataire` DATE NOT NULL COMMENT 'Date que le demandeur prend du destinataire' AFTER `date_demandeur`;

-- 3. Renommer planning_id → planning_demandeur_id
ALTER TABLE `changements_horaire`
  CHANGE COLUMN `planning_id` `planning_demandeur_id` CHAR(36) NOT NULL;

-- 4. Ajouter planning_destinataire_id
ALTER TABLE `changements_horaire`
  ADD COLUMN `planning_destinataire_id` CHAR(36) NOT NULL AFTER `planning_demandeur_id`;

-- 5. Backfill existants (anciens échanges même jour)
UPDATE `changements_horaire`
SET `date_destinataire` = `date_demandeur`,
    `planning_destinataire_id` = `planning_demandeur_id`
WHERE `date_destinataire` = '0000-00-00' OR `planning_destinataire_id` = '';

-- 6. Indexes
ALTER TABLE `changements_horaire`
  DROP INDEX `idx_ch_date`,
  ADD INDEX `idx_ch_date_dem` (`date_demandeur`),
  ADD INDEX `idx_ch_date_dest` (`date_destinataire`);

-- 7. FK pour planning_destinataire_id
ALTER TABLE `changements_horaire`
  DROP FOREIGN KEY `fk_ch_planning`,
  ADD CONSTRAINT `fk_ch_planning_dem` FOREIGN KEY (`planning_demandeur_id`) REFERENCES `plannings`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ch_planning_dest` FOREIGN KEY (`planning_destinataire_id`) REFERENCES `plannings`(`id`) ON DELETE CASCADE;
